<?php
declare(strict_types=1);

namespace App\Repositories;

use InvalidArgumentException;
use PDO;
use RuntimeException;

final class EmailDeliveryAttemptRepository
{
    private const SOURCES = [
        'manual',
        'scheduled',
        'system',
        'retry'
    ];


    private PDO $db;


    public function __construct(
        PDO $db
    )
    {
        $this->db =
            $db;
    }


    /**
     * @param array<int,string> $attachmentNames
     */
    public function createPending(
        string $notificationType,
        string $source,
        string $subject,
        string $recipients,
        array $attachmentNames = [],
        int $attachmentSizeBytes = 0,
        ?int $emailLogId = null,
        ?int $scheduleId = null,
        int $attemptNumber = 1,
        int $maxAttempts = 1,
        ?int $retryOfId = null
    ): int
    {
        $notificationType =
            $this->requiredText(
                $notificationType,
                'Notification type'
            );


        $source =
            strtolower(
                $this->requiredText(
                    $source,
                    'Delivery source'
                )
            );


        if (
            !in_array(
                $source,
                self::SOURCES,
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Delivery source must be manual, scheduled, system, or retry.'
            );
        }


        $subject =
            $this->requiredText(
                $subject,
                'Email subject'
            );


        $recipients =
            $this->requiredText(
                $recipients,
                'Email recipients'
            );


        if ($attachmentSizeBytes < 0) {
            throw new InvalidArgumentException(
                'Attachment size cannot be negative.'
            );
        }


        if ($attemptNumber < 1) {
            throw new InvalidArgumentException(
                'Attempt number must be at least 1.'
            );
        }


        if ($maxAttempts < 1) {
            throw new InvalidArgumentException(
                'Maximum attempts must be at least 1.'
            );
        }


        if ($attemptNumber > $maxAttempts) {
            throw new InvalidArgumentException(
                'Attempt number cannot exceed maximum attempts.'
            );
        }


        $attachmentNames =
            $this->attachmentNames(
                $attachmentNames
            );


        $attachmentNamesJson =
            json_encode(
                $attachmentNames,
                JSON_UNESCAPED_SLASHES
                |
                JSON_UNESCAPED_UNICODE
            );


        if (!is_string($attachmentNamesJson)) {
            throw new RuntimeException(
                'Attachment names could not be encoded.'
            );
        }


        $statement =
            $this->db->prepare(
                "
                INSERT INTO email_delivery_attempts
                (
                    email_log_id,
                    schedule_id,
                    notification_type,
                    source,
                    subject,
                    recipients,
                    status,
                    attempt_number,
                    max_attempts,
                    retry_of_id,
                    permanent_failure,
                    error_message,
                    attachment_count,
                    attachment_names,
                    attachment_size_bytes,
                    started_at,
                    completed_at,
                    created_at
                )

                VALUES
                (
                    :email_log_id,
                    :schedule_id,
                    :notification_type,
                    :source,
                    :subject,
                    :recipients,
                    'pending',
                    :attempt_number,
                    :max_attempts,
                    :retry_of_id,
                    0,
                    NULL,
                    :attachment_count,
                    :attachment_names,
                    :attachment_size_bytes,
                    CURRENT_TIMESTAMP,
                    NULL,
                    CURRENT_TIMESTAMP
                )
                "
            );


        $created =
            $statement->execute(
                [
                    'email_log_id' =>
                        $emailLogId,

                    'schedule_id' =>
                        $scheduleId,

                    'notification_type' =>
                        $notificationType,

                    'source' =>
                        $source,

                    'subject' =>
                        $subject,

                    'recipients' =>
                        $recipients,

                    'attempt_number' =>
                        $attemptNumber,

                    'max_attempts' =>
                        $maxAttempts,

                    'retry_of_id' =>
                        $retryOfId,

                    'attachment_count' =>
                        count(
                            $attachmentNames
                        ),

                    'attachment_names' =>
                        $attachmentNamesJson,

                    'attachment_size_bytes' =>
                        $attachmentSizeBytes
                ]
            );


        if (!$created) {
            throw new RuntimeException(
                'The email delivery attempt could not be created.'
            );
        }


        $id =
            (int)$this->db
                ->lastInsertId();


        if ($id < 1) {
            throw new RuntimeException(
                'The email delivery attempt ID could not be determined.'
            );
        }


        return $id;
    }


