<?php
declare(strict_types=1);

use App\Repositories\EmailDeliveryAttemptRepository;
use App\Services\EmailDeliveryRetryEligibilityService;
use App\Services\EmailDeliveryRetryPolicyService;
use PHPUnit\Framework\TestCase;

final class EmailDeliveryRetryEligibilityServiceTest extends TestCase
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


    public function testConfiguredBatchAndDelayAreExposed(): void
    {
        $service =
            $this->service();


        self::assertSame(
            10,
            $service->batchLimit()
        );


        self::assertSame(
            5,
            $service->delayMinutes()
        );
    }


    public function testFailureAtConfiguredDelayIsEligible(): void
    {
        $attemptId =
            $this->createFailedAttempt(
                '2026-07-30 15:55:00'
            );


        $failures =
            $this->service()
                ->eligibleFailures(
                    null,
                    new \DateTimeImmutable(
                        '2026-07-30 16:00:00',
                        new \DateTimeZone(
                            'UTC'
                        )
                    )
                );


        self::assertCount(
            1,
            $failures
        );


        self::assertSame(
            $attemptId,
            (int)$failures[0]['id']
        );
    }


    public function testRecentFailureIsNotYetEligible(): void
    {
        $this->createFailedAttempt(
            '2026-07-30 15:56:00'
        );


        $failures =
            $this->service()
                ->eligibleFailures(
                    null,
                    new \DateTimeImmutable(
                        '2026-07-30 16:00:00',
                        new \DateTimeZone(
                            'UTC'
                        )
                    )
                );


        self::assertSame(
            [],
            $failures
        );
    }


    public function testDefaultBatchLimitIsApplied(): void
    {
        for ($index = 0; $index < 12; $index++) {

            $this->createFailedAttempt(
                sprintf(
                    '2026-07-30 14:%02d:00',
                    $index
                )
            );
        }


        $failures =
            $this->service()
                ->eligibleFailures(
                    null,
                    new \DateTimeImmutable(
                        '2026-07-30 16:00:00',
                        new \DateTimeZone(
                            'UTC'
                        )
                    )
                );


        self::assertCount(
            10,
            $failures
        );
    }


    public function testExplicitSmallerLimitIsSupported(): void
    {
        $this->createFailedAttempt(
            '2026-07-30 14:00:00'
        );


        $this->createFailedAttempt(
            '2026-07-30 14:01:00'
        );


        $failures =
            $this->service()
                ->eligibleFailures(
                    1,
                    new \DateTimeImmutable(
                        '2026-07-30 16:00:00',
                        new \DateTimeZone(
                            'UTC'
                        )
                    )
                );


        self::assertCount(
            1,
            $failures
        );
    }


    public function testMalformedCompletionTimestampIsExcluded(): void
    {
        $attemptId =
            $this->createFailedAttempt(
                '2026-07-30 14:00:00'
            );


        $statement =
            $this->database->prepare(
                "
                UPDATE email_delivery_attempts

                SET completed_at = :completed_at

                WHERE id = :id
                "
            );


        $statement->execute(
            [
                'completed_at' =>
                    'not-a-timestamp',

                'id' =>
                    $attemptId
            ]
        );


        $failures =
            $this->service()
                ->eligibleFailures(
                    null,
                    new \DateTimeImmutable(
                        '2026-07-30 16:00:00',
                        new \DateTimeZone(
                            'UTC'
                        )
                    )
                );


        self::assertSame(
            [],
            $failures
        );
    }


    public function testInvalidLimitIsRejected(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );


        $this->expectExceptionMessage(
            'Retry eligibility limit must be between 1 and 250.'
        );


        $this->service()
            ->eligibleFailures(
                0
            );
    }


    private function service(): EmailDeliveryRetryEligibilityService
    {
        return
            new EmailDeliveryRetryEligibilityService(
                $this->repository,
                new EmailDeliveryRetryPolicyService(
                    [
                        'delivery_retry' => [
                            'scheduled_max_attempts' =>
                                3,

                            'batch_limit' =>
                                10,

                            'delay_minutes' =>
                                5
                        ]
                    ]
                )
            );
    }


    private function createFailedAttempt(
        string $completedAt
    ): int
    {
        $attemptId =
            $this->repository
                ->createPending(
                    'daily_payroll',
                    'scheduled',
                    'Daily Payroll Report',
                    'payroll@example.com',
                    [
                        'iqwurkspunch-daily-payroll-2026-07-30.csv',
                        'iqwurkspunch-daily-payroll-2026-07-30.pdf'
                    ],
                    25000,
                    null,
                    1,
                    1,
                    3
                );


        self::assertTrue(
            $this->repository
                ->markFailed(
                    $attemptId,
                    'Synthetic SMTP failure.',
                    false
                )
        );


        $statement =
            $this->database->prepare(
                "
                UPDATE email_delivery_attempts

                SET completed_at = :completed_at

                WHERE id = :id
                "
            );


        $statement->execute(
            [
                'completed_at' =>
                    $completedAt,

                'id' =>
                    $attemptId
            ]
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
