<?php
declare(strict_types=1);

use App\Services\ReportEmailService;
use App\Services\WeeklyPayrollEmailService;
use PHPUnit\Framework\TestCase;

final class ReportEmailServiceTest extends TestCase
{
    public function testExactPayrollPeriodMetadataIsIncludedInEmailBody(): void
    {
        $body =
            $this->dailyPayrollPeriodBody(
                [
                    'association_status' =>
                        'exact',

                    'association_message' =>
                        'This report exactly matches a payroll period.',

                    'payroll_period' => [
                        'id' =>
                            42,

                        'period_name' =>
                            'July 2026 Payroll',

                        'start_date' =>
                            '2026-07-20',

                        'end_date' =>
                            '2026-07-31',

                        'status' =>
                            'locked',

                        'created_at' =>
                            '2026-07-20 07:00:00',

                        'created_by_username' =>
                            'period-creator',

                        'review_started_at' =>
                            '2026-08-01 08:00:00',

                        'reviewed_by_username' =>
                            'review-supervisor',

                        'approved_at' =>
                            '2026-08-01 09:00:00',

                        'approved_by_username' =>
                            'approval-admin',

                        'locked_at' =>
                            '2026-08-01 10:00:00',

                        'locked_by_username' =>
                            'locking-supervisor',

                        'open_exception_count' =>
                            0,

                        'total_exception_count' =>
                            3,

                        'approval_blocked' =>
                            false
                    ]
                ]
            );


        self::assertStringContainsString(
            'Payroll-Period Association',
            $body
        );


        self::assertStringContainsString(
            'Association: Exact Match',
            $body
        );


        self::assertStringContainsString(
            'Payroll Period ID: 42',
            $body
        );


        self::assertStringContainsString(
            'Payroll Period Name: July 2026 Payroll',
            $body
        );


        self::assertStringContainsString(
            'Payroll Period Range: 2026-07-20 through 2026-07-31',
            $body
        );


        self::assertStringContainsString(
            'Workflow Status: Locked',
            $body
        );


        self::assertStringContainsString(
            'Open Payroll Exceptions: 0',
            $body
        );


        self::assertStringContainsString(
            'Total Payroll Exceptions: 3',
            $body
        );


        self::assertStringContainsString(
            'Approval Blocked: No',
            $body
        );


        self::assertStringContainsString(
            'Created: 2026-07-20 07:00:00 by period-creator',
            $body
        );


        self::assertStringContainsString(
            'Review Started: 2026-08-01 08:00:00 by review-supervisor',
            $body
        );


        self::assertStringContainsString(
            'Approved: 2026-08-01 09:00:00 by approval-admin',
            $body
        );


        self::assertStringContainsString(
            'Locked: 2026-08-01 10:00:00 by locking-supervisor',
            $body
        );


        self::assertStringContainsString(
            'Punch Protection:',
            $body
        );
    }


    public function testPartialOverlapMetadataIsIncludedWithoutWorkflowClaims(): void
    {
        $body =
            $this->dailyPayrollPeriodBody(
                [
                    'association_status' =>
                        'partial_overlap',

                    'association_message' =>
                        'This daily report overlaps July 2026 Payroll.',

                    'payroll_period' =>
                        null
                ]
            );


        self::assertStringContainsString(
            'Payroll-Period Association',
            $body
        );


        self::assertStringContainsString(
            'Association: Partial Overlap',
            $body
        );


        self::assertStringContainsString(
            'Association Note: This daily report overlaps July 2026 Payroll.',
            $body
        );


        self::assertStringContainsString(
            'does not inherit payroll review, approval, lock, or exception-resolution status',
            $body
        );


        self::assertStringNotContainsString(
            'Workflow Status:',
            $body
        );
    }


    public function testNoAssociationMetadataIsIncludedWithoutWorkflowClaims(): void
    {
        $body =
            $this->dailyPayrollPeriodBody(
                [
                    'association_status' =>
                        'none',

                    'association_message' =>
                        'This daily report is not associated with a payroll period.',

                    'payroll_period' =>
                        null
                ]
            );


        self::assertStringContainsString(
            'Payroll-Period Association',
            $body
        );


        self::assertStringContainsString(
            'Association: None',
            $body
        );


        self::assertStringContainsString(
            'Association Note: This daily report is not associated with a payroll period.',
            $body
        );


        self::assertStringContainsString(
            'does not carry payroll review, approval, lock, or exception-resolution status',
            $body
        );


        self::assertStringNotContainsString(
            'Workflow Status:',
            $body
        );
    }


