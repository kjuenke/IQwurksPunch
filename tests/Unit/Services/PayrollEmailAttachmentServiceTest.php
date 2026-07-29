<?php
declare(strict_types=1);

use App\Exports\DailyPayrollCsvExporter;
use App\Exports\WeeklyPayrollCsvExporter;
use App\Services\PayrollEmailAttachmentService;
use PHPUnit\Framework\TestCase;

final class PayrollEmailAttachmentServiceTest extends TestCase
{
    private PayrollEmailAttachmentService $service;


    protected function setUp(): void
    {
        parent::setUp();


        $this->service =
            new PayrollEmailAttachmentService(
                new DailyPayrollCsvExporter(),
                new WeeklyPayrollCsvExporter()
            );
    }


    public function testDailyCsvAttachmentUsesExpectedMetadata(): void
    {
        $attachment =
            $this->service
                ->dailyCsv(
                    [],
                    '2026-07-29'
                );


        self::assertSame(
            'iqwurkspunch-daily-payroll-2026-07-29.csv',
            $attachment['filename']
        );


        self::assertSame(
            'text/csv',
            $attachment['content_type']
        );


        self::assertNotSame(
            '',
            $attachment['contents']
        );


        self::assertStringContainsString(
            'Date',
            $attachment['contents']
        );
    }


    public function testWeeklyCsvAttachmentUsesExpectedMetadata(): void
    {
        $attachment =
            $this->service
                ->weeklyCsv(
                    [
                        'week_start' =>
                            '2026-07-20',

                        'week_end' =>
                            '2026-07-26',

                        'employees' =>
                            []
                    ],
                    '2026-07-20',
                    '2026-07-26'
                );


        self::assertSame(
            'iqwurkspunch-weekly-payroll-2026-07-20-to-2026-07-26.csv',
            $attachment['filename']
        );


        self::assertSame(
            'text/csv',
            $attachment['content_type']
        );


        self::assertNotSame(
            '',
            $attachment['contents']
        );


        self::assertStringContainsString(
            'Week Start',
            $attachment['contents']
        );
    }


    public function testDailyAttachmentRejectsImpossibleDate(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );


        $this->service
            ->dailyCsv(
                [],
                '2026-02-30'
            );
    }


    public function testWeeklyAttachmentRejectsReversedDateRange(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );


        $this->expectExceptionMessage(
            'end date cannot be earlier'
        );


        $this->service
            ->weeklyCsv(
                [
                    'employees' =>
                        []
                ],
                '2026-07-26',
                '2026-07-20'
            );
    }


    public function testWeeklyAttachmentRejectsInvalidStartDate(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );


        $this->service
            ->weeklyCsv(
                [
                    'employees' =>
                        []
                ],
                'not-a-date',
                '2026-07-26'
            );
    }
}
