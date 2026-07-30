<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Container;
use App\Exceptions\EmailAttachmentSizeExceededException;
use App\Logging\LoggerInterface;
use App\Repositories\EmailDeliveryAttemptRepository;
use App\Repositories\EmailRepository;
use InvalidArgumentException;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

class MailService
{
    private array $config;

    private EmailRepository $emails;

    private EmailDeliveryAttemptRepository $deliveryAttempts;

    private NotificationRecipientService $recipients;

    private EmailAttachmentSizePolicyService $attachmentSizePolicy;

    private LoggerInterface $logger;


    public function __construct()
    {
        $this->config =
            require __DIR__
            .
            '/../../config/mail.php';


        $this->emails =
            Container::emailRepository();


        $this->deliveryAttempts =
            new EmailDeliveryAttemptRepository(
                Container::db()
            );


        $this->recipients =
            Container::notificationRecipientService();


        $this->attachmentSizePolicy =
            new EmailAttachmentSizePolicyService();


        $this->logger =
            Container::logger(
                'mail'
            );
    }


    /**
     * @param array<int,array{
     *     filename:mixed,
     *     content_type?:mixed,
     *     contents:mixed
     * }> $attachments
     */
    public function send(
        string $subject,
        string $body,
        string $notificationType = 'daily_payroll',
        array $attachments = [],
        string $source = 'manual',
        ?int $scheduleId = null,
        int $attemptNumber = 1,
        int $maxAttempts = 1,
        ?int $retryOfId = null
    ): bool
    {
        $recipients =
            $this->recipients->activeEmailsFor(
                $notificationType
            );


        $recipientCount =
            count(
                $recipients
            );


        $attachmentCount =
            count(
                $attachments
            );


        $attemptId = null;


        $this->logger->info(
            'Email delivery started.',
            [
                'subject' =>
                    $subject,

                'notification_type' =>
                    $notificationType,

                'source' =>
                    $source,

                'schedule_id' =>
                    $scheduleId,

                'attempt_number' =>
                    $attemptNumber,

                'max_attempts' =>
                    $maxAttempts,

                'retry_of_id' =>
                    $retryOfId,

                'recipient_count' =>
                    $recipientCount,

                'attachment_count' =>
                    $attachmentCount,

                'attachment_max_total_bytes' =>
                    $this->attachmentSizePolicy
                        ->maxTotalBytes(),

                'transport_host' =>
                    $this->config['host']
                    ??
                    null,

                'transport_port' =>
                    $this->config['port']
                    ??
                    null
            ]
        );


        try {

            $attachments =
                $this->normalizeAttachments(
                    $attachments
                );


            $attemptId =
                $this->beginDeliveryAttempt(
                    $notificationType,
                    $source,
                    $subject,
                    $recipients,
                    $attachments,
                    $scheduleId,
                    $attemptNumber,
                    $maxAttempts,
                    $retryOfId
                );


            $this->assertAttachmentSizeWithinLimit(
                $attachments
            );


            if ($recipientCount === 0) {

                $errorMessage =
                    'No active recipients are subscribed to this notification type.';


                $this->logger->error(
                    'Email delivery cannot continue because no active recipients are subscribed.',
                    [
                        'subject' =>
                            $subject,

                        'notification_type' =>
                            $notificationType,

                        'source' =>
                            $source,

                        'delivery_attempt_id' =>
                            $attemptId,

                        'attachment_count' =>
                            count(
                                $attachments
                            )
                    ]
                );


                $emailLogId =
                    $this->recordHistory(
                        $subject,
                        [],
                        'failed'
                    );


                $this->markDeliveryAttemptFailed(
                    $attemptId,
                    $errorMessage,
                    $attemptNumber >= $maxAttempts,
                    $emailLogId
                );


                return false;
            }


            $dsn =
                sprintf(
                    'smtp://%s:%s@%s:%s',
                    urlencode(
                        (string)(
                            $this->config['username']
                            ??
                            ''
                        )
                    ),
                    urlencode(
                        (string)(
                            $this->config['password']
                            ??
                            ''
                        )
                    ),
                    (string)(
                        $this->config['host']
                        ??
                        ''
                    ),
                    (string)(
                        $this->config['port']
                        ??
                        587
                    )
                );


            $transport =
                Transport::fromDsn(
                    $dsn
                );


            $mailer =
                new Mailer(
                    $transport
                );


            $email =
                (new Email())
                    ->from(
                        $this->fromAddress()
                    )
                    ->subject(
                        $subject
                    )
                    ->text(
                        $body
                    );


            foreach ($recipients as $recipient) {

                $email->addTo(
                    $recipient
                );
            }


            foreach ($attachments as $attachment) {

                $email->attach(
                    $attachment['contents'],
                    $attachment['filename'],
                    $attachment['content_type']
                );
            }


            $mailer->send(
                $email
            );


            $emailLogId =
                $this->recordHistory(
                    $subject,
                    $recipients,
                    'sent'
                );


            $this->markDeliveryAttemptSent(
                $attemptId,
                $emailLogId
            );


            $this->logger->info(
                'Email delivery completed successfully.',
                [
                    'subject' =>
                        $subject,

                    'notification_type' =>
                        $notificationType,

                    'source' =>
                        $source,

                    'schedule_id' =>
                        $scheduleId,

                    'delivery_attempt_id' =>
                        $attemptId,

                    'attempt_number' =>
                        $attemptNumber,

                    'max_attempts' =>
                        $maxAttempts,

                    'recipient_count' =>
                        $recipientCount,

                    'attachment_count' =>
                        count(
                            $attachments
                        ),

                    'attachment_names' =>
                        $this->attachmentNames(
                            $attachments
                        ),

                    'attachment_size_bytes' =>
                        $this->attachmentSizeBytes(
                            $attachments
                        )
                ]
            );


            return true;

        } catch (Throwable $exception) {

            $emailLogId =
                $this->recordHistory(
                    $subject,
                    $recipients,
                    'failed'
                );


            $permanentFailure =
                $this->isPermanentFailure(
                    $exception,
                    $attemptNumber,
                    $maxAttempts
                );


            $this->markDeliveryAttemptFailed(
                $attemptId,
                $exception->getMessage(),
                $permanentFailure,
                $emailLogId
            );


            $this->logger->error(
                'Email delivery failed.',
                [
                    'subject' =>
                        $subject,

                    'notification_type' =>
                        $notificationType,

                    'source' =>
                        $source,

                    'schedule_id' =>
                        $scheduleId,

                    'delivery_attempt_id' =>
                        $attemptId,

                    'attempt_number' =>
                        $attemptNumber,

                    'max_attempts' =>
                        $maxAttempts,

                    'retry_of_id' =>
                        $retryOfId,

                    'recipient_count' =>
                        $recipientCount,

                    'attachment_count' =>
                        count(
                            $attachments
                        ),

                    'attachment_size_bytes' =>
                        is_array(
                            $attachments
                        )
                            ? $this->safeAttachmentSizeBytes(
                                $attachments
                            )
                            : null,

                    'attachment_max_total_bytes' =>
                        $this->attachmentSizePolicy
                            ->maxTotalBytes(),

                    'permanent_failure' =>
                        $permanentFailure,

                    'exception_class' =>
                        $exception::class,

                    'exception_message' =>
                        $exception->getMessage(),

                    'exception_file' =>
                        $exception->getFile(),

                    'exception_line' =>
                        $exception->getLine()
                ]
            );


            throw $exception;
        }
    }


