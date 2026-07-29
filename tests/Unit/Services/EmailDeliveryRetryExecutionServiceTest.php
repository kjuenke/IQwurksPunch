<?php
declare(strict_types=1);

use App\Services\EmailDeliveryRetryExecutionService;
use App\Services\EmailDeliveryRetryPlanService;
use PHPUnit\Framework\TestCase;

final class EmailDeliveryRetryExecutionServiceTest extends TestCase
{
    public function testDailyRetryPreservesDateAndAttemptMetadata(): void
    {
        $dailyCalls = [];

        $weeklyCalled = false;

        $exceptionCalled = false;


        $service =
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
                    &$dailyCalls
                ): bool {
                    $dailyCalls[] = [
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

                        'report_date' =>
                            $reportDate
                    ];


                    return true;
                },

                static function (
                    mixed ...$arguments
                ) use (
                    &$weeklyCalled
                ): bool {
                    $weeklyCalled = true;


                    return true;
                },

                static function (
                    mixed ...$arguments
                ) use (
                    &$exceptionCalled
                ): bool {
                    $exceptionCalled = true;


                    return true;
                }
            );


        $sent =
            $service->retry(
                $this->failedAttempt(
                    [
                        'id' =>
                            41,

                        'schedule_id' =>
                            1,

                        'notification_type' =>
                            'daily_payroll',

                        'attempt_number' =>
                            1,

                        'max_attempts' =>
                            3,

                        'attachment_names' =>
                            json_encode(
                                [
                                    'iqwurkspunch-daily-payroll-2026-07-28.csv',
                                    'iqwurkspunch-daily-payroll-2026-07-28.pdf'
                                ]
                            )
                    ]
                )
            );


        self::assertTrue(
            $sent
        );


        self::assertFalse(
            $weeklyCalled
        );


        self::assertFalse(
            $exceptionCalled
        );


        self::assertCount(
            1,
            $dailyCalls
        );


        self::assertSame(
            [
                'source' =>
                    'retry',

                'schedule_id' =>
                    1,

                'attempt_number' =>
                    2,

                'max_attempts' =>
                    3,

                'retry_of_id' =>
                    41,

                'report_date' =>
                    '2026-07-28'
            ],
            $dailyCalls[0]
        );
    }


    public function testWeeklyRetryPreservesOriginalWeekAndMetadata(): void
    {
        $weeklyCalls = [];

        $dailyCalled = false;

        $exceptionCalled = false;


        $service =
            new EmailDeliveryRetryExecutionService(
                new EmailDeliveryRetryPlanService(),

                static function (
                    mixed ...$arguments
                ) use (
                    &$dailyCalled
                ): bool {
                    $dailyCalled = true;


                    return true;
                },

                static function (
                    string $referenceDate,
                    string $source,
                    ?int $scheduleId,
                    int $attemptNumber,
                    int $maxAttempts,
                    int $retryOfId
                ) use (
                    &$weeklyCalls
                ): bool {
                    $weeklyCalls[] = [
                        'reference_date' =>
                            $referenceDate,

                        'source' =>
                            $source,

                        'schedule_id' =>
                            $scheduleId,

                        'attempt_number' =>
                            $attemptNumber,

                        'max_attempts' =>
                            $maxAttempts,

                        'retry_of_id' =>
                            $retryOfId
                    ];


                    return true;
                },

                static function (
                    mixed ...$arguments
                ) use (
                    &$exceptionCalled
                ): bool {
                    $exceptionCalled = true;


                    return true;
                }
            );


        $sent =
            $service->retry(
                $this->failedAttempt(
                    [
                        'id' =>
                            52,

                        'schedule_id' =>
                            2,

                        'notification_type' =>
                            'weekly_payroll',

                        'attempt_number' =>
                            2,

                        'max_attempts' =>
                            4,

                        'attachment_names' =>
                            json_encode(
                                [
                                    'iqwurkspunch-weekly-payroll-2026-07-20-to-2026-07-26.pdf'
                                ]
                            )
                    ]
                )
            );


        self::assertTrue(
            $sent
        );


        self::assertFalse(
            $dailyCalled
        );


        self::assertFalse(
            $exceptionCalled
        );


        self::assertCount(
            1,
            $weeklyCalls
        );


        self::assertSame(
            [
                'reference_date' =>
                    '2026-07-20',

                'source' =>
                    'retry',

                'schedule_id' =>
                    2,

                'attempt_number' =>
                    3,

                'max_attempts' =>
                    4,

                'retry_of_id' =>
                    52
            ],
            $weeklyCalls[0]
        );
    }


    public function testExceptionRetryPreservesAttemptMetadata(): void
    {
        $exceptionCalls = [];

        $dailyCalled = false;

        $weeklyCalled = false;


        $service =
            new EmailDeliveryRetryExecutionService(
                new EmailDeliveryRetryPlanService(),

                static function (
                    mixed ...$arguments
                ) use (
                    &$dailyCalled
                ): bool {
                    $dailyCalled = true;


                    return true;
                },

                static function (
                    mixed ...$arguments
                ) use (
                    &$weeklyCalled
                ): bool {
                    $weeklyCalled = true;


                    return true;
                },

                static function (
                    string $source,
                    ?int $scheduleId,
                    int $attemptNumber,
                    int $maxAttempts,
                    int $retryOfId
                ) use (
                    &$exceptionCalls
                ): bool {
                    $exceptionCalls[] = [
                        'source' =>
                            $source,

                        'schedule_id' =>
                            $scheduleId,

                        'attempt_number' =>
                            $attemptNumber,

                        'max_attempts' =>
                            $maxAttempts,

                        'retry_of_id' =>
                            $retryOfId
                    ];


                    return true;
                }
            );


        $sent =
            $service->retry(
                $this->failedAttempt(
                    [
                        'id' =>
                            63,

                        'schedule_id' =>
                            3,

                        'notification_type' =>
                            'exception_reports',

                        'attempt_number' =>
                            1,

                        'max_attempts' =>
                            2,

                        'attachment_names' =>
                            '[]'
                    ]
                )
            );


        self::assertTrue(
            $sent
        );


        self::assertFalse(
            $dailyCalled
        );


        self::assertFalse(
            $weeklyCalled
        );


        self::assertCount(
            1,
            $exceptionCalls
        );


        self::assertSame(
            [
                'source' =>
                    'retry',

                'schedule_id' =>
                    3,

                'attempt_number' =>
                    2,

                'max_attempts' =>
                    2,

                'retry_of_id' =>
                    63
            ],
            $exceptionCalls[0]
        );
    }


    public function testRetryReturnsFalseWhenReportDeliveryReturnsFalse(): void
    {
        $service =
            new EmailDeliveryRetryExecutionService(
                new EmailDeliveryRetryPlanService(),

                static fn (
                    string $source,
                    ?int $scheduleId,
                    int $attemptNumber,
                    int $maxAttempts,
                    int $retryOfId,
                    string $reportDate
                ): bool =>
                    false,

                static fn (
                    mixed ...$arguments
                ): bool =>
                    true,

                static fn (
                    mixed ...$arguments
                ): bool =>
                    true
            );


        self::assertFalse(
            $service->retry(
                $this->failedAttempt()
            )
        );
    }


    public function testRetryDoesNotHideReportDeliveryException(): void
    {
        $service =
            new EmailDeliveryRetryExecutionService(
                new EmailDeliveryRetryPlanService(),

                static function (
                    string $source,
                    ?int $scheduleId,
                    int $attemptNumber,
                    int $maxAttempts,
                    int $retryOfId,
                    string $reportDate
                ): bool {
                    throw new RuntimeException(
                        'Synthetic retry delivery failure.'
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


        $this->expectException(
            RuntimeException::class
        );


        $this->expectExceptionMessage(
            'Synthetic retry delivery failure.'
        );


        $service->retry(
            $this->failedAttempt()
        );
    }


    /**
     * @param array<string,mixed> $overrides
     *
     * @return array<string,mixed>
     */
    private function failedAttempt(
        array $overrides = []
    ): array
    {
        return
            array_merge(
                [
                    'id' =>
                        1,

                    'schedule_id' =>
                        null,

                    'notification_type' =>
                        'daily_payroll',

                    'status' =>
                        'failed',

                    'attempt_number' =>
                        1,

                    'max_attempts' =>
                        3,

                    'permanent_failure' =>
                        0,

                    'attachment_names' =>
                        json_encode(
                            [
                                'iqwurkspunch-daily-payroll-2026-07-29.csv'
                            ]
                        )
                ],
                $overrides
            );
    }
}
