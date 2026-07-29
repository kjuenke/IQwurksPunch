<?php
declare(strict_types=1);

use App\Repositories\EmailDeliveryAttemptRepository;
use PHPUnit\Framework\TestCase;

final class EmailDeliveryAttemptRepositoryTest extends TestCase
{
    private \PDO $database;

    private EmailDeliveryAttemptRepository $repository;


    protected function setUp(): void
    {
        parent::setUp();


        $this->database =
            new \PDO(
                'sqlite::memory:'
            );


        $this->database->setAttribute(
            \PDO::ATTR_ERRMODE,
            \PDO::ERRMODE_EXCEPTION
        );


        $this->database->setAttribute(
            \PDO::ATTR_DEFAULT_FETCH_MODE,
            \PDO::FETCH_ASSOC
        );


        $this->createSchema();


        $this->repository =
            new EmailDeliveryAttemptRepository(
                $this->database
            );
    }


    public function testCreatePendingRecordsDeliveryMetadata(): void
    {
        $attemptId =
            $this->repository
                ->createPending(
                    'daily_payroll',
                    'manual',
                    'Daily Payroll Report',
                    'payroll@example.com',
                    [
                        '../daily.csv',
                        "daily.pdf\r\n"
                    ],
                    2450,
                    null,
                    null,
                    1,
                    3
                );


        self::assertGreaterThan(
            0,
            $attemptId
        );


        $attempt =
            $this->repository
                ->find(
                    $attemptId
                );


        self::assertNotNull(
            $attempt
        );


        self::assertSame(
            'daily_payroll',
            $attempt['notification_type']
        );


        self::assertSame(
            'manual',
            $attempt['source']
        );


        self::assertSame(
            'pending',
            $attempt['status']
        );


        self::assertSame(
            2,
            (int)$attempt['attachment_count']
        );


        self::assertSame(
            [
                'daily.csv',
                'daily.pdf'
            ],
            json_decode(
                (string)$attempt['attachment_names'],
                true
            )
        );


        self::assertSame(
            2450,
            (int)$attempt['attachment_size_bytes']
        );


        self::assertSame(
            1,
            (int)$attempt['attempt_number']
        );


        self::assertSame(
            3,
            (int)$attempt['max_attempts']
        );


        self::assertNull(
            $attempt['completed_at']
        );
    }


    public function testMarkSentCompletesPendingAttempt(): void
    {
        $attemptId =
            $this->repository
                ->createPending(
                    'weekly_payroll',
                    'scheduled',
                    'Weekly Payroll Report',
                    'payroll@example.com'
                );


        self::assertTrue(
            $this->repository
                ->markSent(
                    $attemptId,
                    42
                )
        );


        $attempt =
            $this->repository
                ->find(
                    $attemptId
                );


        self::assertSame(
            'sent',
            $attempt['status']
        );


        self::assertSame(
            42,
            (int)$attempt['email_log_id']
        );


        self::assertSame(
            0,
            (int)$attempt['permanent_failure']
        );


        self::assertNull(
            $attempt['error_message']
        );


        self::assertNotNull(
            $attempt['completed_at']
        );


        self::assertFalse(
            $this->repository
                ->markSent(
                    $attemptId
                )
        );
    }


    public function testMarkFailedRecordsErrorAndPermanentStatus(): void
    {
        $attemptId =
            $this->repository
                ->createPending(
                    'exception_reports',
                    'retry',
                    'Payroll Exception Report',
                    'payroll@example.com',
                    [],
                    0,
                    null,
                    5,
                    2,
                    2,
                    10
                );


        self::assertTrue(
            $this->repository
                ->markFailed(
                    $attemptId,
                    ' SMTP connection failed. ',
                    true
                )
        );


        $attempt =
            $this->repository
                ->find(
                    $attemptId
                );


        self::assertSame(
            'failed',
            $attempt['status']
        );


        self::assertSame(
            'SMTP connection failed.',
            $attempt['error_message']
        );


        self::assertSame(
            1,
            (int)$attempt['permanent_failure']
        );


        self::assertSame(
            5,
            (int)$attempt['schedule_id']
        );


        self::assertSame(
            10,
            (int)$attempt['retry_of_id']
        );


        self::assertNotNull(
            $attempt['completed_at']
        );
    }