    /**
     * @param array<int,mixed> $attachments
     *
     * @return array<int,array{
     *     filename:string,
     *     content_type:string,
     *     contents:string
     * }>
     */
    private function normalizeAttachments(
        array $attachments
    ): array
    {
        $normalized = [];


        foreach (
            $attachments
            as
            $index =>
            $attachment
        ) {
            if (!is_array($attachment)) {

                throw new InvalidArgumentException(
                    'Email attachment #'
                    .
                    (
                        $index
                        +
                        1
                    )
                    .
                    ' must be an array.'
                );
            }


            $filename =
                trim(
                    str_replace(
                        [
                            "\r",
                            "\n",
                            "\0"
                        ],
                        '',
                        basename(
                            trim(
                                (string)(
                                    $attachment['filename']
                                    ??
                                    ''
                                )
                            )
                        )
                    )
                );


            if (
                $filename === ''
                ||
                $filename === '.'
                ||
                $filename === '..'
            ) {
                throw new InvalidArgumentException(
                    'Email attachment #'
                    .
                    (
                        $index
                        +
                        1
                    )
                    .
                    ' requires a valid filename.'
                );
            }


            $contentType =
                trim(
                    str_replace(
                        [
                            "\r",
                            "\n",
                            "\0"
                        ],
                        '',
                        (string)(
                            $attachment['content_type']
                            ??
                            'application/octet-stream'
                        )
                    )
                );


            if ($contentType === '') {

                $contentType =
                    'application/octet-stream';
            }


            $contents =
                $attachment['contents']
                ??
                null;


            if (!is_string($contents)) {

                throw new InvalidArgumentException(
                    'Email attachment '
                    .
                    $filename
                    .
                    ' must contain string data.'
                );
            }


            if ($contents === '') {

                throw new InvalidArgumentException(
                    'Email attachment '
                    .
                    $filename
                    .
                    ' cannot be empty.'
                );
            }


            $normalized[] = [
                'filename' =>
                    $filename,

                'content_type' =>
                    $contentType,

                'contents' =>
                    $contents
            ];
        }


        return $normalized;
    }