    public function markSent(
        int $attemptId,
        ?int $emailLogId = null
    ): bool
    {
        $attemptId =
            $this->validId(
                $attemptId,
                'Delivery attempt'
            );


        $statement =
            $this->db->prepare(
                "
                UPDATE email_delivery_attempts

                SET
                    email_log_id =
                        COALESCE
                        (
                            :email_log_id,
                            email_log_id
                        ),

                    status = 'sent',
                    permanent_failure = 0,
                    error_message = NULL,
                    completed_at = CURRENT_TIMESTAMP

                WHERE id = :id
                  AND status = 'pending'
                "
            );


        $statement->execute(
            [
                'email_log_id' =>
                    $emailLogId,

                'id' =>
                    $attemptId
            ]
        );


        return
            $statement->rowCount() === 1;
    }


    public function markFailed(
        int $attemptId,
        string $errorMessage,
        bool $permanentFailure = false,
        ?int $emailLogId = null
    ): bool
    {
        $attemptId =
            $this->validId(
                $attemptId,
                'Delivery attempt'
            );


        $errorMessage =
            trim(
                $errorMessage
            );


        if ($errorMessage === '') {
            $errorMessage =
                'Email delivery failed without an error message.';
        }


        if (
            strlen(
                $errorMessage
            ) > 4000
        ) {
            $errorMessage =
                substr(
                    $errorMessage,
                    0,
                    4000
                );
        }


        $statement =
            $this->db->prepare(
                "
                UPDATE email_delivery_attempts

                SET
                    email_log_id =
                        COALESCE
                        (
                            :email_log_id,
                            email_log_id
                        ),

                    status = 'failed',
                    permanent_failure = :permanent_failure,
                    error_message = :error_message,
                    completed_at = CURRENT_TIMESTAMP

                WHERE id = :id
                  AND status = 'pending'
                "
            );


        $statement->execute(
            [
                'email_log_id' =>
                    $emailLogId,

                'permanent_failure' =>
                    $permanentFailure
                        ? 1
                        : 0,

                'error_message' =>
                    $errorMessage,

                'id' =>
                    $attemptId
            ]
        );


        return
            $statement->rowCount() === 1;
    }


    /**
     * @return array<string,mixed>|null
     */
    public function find(
        int $attemptId
    ): ?array
    {
        $attemptId =
            $this->validId(
                $attemptId,
                'Delivery attempt'
            );


        $statement =
            $this->db->prepare(
                "
                SELECT *
                FROM email_delivery_attempts

                WHERE id = :id

                LIMIT 1
                "
            );


        $statement->execute(
            [
                'id' =>
                    $attemptId
            ]
        );


        $attempt =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );


        return
            is_array(
                $attempt
            )
                ? $attempt
                : null;
    }


    /**
     * @return array<int,array<string,mixed>>
     */
    public function recent(
        int $limit = 100
    ): array
    {
        if ($limit < 1) {
            throw new InvalidArgumentException(
                'Delivery history limit must be at least 1.'
            );
        }


        $limit =
            min(
                $limit,
                1000
            );


        $statement =
            $this->db->prepare(
                "
                SELECT *
                FROM email_delivery_attempts

                ORDER BY id DESC

                LIMIT :limit
                "
            );


        $statement->bindValue(
            'limit',
            $limit,
            PDO::PARAM_INT
        );


        $statement->execute();


        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            );
    }


    private function validId(
        int $id,
        string $label
    ): int
    {
        if ($id < 1) {
            throw new InvalidArgumentException(
                $label
                .
                ' ID must be greater than zero.'
            );
        }


        return $id;
    }


    private function requiredText(
        string $value,
        string $label
    ): string
    {
        $value =
            trim(
                $value
            );


        if ($value === '') {
            throw new InvalidArgumentException(
                $label
                .
                ' is required.'
            );
        }


        return $value;
    }


    /**
     * @param array<int,mixed> $names
     *
     * @return array<int,string>
     */
    private function attachmentNames(
        array $names
    ): array
    {
        $normalized = [];


        foreach ($names as $name) {

            if (!is_string($name)) {
                throw new InvalidArgumentException(
                    'Every attachment name must be a string.'
                );
            }


            $name =
                trim(
                    $name
                );


            if ($name === '') {
                throw new InvalidArgumentException(
                    'Attachment names cannot be empty.'
                );
            }


            $name =
                basename(
                    str_replace(
                        [
                            "\0",
                            "\r",
                            "\n"
                        ],
                        '',
                        $name
                    )
                );


            if ($name === '') {
                throw new InvalidArgumentException(
                    'Attachment names cannot be empty.'
                );
            }


            $normalized[] =
                $name;
        }


        return $normalized;
    }
}
