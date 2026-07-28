<?php
declare(strict_types=1);

use App\Services\ApprovalNotificationEmailService;
use PHPUnit\Framework\TestCase;

final class ApprovalNotificationEmailServiceTest extends TestCase
{
    private ApprovalNotificationEmailService $service;


    protected function setUp(): void
    {
        parent::setUp();


        $reflection =
            new ReflectionClass(
                ApprovalNotificationEmailService::class
            );


        $this->service =
            $reflection->newInstanceWithoutConstructor();
    }


    public function testSubjectIdentifiesCompanyAndPayrollPeriod(): void
    {
        $subject =
            $this->invokePrivate(
                'buildSubject',
                [
                    'RFE International, Inc.',
                    $this->approvedPeriod()
                ]
            );


        self::assertSame(
            'RFE International, Inc. Payroll Approved: July Payroll',
            $subject
        );
    }


    public function testBodyContainsApprovalWorkflowDetails(): void
    {
        $timezone =
            new DateTimeZone(
                'America/Los_Angeles'
            );


        $body =
            $this->invokePrivate(
                'buildBody',
                [
                    'RFE International, Inc.',
                    $timezone,
                    $this->approvedPeriod()
                ]
            );


        self::assertStringContainsString(
            'RFE International, Inc. Payroll Approval Notification',
            $body
        );


        self::assertStringContainsString(
            'A payroll period has been approved successfully.',
            $body
        );


        self::assertStringContainsString(
            'Payroll Period ID: 12',
            $body
        );


        self::assertStringContainsString(
            'Payroll Period: July Payroll',
            $body
        );


        self::assertStringContainsString(
            'Date Range: 2026-07-20 through 2026-07-26',
            $body
        );


        self::assertStringContainsString(
            'Status: Approved',
            $body
        );


        self::assertStringContainsString(
            'Approved By: test-admin',
            $body
        );


        /*
         * 2026-07-28 17:30:00 UTC is 10:30 PDT.
         */
        self::assertStringContainsString(
            'Approved At: 2026-07-28 10:30:00 PDT',
            $body
        );


        self::assertStringContainsString(
            'Review Started By: test-supervisor',
            $body
        );


        /*
         * 2026-07-28 16:00:00 UTC is 09:00 PDT.
         */
        self::assertStringContainsString(
            'Review Started At: 2026-07-28 09:00:00 PDT',
            $body
        );


        self::assertStringContainsString(
            'Company Timezone: America/Los_Angeles',
            $body
        );


        self::assertStringContainsString(
            'The payroll period is not final until it is locked',
            $body
        );
    }


    public function testBodyUsesSafeFallbackLabels(): void
    {
        $period =
            $this->approvedPeriod();


        $period['period_name'] =
            '';


        $period['approved_by_username'] =
            '';


        $period['reviewed_by_username'] =
            '';


        $period['reviewed_by_user_id'] =
            null;


        $body =
            $this->invokePrivate(
                'buildBody',
                [
                    'IQwurksPunch',
                    new DateTimeZone(
                        'UTC'
                    ),
                    $period
                ]
            );


        self::assertStringContainsString(
            'Payroll Period: Payroll Period #12',
            $body
        );


        self::assertStringContainsString(
            'Approved By: User #2',
            $body
        );


        self::assertStringContainsString(
            'Review Started By: Not recorded',
            $body
        );
    }


    public function testValidationRejectsPeriodThatIsNotApproved(): void
    {
        $period =
            $this->approvedPeriod();


        $period['status'] =
            'under_review';


        $this->expectException(
            RuntimeException::class
        );


        $this->expectExceptionMessage(
            'only for an approved payroll period'
        );


        $this->invokePrivate(
            'validateApprovedPeriod',
            [
                $period
            ]
        );
    }


    public function testValidationRequiresCompleteApprovalMetadata(): void
    {
        $period =
            $this->approvedPeriod();


        $period['approved_at'] =
            null;


        $this->expectException(
            RuntimeException::class
        );


        $this->expectExceptionMessage(
            'complete approval metadata'
        );


        $this->invokePrivate(
            'validateApprovedPeriod',
            [
                $period
            ]
        );
    }


    /**
     * @return array<string,mixed>
     */
    private function approvedPeriod(): array
    {
        return [
            'id' =>
                12,

            'period_name' =>
                'July Payroll',

            'start_date' =>
                '2026-07-20',

            'end_date' =>
                '2026-07-26',

            'status' =>
                'approved',

            'reviewed_by_user_id' =>
                1,

            'reviewed_by_username' =>
                'test-supervisor',

            'review_started_at' =>
                '2026-07-28 16:00:00',

            'approved_by_user_id' =>
                2,

            'approved_by_username' =>
                'test-admin',

            'approved_at' =>
                '2026-07-28 17:30:00',

            'locked_by_user_id' =>
                null,

            'locked_at' =>
                null
        ];
    }


    private function invokePrivate(
        string $methodName,
        array $arguments
    ): mixed
    {
        $reflection =
            new ReflectionClass(
                ApprovalNotificationEmailService::class
            );


        $method =
            $reflection->getMethod(
                $methodName
            );


        return
            $method->invokeArgs(
                $this->service,
                $arguments
            );
    }
}
