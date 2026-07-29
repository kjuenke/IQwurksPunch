<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Container;
use App\Logging\LoggerInterface;
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

    private NotificationRecipientService $recipients;

    private LoggerInterface $logger;


    public function __construct()
    {
        $this->config =
            require __DIR__
            .
            '/../../config/mail.php';


        $this->emails =
            Container::emailRepository();


        $this->recipients =
            Container::notificationRecipientService();


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
        array $attachments = []
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


        $this->logger->info(
            'Email delivery started.',
            [
                'subject' =>
                    $subject,

                'notification_type' =>
                    $notificationType,

                'recipient_count' =>
                    $recipientCount,

                'attachment_count' =>
                    $attachmentCount,

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


        if ($recipientCount === 0) {

            $this->logger->error(
                'Email delivery cannot continue because no active recipients are subscribed.',
                [
                    'subject' =>
                        $subject,

                    'notification_type' =>
                        $notificationType,

                    'attachment_count' =>
                        $attachmentCount
                ]
            );


            $this->recordHistory(
                $subject,
                [],
                'failed'
            );


            return false;
        }


        try {

            $attachments =
                $this->normalizeAttachments(
                    $attachments
                );


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


            $this->recordHistory(
                $subject,
                $recipients,
                'sent'
            );


            $this->logger->info(
                'Email delivery completed successfully.',
                [
                    'subject' =>
                        $subject,

                    'notification_type' =>
                        $notificationType,

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

            $this->recordHistory(
                $subject,
                $recipients,
                'failed'
            );


            $this->logger->error(
                'Email delivery failed.',
                [
                    'subject' =>
                        $subject,

                    'notification_type' =>
                        $notificationType,

                    'recipient_count' =>
                        $recipientCount,

                    'attachment_count' =>
                        count(
                            $attachments
                        ),

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


    private function recordHistory(
        string $subject,
        array $recipients,
        string $status
    ): void
    {
        try {

            $recorded =
                $this->emails->create(
                    $subject,
                    implode(
                        ', ',
                        $recipients
                    ),
                    $status
                );


            if (!$recorded) {

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
        }
    }
}
