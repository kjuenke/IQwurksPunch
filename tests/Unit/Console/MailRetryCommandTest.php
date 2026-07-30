<?php
declare(strict_types=1);

use App\Console\Commands\MailRetryCommand;
use App\Repositories\EmailDeliveryAttemptRepository;
use App\Repositories\EmailDeliveryRetryQuarantineRepository;
use App\Services\EmailDeliveryRetryEligibilityService;
use App\Services\EmailDeliveryRetryExecutionService;
use App\Services\EmailDeliveryRetryPlanService;
use App\Services\EmailDeliveryRetryPolicyService;
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


    public function testConfiguredDefaultsAreDisplayed(): void
    {
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


        self::assertStringContainsString(
            'Selection limit: 10',
            $output
        );


        self::assertStringContainsString(
            'Retry delay: 5 minutes',
            $output
        );


        self::assertStringContainsString(
            'Mode: PREVIEW',
            $output
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
            'Retryable payroll-report deliveries: 1',
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


    public function testPreviewDisplaysUnsupportedNotificationWithoutQuarantining(): void
    {
        $failedAttemptId =
            $this->createFailedUnsupportedAttempt();


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
            'Retryable payroll-report deliveries: 1',
            $output
        );


        self::assertStringContainsString(
            'approval_notifications',
            $output
        );


        self::assertStringContainsString(
            'Payroll Approval Notification',
            $output
        );


        $attempt =
            $this->repository
                ->find(
                    $failedAttemptId
                );


        self::assertNotNull(
            $attempt
        );


        self::assertSame(
            'failed',
            $attempt['status']
        );


        self::assertSame(
            0,
            (int)$attempt['permanent_failure']
        );


        self::assertSame(
            'Synthetic unsupported-notification failure.',
            $attempt['error_message']
        );


        self::assertCount(
            1,
            $this->repository
                ->retryableFailures()
        );
    }


    public function testRecentFailureIsExcludedByDelayPolicy(): void
    {
        $this->createFailedDailyAttempt(
            '2026-07-29',
            '-1 minute'
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
                    '--send'
                ]
            );


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
            'Retryable payroll-report deliveries: 0',
            $output
        );


        self::assertStringContainsString(
            'No failed payroll-report deliveries have satisfied the retry policy.',
            $output
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
            'No failed payroll-report deliveries have satisfied the retry policy.',
            $secondOutput
        );
    }


    public function testSendQuarantinesUnsupportedNotification(): void
    {
        $failedAttemptId =
            $this->createFailedUnsupportedAttempt();


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
            1,
            $firstExitCode
        );


        self::assertSame(
            0,
            $callCount
        );


        self::assertStringContainsString(
            'Retryable payroll-report deliveries: 1',
            $firstOutput
        );


        self::assertStringContainsString(
            'approval_notifications',
            $firstOutput
        );


        self::assertStringContainsString(
            'Failed retries: 1',
            $firstOutput
        );


        $attempt =
            $this->repository
                ->find(
                    $failedAttemptId
                );


        self::assertNotNull(
            $attempt
        );


        self::assertSame(
            'failed',
            $attempt['status']
        );


        self::assertSame(
            1,
            (int)$attempt['permanent_failure']
        );


        self::assertSame(
            'Retry metadata is invalid: This email notification type cannot be regenerated safely.',
            $attempt['error_message']
        );


        self::assertSame(
            [],
            $this->repository
                ->retryableFailures()
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
            0,
            $callCount
        );


        self::assertStringContainsString(
            'Retryable payroll-report deliveries: 0',
            $secondOutput
        );


        self::assertStringContainsString(
            'No failed payroll-report deliveries have satisfied the retry policy.',
            $secondOutput
        );
    }


    public function testSendHonorsExplicitLimit(): void
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


        $policy =
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
            );


        $eligibility =
            new EmailDeliveryRetryEligibilityService(
                $repository,
                $policy
            );


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
                    true,

                new EmailDeliveryRetryQuarantineRepository(
                    $this->database
                )
            );


        return
            new MailRetryCommand(
                $eligibility,
                $execution
            );
    }


    private function createFailedDailyAttempt(
        string $reportDate = '2026-07-29',
        string $completedModifier = '-10 minutes'
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


        $this->setCompletedAge(
            $attemptId,
            $completedModifier
        );


        return $attemptId;
    }


    private function createFailedUnsupportedAttempt(
        string $completedModifier = '-10 minutes'
    ): int
    {
        $attemptId =
            $this->repository
                ->createPending(
                    'approval_notifications',
                    'system',
                    'Payroll Approval Notification',
                    'payroll@example.com',
                    [],
                    0,
                    null,
                    null,
                    1,
                    3
                );


        self::assertTrue(
            $this->repository
                ->markFailed(
                    $attemptId,
                    'Synthetic unsupported-notification failure.',
                    false
                )
        );


        $this->setCompletedAge(
            $attemptId,
            $completedModifier
        );


        return $attemptId;
    }


    private function setCompletedAge(
        int $attemptId,
        string $completedModifier
    ): void
    {
        $statement =
            $this->database->prepare(
                "
                UPDATE email_delivery_attempts

                SET completed_at = datetime(
                    'now',
                    :completed_modifier
                )

                WHERE id = :id
                "
            );


        $statement->execute(
            [
                'completed_modifier' =>
                    $completedModifier,

                'id' =>
                    $attemptId
            ]
        );
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