    /**
     * @param array<int,array{
     *     filename:string,
     *     content_type:string,
     *     contents:string
     * }> $attachments
     *
     * @return array<int,string>
     */
    private function attachmentNames(
        array $attachments
    ): array
    {
        return
            array_values(
                array_map(
                    static fn (
                        array $attachment
                    ): string =>
                        $attachment['filename'],
                    $attachments
                )
            );
    }


    /**
     * @param array<int,array{
     *     filename:string,
     *     content_type:string,
     *     contents:string
     * }> $attachments
     */
    private function attachmentSizeBytes(
        array $attachments
    ): int
    {
        $bytes = 0;


        foreach ($attachments as $attachment) {

            $bytes +=
                strlen(
                    $attachment['contents']
                );
        }


        return $bytes;
    }


    /**
     * @param array<int,array{
     *     filename:string,
     *     content_type:string,
     *     contents:string
     * }> $attachments
     */
    private function assertAttachmentSizeWithinLimit(
        array $attachments
    ): void
    {
        $this->attachmentSizePolicy
            ->assertWithinLimit(
                $this->attachmentSizeBytes(
                    $attachments
                )
            );
    }


    private function isPermanentFailure(
        Throwable $exception,
        int $attemptNumber,
        int $maxAttempts
    ): bool
    {
        return
            $exception
            instanceof
            EmailAttachmentSizeExceededException
            ||
            $attemptNumber >= $maxAttempts;
    }


    /**
     * @param array<int,mixed> $attachments
     */
    private function safeAttachmentSizeBytes(
        array $attachments
    ): ?int
    {
        $bytes = 0;


        foreach ($attachments as $attachment) {

            if (
                !is_array(
                    $attachment
                )
                ||
                !array_key_exists(
                    'contents',
                    $attachment
                )
                ||
                !is_string(
                    $attachment['contents']
                )
            ) {
                return null;
            }


            $bytes +=
                strlen(
                    $attachment['contents']
                );
        }


        return $bytes;
    }


    /**
     * @param array<int,string> $recipients
     * @param array<int,array{
     *     filename:string,
     *     content_type:string,
     *     contents:string
     * }> $attachments
     */
    private function beginDeliveryAttempt(
        string $notificationType,
        string $source,
        string $subject,
        array $recipients,
        array $attachments,
        ?int $scheduleId,
        int $attemptNumber,
        int $maxAttempts,
        ?int $retryOfId
    ): ?int
    {
        try {

            return
                $this->deliveryAttempts
                    ->createPending(
                        $notificationType,
                        $source,
                        $subject,
                        empty(
                            $recipients
                        )
                            ? '(none)'
                            : implode(
                                ', ',
                                $recipients
                            ),
                        $this->attachmentNames(
                            $attachments
                        ),
                        $this->attachmentSizeBytes(
                            $attachments
                        ),
                        null,
                        $scheduleId,
                        $attemptNumber,
                        $maxAttempts,
                        $retryOfId
                    );

        } catch (Throwable $exception) {

            $this->logger->warning(
                'Email delivery attempt history could not be started.',
                [
                    'subject' =>
                        $subject,

                    'notification_type' =>
                        $notificationType,

                    'source' =>
                        $source,

                    'schedule_id' =>
                        $scheduleId,

                    'attempt_number' =>
                        $attemptNumber,

                    'max_attempts' =>
                        $maxAttempts,

                    'retry_of_id' =>
                        $retryOfId,

                    'exception_class' =>
                        $exception::class,

                    'exception_message' =>
                        $exception->getMessage()
                ]
            );


            return null;
        }
    }


