<?php
declare(strict_types=1);

use App\Services\ReportEmailService;
use PHPUnit\Framework\TestCase;

final class ReportEmailServiceTest extends TestCase
{
    public function testExactPayrollPeriodMetadataIsIncludedInEmailBody(): void
    {
        $body =
            $this->payrollPeriodBody(
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
            $this->payrollPeriodBody(
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
            $this->payrollPeriodBody(
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


    /**
     * @param array<string,mixed> $metadata
     */
    private function payrollPeriodBody(
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
}
