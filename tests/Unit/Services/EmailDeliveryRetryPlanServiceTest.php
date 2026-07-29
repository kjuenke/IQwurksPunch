<?php
declare(strict_types=1);

use App\Services\EmailDeliveryRetryPlanService;
use PHPUnit\Framework\TestCase;

final class EmailDeliveryRetryPlanServiceTest extends TestCase
{
    private EmailDeliveryRetryPlanService $service;


    protected function setUp(): void
    {
        parent::setUp();


        $this->service =
            new EmailDeliveryRetryPlanService();
    }


    public function testDailyPlanPreservesOriginalReportDate(): void
    {
        $plan =
            $this->service
                ->plan(
                    $this->attempt(
                        [
                            'id' =>
                                14,

                            'notification_type' =>
                                'daily_payroll',

                            'schedule_id' =>
                                1,

                            'attempt_number' =>
                                1,

                            'max_attempts' =>
                                3,

                            'attachment_names' =>
                                json_encode(
                                    [
                                        'iqwurkspunch-daily-payroll-2026-07-29.csv',
                                        'iqwurkspunch-daily-payroll-2026-07-29.pdf'
                                    ]
                                )
                        ]
                    )
                );


        self::assertSame(
            14,
            $plan['failed_attempt_id']
        );


        self::assertSame(
            'daily_payroll',
            $plan['notification_type']
        );


        self::assertSame(
            'retry',
            $plan['source']
        );


        self::assertSame(
            1,
            $plan['schedule_id']
        );


        self::assertSame(
            2,
            $plan['attempt_number']
        );


        self::assertSame(
            3,
            $plan['max_attempts']
        );


        self::assertSame(
            14,
            $plan['retry_of_id']
        );


        self::assertSame(
            '2026-07-29',
            $plan['report_date']
        );


        self::assertNull(
            $plan['reference_date']
        );
    }


    public function testWeeklyPlanPreservesOriginalWeek(): void
    {
        $plan =
            $this->service
                ->plan(
                    $this->attempt(
                        [
                            'id' =>
                                22,

                            'notification_type' =>
                                'weekly_payroll',

                            'schedule_id' =>
                                2,

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


        self::assertSame(
            '2026-07-20',
            $plan['reference_date']
        );


        self::assertNull(
            $plan['report_date']
        );


        self::assertSame(
            3,
            $plan['attempt_number']
        );


        self::assertSame(
            22,
            $plan['retry_of_id']
        );
    }


    public function testExceptionReportPlanDoesNotRequireAttachments(): void
    {
        $plan =
            $this->service
                ->plan(
                    $this->attempt(
                        [
                            'id' =>
                                30,

                            'notification_type' =>
                                'exception_reports',

                            'schedule_id' =>
                                3,

                            'attachment_names' =>
                                '[]'
                        ]
                    )
                );


        self::assertSame(
            'exception_reports',
            $plan['notification_type']
        );


        self::assertNull(
            $plan['report_date']
        );


        self::assertNull(
            $plan['reference_date']
        );
    }


    public function testPlanRejectsSuccessfulDelivery(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );


        $this->expectExceptionMessage(
            'Only failed email deliveries can be retried.'
        );


        $this->service
            ->plan(
                $this->attempt(
                    [
                        'status' =>
                            'sent'
                    ]
                )
            );
    }


    public function testPlanRejectsPermanentFailure(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );


        $this->expectExceptionMessage(
            'A permanent email-delivery failure cannot be retried.'
        );


        $this->service
            ->plan(
                $this->attempt(
                    [
                        'permanent_failure' =>
                            1
                    ]
                )
            );
    }


    public function testPlanRejectsExhaustedFailure(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );


        $this->expectExceptionMessage(
            'The email-delivery retry limit has already been reached.'
        );


        $this->service
            ->plan(
                $this->attempt(
                    [
                        'attempt_number' =>
                            3,

                        'max_attempts' =>
                            3
                    ]
                )
            );
    }


    public function testPlanRejectsUnsupportedNotificationType(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );


        $this->expectExceptionMessage(
            'This email notification type cannot be regenerated safely.'
        );


        $this->service
            ->plan(
                $this->attempt(
                    [
                        'notification_type' =>
                            'operational_failures'
                    ]
                )
            );
    }


    public function testDailyPlanRequiresRecoverableDateMetadata(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );


        $this->expectExceptionMessage(
            'The original daily payroll report date could not be determined.'
        );


        $this->service
            ->plan(
                $this->attempt(
                    [
                        'attachment_names' =>
                            '[]'
                    ]
                )
            );
    }


    public function testPlanRejectsMalformedAttachmentMetadata(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );


        $this->expectExceptionMessage(
            'The email attachment metadata is not valid JSON.'
        );


        $this->service
            ->plan(
                $this->attempt(
                    [
                        'attachment_names' =>
                            '{not-json}'
                    ]
                )
            );
    }


    /**
     * @param array<string,mixed> $overrides
     *
     * @return array<string,mixed>
     */
    private function attempt(
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