    public function testRecentReturnsNewestAttemptsFirst(): void
    {
        $first =
            $this->repository
                ->createPending(
                    'daily_payroll',
                    'manual',
                    'First',
                    'one@example.com'
                );


        $second =
            $this->repository
                ->createPending(
                    'weekly_payroll',
                    'scheduled',
                    'Second',
                    'two@example.com'
                );


        $attempts =
            $this->repository
                ->recent(
                    1
                );


        self::assertCount(
            1,
            $attempts
        );


        self::assertSame(
            $second,
            (int)$attempts[0]['id']
        );


        self::assertNotSame(
            $first,
            (int)$attempts[0]['id']
        );
    }


    public function testRetryableFailuresReturnsEligibleFailure(): void
    {
        $attemptId =
            $this->failedAttempt(
                'daily_payroll',
                1,
                3,
                false
            );


        $failures =
            $this->repository
                ->retryableFailures();


        self::assertCount(
            1,
            $failures
        );


        self::assertSame(
            $attemptId,
            (int)$failures[0]['id']
        );
    }


    public function testRetryableFailuresExcludesPermanentAndExhaustedFailures(): void
    {
        $this->failedAttempt(
            'daily_payroll',
            1,
            3,
            true
        );


        $this->failedAttempt(
            'weekly_payroll',
            3,
            3,
            false
        );


        self::assertSame(
            [],
            $this->repository
                ->retryableFailures()
        );
    }


    public function testRetryableFailuresExcludesFailureWithExistingChildRetry(): void
    {
        $originalId =
            $this->failedAttempt(
                'daily_payroll',
                1,
                3,
                false
            );


        $retryId =
            $this->repository
                ->createPending(
                    'daily_payroll',
                    'retry',
                    'Daily Payroll Retry',
                    'payroll@example.com',
                    [],
                    0,
                    null,
                    1,
                    2,
                    3,
                    $originalId
                );


        self::assertGreaterThan(
            $originalId,
            $retryId
        );


        self::assertSame(
            [],
            $this->repository
                ->retryableFailures()
        );
    }


    public function testRetryableFailuresReturnsFailedChildInsteadOfParent(): void
    {
        $originalId =
            $this->failedAttempt(
                'daily_payroll',
                1,
                3,
                false
            );


        $retryId =
            $this->repository
                ->createPending(
                    'daily_payroll',
                    'retry',
                    'Daily Payroll Retry',
                    'payroll@example.com',
                    [],
                    0,
                    null,
                    1,
                    2,
                    3,
                    $originalId
                );


        self::assertTrue(
            $this->repository
                ->markFailed(
                    $retryId,
                    'Retry failed.',
                    false
                )
        );


        $failures =
            $this->repository
                ->retryableFailures();


        self::assertCount(
            1,
            $failures
        );


        self::assertSame(
            $retryId,
            (int)$failures[0]['id']
        );


        self::assertSame(
            2,
            (int)$failures[0]['attempt_number']
        );
    }


    public function testRetryableFailuresHonorsLimit(): void
    {
        $firstId =
            $this->failedAttempt(
                'daily_payroll',
                1,
                3,
                false
            );


        $this->failedAttempt(
            'weekly_payroll',
            1,
            3,
            false
        );


        $failures =
            $this->repository
                ->retryableFailures(
                    1
                );


        self::assertCount(
            1,
            $failures
        );


        self::assertSame(
            $firstId,
            (int)$failures[0]['id']
        );
    }


    public function testCreatePendingRejectsUnsupportedSource(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );


        $this->repository
            ->createPending(
                'daily_payroll',
                'unknown',
                'Daily Payroll Report',
                'payroll@example.com'
            );
    }


    private function failedAttempt(
        string $notificationType,
        int $attemptNumber,
        int $maxAttempts,
        bool $permanentFailure
    ): int
    {
        $attemptId =
            $this->repository
                ->createPending(
                    $notificationType,
                    'scheduled',
                    'Failed Delivery',
                    'payroll@example.com',
                    [],
                    0,
                    null,
                    1,
                    $attemptNumber,
                    $maxAttempts
                );


        self::assertTrue(
            $this->repository
                ->markFailed(
                    $attemptId,
                    'SMTP delivery failed.',
                    $permanentFailure
                )
        );


        return $attemptId;
    }


    private function createSchema(): void
    {
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
                started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                completed_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
            "
        );
    }
}
