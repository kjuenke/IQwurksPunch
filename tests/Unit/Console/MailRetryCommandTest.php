<?php
declare(strict_types=1);

use App\Console\Commands\MailRetryCommand;
use App\Repositories\EmailDeliveryAttemptRepository;
use App\Services\EmailDeliveryRetryExecutionService;
use App\Services\EmailDeliveryRetryPlanService;
use PHPUnit\Framework\TestCase;

final class MailRetryCommandTest extends TestCase
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


    public function testCommandIdentity(): void
    {
        $callCount = 0;


        $command =
            $this->command(
                $callCount
            );


        self::assertSame(
            'mail:retry',
            $command->name()
        );


        self::assertSame(
            'Preview or retry failed payroll-report email deliveries.',
            $command->description()
        );
    }


    public function testPreviewDoesNotExecuteRetry(): void
    {
        $failedAttemptId =
            $this->createFailedDailyAttempt();


        $callCount = 0;


        $command =
            $this->command(
                $callCount
            );


        ob_start();


        $exitCode =
            $command->execute();


        $output =
            (string)ob_get_clean();


        self::assertSame(
            0,
            $exitCode
        );


        self::assertSame(
            0,
            $callCount
        );


        self::assertStringContainsString(
            'Mode: PREVIEW',
            $output
        );


        self::assertStringContainsString(
            'Preview only. No email deliveries were retried.',
            $output
        );


        $retryable =
            $this->repository
                ->retryableFailures();


        self::assertCount(
            1,
            $retryable
        );


        self::assertSame(
            $failedAttemptId,
            (int)$retryable[0]['id']
        );
    }


    public function testSendExecutesRetryOnlyOnce(): void
    {
        $failedAttemptId =
            $this->createFailedDailyAttempt();


        $callCount = 0;


        $command =
            $this->command(
                $callCount
            );


        ob_start();


        $firstExitCode =
            $command->execute(
                [
                    '--send'
                ]
            );


        $firstOutput =
            (string)ob_get_clean();


        self::assertSame(
            0,
            $firstExitCode
        );


        self::assertSame(
            1,
            $callCount
        );


        self::assertStringContainsString(
            'Mode: SEND',
            $firstOutput
        );


        self::assertStringContainsString(
            'was retried successfully',
            $firstOutput
        );


        $attempts =
            $this->repository
                ->recent();


        self::assertCount(
            2,
            $attempts
        );


        self::assertSame(
            'retry',
            $attempts[0]['source']
        );


        self::assertSame(
            'sent',
            $attempts[0]['status']
        );


        self::assertSame(
            $failedAttemptId,
            (int)$attempts[0]['retry_of_id']
        );


        ob_start();


        $secondExitCode =
            $command->execute(
                [
                    '--send'
                ]
            );


        $secondOutput =
            (string)ob_get_clean();


        self::assertSame(
            0,
            $secondExitCode
        );


        self::assertSame(
            1,
            $callCount
        );


        self::assertStringContainsString(
            'No failed payroll-report deliveries are eligible for retry.',
            $secondOutput
        );
    }


    public function testSendHonorsLimit(): void
    {
        $this->createFailedDailyAttempt(
            '2026-07-28'
        );


        $this->createFailedDailyAttempt(
            '2026-07-29'
        );


        $callCount = 0;


        $command =
            $this->command(
                $callCount
            );


        ob_start();


        $exitCode =
            $command->execute(
                [
                    '--send',
                    '--limit=1'
                ]
            );


        ob_end_clean();


        self::assertSame(
            0,
            $exitCode
        );


        self::assertSame(
            1,
            $callCount
        );


        self::assertCount(
            1,
            $this->repository
                ->retryableFailures()
        );
    }


    public function testInvalidArgumentReturnsFailureWithoutRetrying(): void
    {
        $this->createFailedDailyAttempt();


        $callCount = 0;


        $command =
            $this->command(
                $callCount
            );


        $exitCode =
            $command->execute(
                [
                    '--unknown'
                ]
            );


        self::assertSame(
            1,
            $exitCode
        );


        self::assertSame(
            0,
            $callCount
        );


        self::assertCount(
            1,
            $this->repository
                ->retryableFailures()
        );
    }


    private function command(
        int &$callCount
    ): MailRetryCommand
    {
        $repository =
            $this->repository;


        $execution =
            new EmailDeliveryRetryExecutionService(
                new EmailDeliveryRetryPlanService(),

                static function (
                    string $source,
                    ?int $scheduleId,
                    int $attemptNumber,
                    int $maxAttempts,
                    int $retryOfId,
                    string $reportDate
                ) use (
                    &$callCount,
                    $repository
                ): bool {
                    $callCount++;


                    $retryAttemptId =
                        $repository
                            ->createPending(
                                'daily_payroll',
                                $source,
                                'Daily Payroll Retry',
                                'payroll@example.com',
                                [
                                    'iqwurkspunch-daily-payroll-'
                                    .
                                    $reportDate
                                    .
                                    '.csv'
                                ],
                                100,
                                null,
                                $scheduleId,
                                $attemptNumber,
                                $maxAttempts,
                                $retryOfId
                            );


                    return
                        $repository
                            ->markSent(
                                $retryAttemptId
                            );
                },

                static fn (
                    mixed ...$arguments
                ): bool =>
                    true,

                static fn (
                    mixed ...$arguments
                ): bool =>
                    true
            );


        return
            new MailRetryCommand(
                $this->repository,
                $execution
            );
    }


    private function createFailedDailyAttempt(
        string $reportDate = '2026-07-29'
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
                        'iqwurkspunch-daily-payroll-'
                        .
                        $reportDate
                        .
                        '.csv',

                        'iqwurkspunch-daily-payroll-'
                        .
                        $reportDate
                        .
                        '.pdf'
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
