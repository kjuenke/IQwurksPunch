<?php
declare(strict_types=1);

use App\Exceptions\EmailAttachmentSizeExceededException;
use App\Logging\LogHandlerInterface;
use App\Logging\Logger;
use App\Repositories\EmailDeliveryAttemptRepository;
use App\Repositories\EmailRepository;
use App\Repositories\NotificationRecipientRepository;
use App\Services\EmailAttachmentSizePolicyService;
use App\Services\MailService;
use App\Services\NotificationRecipientService;
use PHPUnit\Framework\TestCase;

final class MailServiceAttachmentLimitIntegrationTest extends TestCase
{
    private PDO $database;

    private EmailDeliveryAttemptRepository $deliveryAttempts;


    protected function setUp(): void
    {
        parent::setUp();


        $this->database =
            new PDO(
                'sqlite::memory:'
            );


        $this->database->setAttribute(
            PDO::ATTR_ERRMODE,
            PDO::ERRMODE_EXCEPTION
        );


        $this->database->setAttribute(
            PDO::ATTR_DEFAULT_FETCH_MODE,
            PDO::FETCH_ASSOC
        );


        $this->createSchema();

        $this->seedRecipient();


        $this->deliveryAttempts =
            new EmailDeliveryAttemptRepository(
                $this->database
            );
    }


    public function testOversizedAttachmentIsRecordedAsPermanentFailure(): void
    {
        $service =
            $this->mailService(
                100
            );


        try {

            $service->send(
                'Oversized Payroll Report',
                'This message must never reach SMTP.',
                'daily_payroll',
                [
                    [
                        'filename' =>
                            'oversized-payroll.pdf',

                        'content_type' =>
                            'application/pdf',

                        'contents' =>
                            str_repeat(
                                'A',
                                101
                            )
                    ]
                ],
                'scheduled',
                null,
                1,
                3,
                null
            );


            self::fail(
                'The oversized attachment should have been rejected.'
            );

        } catch (EmailAttachmentSizeExceededException $exception) {

            self::assertSame(
                101,
                $exception->totalBytes()
            );


            self::assertSame(
                100,
                $exception->maxTotalBytes()
            );


            self::assertSame(
                'Email attachments total 101 bytes, exceeding the configured maximum of 100 bytes.',
                $exception->getMessage()
            );
        }


        $attempts =
            $this->deliveryAttempts
                ->recent();


        self::assertCount(
            1,
            $attempts
        );


        $attempt =
            $attempts[0];


        self::assertSame(
            'failed',
            $attempt['status']
        );


        self::assertSame(
            1,
            (int)$attempt['permanent_failure']
        );


        self::assertSame(
            'daily_payroll',
            $attempt['notification_type']
        );


        self::assertSame(
            'scheduled',
            $attempt['source']
        );


        self::assertSame(
            1,
            (int)$attempt['attempt_number']
        );


        self::assertSame(
            3,
            (int)$attempt['max_attempts']
        );


        self::assertSame(
            1,
            (int)$attempt['attachment_count']
        );


        self::assertSame(
            101,
            (int)$attempt['attachment_size_bytes']
        );


        self::assertSame(
            [
                'oversized-payroll.pdf'
            ],
            json_decode(
                (string)$attempt['attachment_names'],
                true
            )
        );


        self::assertNotNull(
            $attempt['completed_at']
        );


        self::assertSame(
            [],
            $this->deliveryAttempts
                ->retryableFailures()
        );


        $emailHistory =
            (
                new EmailRepository(
                    $this->database
                )
            )->all();


        self::assertCount(
            1,
            $emailHistory
        );


        self::assertSame(
            'Oversized Payroll Report',
            $emailHistory[0]['report_type']
        );


        self::assertSame(
            'payroll@example.com',
            $emailHistory[0]['recipients']
        );


        self::assertSame(
            'failed',
            $emailHistory[0]['status']
        );
    }