    public function testWeeklyReferenceDateAcceptsValidIsoDate(): void
    {
        $date =
            $this->weeklyReferenceDate(
                '2026-07-23'
            );


        self::assertSame(
            '2026-07-23',
            $date
        );
    }


    public function testWeeklyReferenceDateRejectsImpossibleDate(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );


        $this->weeklyReferenceDate(
            '2026-02-30'
        );
    }


    public function testWeeklyExactPayrollPeriodMetadataIsIncludedInEmailBody(): void
    {
        $body =
            $this->weeklyPayrollPeriodBody(
                [
                    'association_status' =>
                        'exact',

                    'association_message' =>
                        'This weekly report exactly matches a payroll period.',

                    'payroll_period' => [
                        'id' =>
                            77,

                        'period_name' =>
                            'Weekly Payroll',

                        'start_date' =>
                            '2026-07-20',

                        'end_date' =>
                            '2026-07-26',

                        'status' =>
                            'approved',

                        'created_at' =>
                            '2026-07-20 07:00:00',

                        'created_by_username' =>
                            'period-creator',

                        'review_started_at' =>
                            '2026-07-27 08:00:00',

                        'reviewed_by_username' =>
                            'review-supervisor',

                        'approved_at' =>
                            '2026-07-27 09:00:00',

                        'approved_by_username' =>
                            'approval-admin',

                        'locked_at' =>
                            null,

                        'locked_by_username' =>
                            null,

                        'open_exception_count' =>
                            0,

                        'total_exception_count' =>
                            2,

                        'approval_blocked' =>
                            false
                    ]
                ]
            );


        self::assertStringContainsString(
            'Payroll-Period Association',
            $body
        );


        self::assertStringContainsString(
            'Association: Exact Match',
            $body
        );


        self::assertStringContainsString(
            'Payroll Period ID: 77',
            $body
        );


        self::assertStringContainsString(
            'Payroll Period Range: 2026-07-20 through 2026-07-26',
            $body
        );


        self::assertStringContainsString(
            'Workflow Status: Approved',
            $body
        );


        self::assertStringContainsString(
            'Open Payroll Exceptions: 0',
            $body
        );


        self::assertStringContainsString(
            'Punch Protection:',
            $body
        );
    }


    public function testWeeklyPartialOverlapDoesNotClaimWorkflowState(): void
    {
        $body =
            $this->weeklyPayrollPeriodBody(
                [
                    'association_status' =>
                        'partial_overlap',

                    'association_message' =>
                        'This weekly report overlaps a longer payroll period.',

                    'payroll_period' =>
                        null
                ]
            );


        self::assertStringContainsString(
            'Association: Partial Overlap',
            $body
        );


        self::assertStringContainsString(
            'This weekly report does not inherit payroll review, approval, lock, or exception-resolution status',
            $body
        );


        self::assertStringNotContainsString(
            'Workflow Status:',
            $body
        );
    }


    /**
     * @param array<string,mixed> $metadata
     */
    private function dailyPayrollPeriodBody(
        array $metadata
    ): string
    {
        $reflection =
            new ReflectionClass(
                ReportEmailService::class
            );


        $service =
            $reflection
                ->newInstanceWithoutConstructor();


        $method =
            $reflection
                ->getMethod(
                    'payrollPeriodBody'
                );


        return
            (string)$method->invoke(
                $service,
                $metadata
            );
    }


    /**
     * @param array<string,mixed> $metadata
     */
    private function weeklyPayrollPeriodBody(
        array $metadata
    ): string
    {
        $reflection =
            new ReflectionClass(
                WeeklyPayrollEmailService::class
            );


        $service =
            $reflection
                ->newInstanceWithoutConstructor();


        $method =
            $reflection
                ->getMethod(
                    'payrollPeriodBody'
                );


        return
            (string)$method->invoke(
                $service,
                $metadata
            );
    }


    private function weeklyReferenceDate(
        ?string $date
    ): string
    {
        $reflection =
            new ReflectionClass(
                WeeklyPayrollEmailService::class
            );


        $service =
            $reflection
                ->newInstanceWithoutConstructor();


        $method =
            $reflection
                ->getMethod(
                    'validReferenceDate'
                );


        return
            (string)$method->invoke(
                $service,
                $date,
                new DateTimeZone(
                    'America/Los_Angeles'
                )
            );
    }
}