    private function markDeliveryAttemptSent(
        ?int $attemptId,
        ?int $emailLogId
    ): void
    {
        if ($attemptId === null) {
            return;
        }


        try {

            if (
                !$this->deliveryAttempts
                    ->markSent(
                        $attemptId,
                        $emailLogId
                    )
            ) {
                $this->logger->warning(
                    'Email delivery attempt could not be marked as sent.',
                    [
                        'delivery_attempt_id' =>
                            $attemptId,

                        'email_log_id' =>
                            $emailLogId
                    ]
                );
            }

        } catch (Throwable $exception) {

            $this->logger->warning(
                'Email delivery attempt raised an exception while being marked as sent.',
                [
                    'delivery_attempt_id' =>
                        $attemptId,

                    'email_log_id' =>
                        $emailLogId,

                    'exception_class' =>
                        $exception::class,

                    'exception_message' =>
                        $exception->getMessage()
                ]
            );
        }
    }


    private function markDeliveryAttemptFailed(
        ?int $attemptId,
        string $errorMessage,
        bool $permanentFailure,
        ?int $emailLogId
    ): void
    {
        if ($attemptId === null) {
            return;
        }


        try {

            if (
                !$this->deliveryAttempts
                    ->markFailed(
                        $attemptId,
                        $errorMessage,
                        $permanentFailure,
                        $emailLogId
                    )
            ) {
                $this->logger->warning(
                    'Email delivery attempt could not be marked as failed.',
                    [
                        'delivery_attempt_id' =>
                            $attemptId,

                        'email_log_id' =>
                            $emailLogId,

                        'permanent_failure' =>
                            $permanentFailure
                    ]
                );
            }

        } catch (Throwable $exception) {

            $this->logger->warning(
                'Email delivery attempt raised an exception while being marked as failed.',
                [
                    'delivery_attempt_id' =>
                        $attemptId,

                    'email_log_id' =>
                        $emailLogId,

                    'permanent_failure' =>
                        $permanentFailure,

                    'exception_class' =>
                        $exception::class,

                    'exception_message' =>
                        $exception->getMessage()
                ]
            );
        }
    }


    private function fromAddress(): Address|string
    {
        $fromEmail =
            trim(
                (string)(
                    $this->config['from_email']
                    ??
                    ''
                )
            );


        $fromName =
            trim(
                (string)(
                    $this->config['from_name']
                    ??
                    ''
                )
            );


        if ($fromName === '') {

            return $fromEmail;
        }


        return new Address(
            $fromEmail,
            $fromName
        );
    }


    /**
     * @param array<int,string> $recipients
     */
    private function recordHistory(
        string $subject,
        array $recipients,
        string $status
    ): ?int
    {
        try {

            $emailLogId =
                $this->emails
                    ->createAndReturnId(
                        $subject,
                        implode(
                            ', ',
                            $recipients
                        ),
                        $status
                    );


            if ($emailLogId === null) {

                $this->logger->warning(
                    'Email delivery history could not be recorded.',
                    [
                        'subject' =>
                            $subject,

                        'status' =>
                            $status,

                        'recipient_count' =>
                            count(
                                $recipients
                            )
                    ]
                );
            }


            return $emailLogId;

        } catch (Throwable $exception) {

            $this->logger->warning(
                'Email delivery history raised an exception.',
                [
                    'subject' =>
                        $subject,

                    'status' =>
                        $status,

                    'recipient_count' =>
                        count(
                            $recipients
                        ),

                    'exception_class' =>
                        $exception::class,

                    'exception_message' =>
                        $exception->getMessage()
                ]
            );


            return null;
        }
    }
}