    private function mailService(
        int $maxTotalBytes
    ): MailService
    {
        $reflection =
            new ReflectionClass(
                MailService::class
            );


        $service =
            $reflection
                ->newInstanceWithoutConstructor();


        $this->setProperty(
            $reflection,
            $service,
            'config',
            [
                /*
                 * This transport is deliberately unusable. Receiving the
                 * attachment-size exception proves the transport path was
                 * never reached.
                 */
                'host' =>
                    '',

                'port' =>
                    587,

                'username' =>
                    '',

                'password' =>
                    '',

                'encryption' =>
                    'tls',

                'from_email' =>
                    'timeclock@example.com',

                'from_name' =>
                    'IQwurksPunch'
            ]
        );


        $this->setProperty(
            $reflection,
            $service,
            'emails',
            new EmailRepository(
                $this->database
            )
        );


        $this->setProperty(
            $reflection,
            $service,
            'deliveryAttempts',
            $this->deliveryAttempts
        );


        $this->setProperty(
            $reflection,
            $service,
            'recipients',
            new NotificationRecipientService(
                new NotificationRecipientRepository(
                    $this->database
                )
            )
        );


        $this->setProperty(
            $reflection,
            $service,
            'attachmentSizePolicy',
            new EmailAttachmentSizePolicyService(
                [
                    'email_attachments' => [
                        'max_total_bytes' =>
                            $maxTotalBytes
                    ]
                ]
            )
        );


        $this->setProperty(
            $reflection,
            $service,
            'logger',
            new Logger(
                new class implements LogHandlerInterface
                {
                    public function write(
                        string $level,
                        string $channel,
                        string $message,
                        array $context = []
                    ): void
                    {
                    }
                },
                'mail-test'
            )
        );


        return $service;
    }


    private function setProperty(
        ReflectionClass $reflection,
        MailService $service,
        string $propertyName,
        mixed $value
    ): void
    {
        $property =
            $reflection->getProperty(
                $propertyName
            );


        $property->setValue(
            $service,
            $value
        );
    }


    private function seedRecipient(): void
    {
        $statement =
            $this->database->prepare(
                "
                INSERT INTO notification_recipients
                (
                    name,
                    email,
                    daily_payroll,
                    weekly_payroll,
                    exception_reports,
                    approval_notifications,
                    operational_failures,
                    active
                )

                VALUES
                (
                    :name,
                    :email,
                    1,
                    0,
                    0,
                    0,
                    0,
                    1
                )
                "
            );


        $statement->execute(
            [
                'name' =>
                    'Payroll Department',

                'email' =>
                    'payroll@example.com'
            ]
        );
    }


    private function createSchema(): void
    {
        $this->database->exec(
            "
            CREATE TABLE email_log
            (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                report_type TEXT NOT NULL,
                recipients TEXT NOT NULL,
                status TEXT NOT NULL,
                sent_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
            "
        );


        $this->database->exec(
            "
            CREATE TABLE notification_recipients
            (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE,
                daily_payroll INTEGER NOT NULL DEFAULT 1,
                weekly_payroll INTEGER NOT NULL DEFAULT 1,
                exception_reports INTEGER NOT NULL DEFAULT 1,
                approval_notifications INTEGER NOT NULL DEFAULT 0,
                operational_failures INTEGER NOT NULL DEFAULT 0,
                active INTEGER NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL
                    DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL
                    DEFAULT CURRENT_TIMESTAMP
            )
            "
        );


        $this->database->exec(
            "
            CREATE TABLE email_delivery_attempts
            (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email_log_id INTEGER DEFAULT NULL,
                schedule_id INTEGER DEFAULT NULL,
                notification_type TEXT NOT NULL,
                source TEXT NOT NULL DEFAULT 'manual',
                subject TEXT NOT NULL,
                recipients TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'pending',
                attempt_number INTEGER NOT NULL DEFAULT 1,
                max_attempts INTEGER NOT NULL DEFAULT 1,
                retry_of_id INTEGER DEFAULT NULL,
                permanent_failure INTEGER NOT NULL DEFAULT 0,
                error_message TEXT DEFAULT NULL,
                attachment_count INTEGER NOT NULL DEFAULT 0,
                attachment_names TEXT NOT NULL DEFAULT '[]',
                attachment_size_bytes INTEGER NOT NULL DEFAULT 0,
                started_at DATETIME NOT NULL
                    DEFAULT CURRENT_TIMESTAMP,
                completed_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL
                    DEFAULT CURRENT_TIMESTAMP
            )
            "
        );
    }
}
