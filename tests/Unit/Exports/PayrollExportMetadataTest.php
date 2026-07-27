<?php
declare(strict_types=1);

use App\Exports\PayrollWorkspaceCsvExporter;
use App\Exports\Pdf\EmployeeTimeCardPdfExporter;
use App\Exports\Pdf\PayrollWorkspacePdfExporter;
use PHPUnit\Framework\TestCase;

final class PayrollExportMetadataTest extends TestCase
{
    public function testWorkspaceCsvIncludesExactPayrollPeriodMetadata(): void
    {
        $exporter =
            new PayrollWorkspaceCsvExporter();


        $rows =
            $this->csvRows(
                $exporter->export(
                    $this->exactSummary()
                )
            );


        self::assertSame(
            'Exact Match',
            $this->rowValue(
                $rows,
                'Payroll-Period Association'
            )
        );


        self::assertSame(
            '42',
            $this->rowValue(
                $rows,
                'Payroll Period ID'
            )
        );


        self::assertSame(
            'July 2026 Payroll',
            $this->rowValue(
                $rows,
                'Payroll Period Name'
            )
        );


        self::assertSame(
            'Locked',
            $this->rowValue(
                $rows,
                'Payroll Workflow Status'
            )
        );


        self::assertSame(
            '0',
            $this->rowValue(
                $rows,
                'Open Payroll Exceptions'
            )
        );


        self::assertSame(
            '3',
            $this->rowValue(
                $rows,
                'Total Payroll Exceptions'
            )
        );


        self::assertSame(
            'No',
            $this->rowValue(
                $rows,
                'Approval Blocked'
            )
        );


        self::assertSame(
            'review-supervisor',
            $this->rowValue(
                $rows,
                'Reviewed By'
            )
        );


        self::assertSame(
            'approval-admin',
            $this->rowValue(
                $rows,
                'Approved By'
            )
        );


        self::assertSame(
            'locking-supervisor',
            $this->rowValue(
                $rows,
                'Locked By'
            )
        );


        self::assertSame(
            '/payroll-periods/42',
            $this->rowValue(
                $rows,
                'Payroll Period Detail'
            )
        );
    }


    public function testWorkspaceCsvIncludesPartialOverlapWithoutWorkflowMetadata(): void
    {
        $summary =
            $this->baseSummary();


        $summary['payroll_period_association'] =
            'partial_overlap';


        $summary['payroll_period_association_message'] =
            'This report partially overlaps an existing payroll period.';


        $summary['payroll_period'] =
            null;


        $rows =
            $this->csvRows(
                (
                    new PayrollWorkspaceCsvExporter()
                )
                    ->export(
                        $summary
                    )
            );


        self::assertSame(
            'Partial Overlap',
            $this->rowValue(
                $rows,
                'Payroll-Period Association'
            )
        );


        self::assertStringContainsString(
            'partially overlaps',
            $this->rowValue(
                $rows,
                'Payroll-Period Association Message'
            )
        );


        self::assertSame(
            '',
            $this->rowValue(
                $rows,
                'Payroll Period ID'
            )
        );


        self::assertSame(
            '',
            $this->rowValue(
                $rows,
                'Payroll Period Name'
            )
        );


        self::assertSame(
            'Not Associated',
            $this->rowValue(
                $rows,
                'Payroll Workflow Status'
            )
        );


        self::assertSame(
            '',
            $this->rowValue(
                $rows,
                'Open Payroll Exceptions'
            )
        );


        self::assertSame(
            '',
            $this->rowValue(
                $rows,
                'Total Payroll Exceptions'
            )
        );


        self::assertSame(
            '',
            $this->rowValue(
                $rows,
                'Approval Blocked'
            )
        );
    }


    public function testWorkspacePdfHtmlIncludesExactPayrollPeriodMetadata(): void
    {
        $html =
            $this->workspacePdfHtml(
                $this->exactSummary()
            );


        self::assertStringContainsString(
            'Exact Payroll-Period Association',
            $html
        );


        self::assertStringContainsString(
            'July 2026 Payroll',
            $html
        );


        self::assertStringContainsString(
            'Period ID:</span> 42',
            $html
        );


        self::assertStringContainsString(
            'Workflow status:</span> Locked',
            $html
        );


        self::assertStringContainsString(
            'Open exceptions:</span> 0',
            $html
        );


        self::assertStringContainsString(
            'Total exceptions:</span> 3',
            $html
        );


        self::assertStringContainsString(
            'Approval blocked:</span> No',
            $html
        );


        self::assertStringContainsString(
            '2026-08-01 08:00:00 by review-supervisor',
            $html
        );


        self::assertStringContainsString(
            '2026-08-01 09:00:00 by approval-admin',
            $html
        );


        self::assertStringContainsString(
            '2026-08-01 10:00:00 by locking-supervisor',
            $html
        );


        self::assertStringContainsString(
            'Punch protection:',
            $html
        );
    }


    public function testWorkspacePdfHtmlIncludesPartialOverlapWarning(): void
    {
        $summary =
            $this->baseSummary();


        $summary['payroll_period_association'] =
            'partial_overlap';


        $summary['payroll_period_association_message'] =
            'This report overlaps July 2026 Payroll but is not an exact match.';


        $summary['payroll_period'] =
            null;


        $html =
            $this->workspacePdfHtml(
                $summary
            );


        self::assertStringContainsString(
            'Partial Payroll-Period Overlap',
            $html
        );


        self::assertStringContainsString(
            'overlaps July 2026 Payroll',
            $html
        );


        self::assertStringContainsString(
            'does not carry payroll approval',
            $html
        );


        self::assertStringNotContainsString(
            'Exact Payroll-Period Association',
            $html
        );
    }


    public function testWorkspacePdfHtmlIncludesNoAssociationMessage(): void
    {
        $summary =
            $this->baseSummary();


        $summary['payroll_period_association'] =
            'none';


        $summary['payroll_period_association_message'] =
            'This report is not associated with a payroll period.';


        $summary['payroll_period'] =
            null;


        $html =
            $this->workspacePdfHtml(
                $summary
            );


        self::assertStringContainsString(
            'No Payroll-Period Association',
            $html
        );


        self::assertStringContainsString(
            'not associated with a payroll period',
            $html
        );


        self::assertStringContainsString(
            'does not carry payroll review',
            $html
        );
    }


    public function testTimeCardPdfHtmlIncludesExactPayrollPeriodMetadata(): void
    {
        $summary =
            $this->exactSummary();


        $html =
            $this->timeCardPdfHtml(
                $summary
            );


        self::assertStringContainsString(
            'Exact Payroll-Period Association',
            $html
        );


        self::assertStringContainsString(
            'July 2026 Payroll',
            $html
        );


        self::assertStringContainsString(
            'Period ID:</span> 42',
            $html
        );


        self::assertStringContainsString(
            'Workflow status:</span> Locked',
            $html
        );


        self::assertStringContainsString(
            'Open exceptions:</span> 0',
            $html
        );


        self::assertStringContainsString(
            'Total exceptions:</span> 3',
            $html
        );


        self::assertStringContainsString(
            'Approval blocked:</span> No',
            $html
        );


        self::assertStringContainsString(
            '2026-08-01 08:00:00 by review-supervisor',
            $html
        );


        self::assertStringContainsString(
            '2026-08-01 09:00:00 by approval-admin',
            $html
        );


        self::assertStringContainsString(
            '2026-08-01 10:00:00 by locking-supervisor',
            $html
        );


        self::assertStringContainsString(
            'Punch protection:',
            $html
        );
    }


    public function testTimeCardPdfHtmlIncludesPartialOverlapWarning(): void
    {
        $summary =
            $this->baseSummary();


        $summary['payroll_period_association'] =
            'partial_overlap';


        $summary['payroll_period_association_message'] =
            'This time card partially overlaps July 2026 Payroll.';


        $summary['payroll_period'] =
            null;


        $html =
            $this->timeCardPdfHtml(
                $summary
            );


        self::assertStringContainsString(
            'Partial Payroll-Period Overlap',
            $html
        );


        self::assertStringContainsString(
            'partially overlaps July 2026 Payroll',
            $html
        );


        self::assertStringContainsString(
            'does not carry payroll approval',
            $html
        );


        self::assertStringNotContainsString(
            'Punch protection:',
            $html
        );
    }


    public function testTimeCardPdfHtmlIncludesNoAssociationMessage(): void
    {
        $summary =
            $this->baseSummary();


        $summary['payroll_period_association'] =
            'none';


        $summary['payroll_period_association_message'] =
            'This time card is not associated with a payroll period.';


        $summary['payroll_period'] =
            null;


        $html =
            $this->timeCardPdfHtml(
                $summary
            );


        self::assertStringContainsString(
            'No Payroll-Period Association',
            $html
        );


        self::assertStringContainsString(
            'not associated with a payroll period',
            $html
        );


        self::assertStringContainsString(
            'does not carry payroll review',
            $html
        );
    }


    /**
     * @return array<string,mixed>
     */
    private function exactSummary(): array
    {
        $summary =
            $this->baseSummary();


        $summary['payroll_period_association'] =
            'exact';


        $summary['payroll_period_association_message'] =
            'This report exactly matches a payroll review period.';


        $summary['payroll_period'] = [
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
                false,

            'detail_url' =>
                '/payroll-periods/42'
        ];


        return $summary;
    }


    /**
     * @return array<string,mixed>
     */
    private function baseSummary(): array
    {
        return [
            'start_date' =>
                '2026-07-20',

            'end_date' =>
                '2026-07-31',

            'day_count' =>
                12,

            'timezone' =>
                'America/Los_Angeles',

            'filters' => [
                'employee_id' =>
                    1,

                'department' =>
                    'Assembly'
            ],

            'issue_count' =>
                0,

            'totals' => [
                'employee_count' =>
                    1,

                'gross_hours' =>
                    80.0,

                'regular_hours' =>
                    80.0,

                'daily_overtime_hours' =>
                    0.0,

                'weekly_overtime_hours' =>
                    0.0,

                'double_time_hours' =>
                    0.0,

                'overtime_hours' =>
                    0.0,

                'premium_hours' =>
                    0.0,

                'total_hours' =>
                    80.0
            ],

            'employees' => [
                $this->employeeSummary()
            ]
        ];
    }


    /**
     * @return array<string,mixed>
     */
    private function employeeSummary(): array
    {
        return [
            'employee_number' =>
                '1001',

            'name' =>
                'Test Employee',

            'department' =>
                'Assembly',

            'complete' =>
                true,

            'gross_hours' =>
                80.0,

            'regular_hours' =>
                80.0,

            'daily_overtime_hours' =>
                0.0,

            'weekly_overtime_hours' =>
                0.0,

            'double_time_hours' =>
                0.0,

            'overtime_hours' =>
                0.0,

            'premium_hours' =>
                0.0,

            'total_hours' =>
                80.0,

            'errors' =>
                [],

            'days' =>
                [],

            'weeks' =>
                []
        ];
    }


    /**
     * @param array<string,mixed> $summary
     */
    private function workspacePdfHtml(
        array $summary
    ): string
    {
        $exporter =
            new PayrollWorkspacePdfExporter();


        $method =
            new ReflectionMethod(
                $exporter,
                'html'
            );


        return
            (string)$method->invoke(
                $exporter,
                $summary,
                $this->company()
            );
    }


    /**
     * @param array<string,mixed> $summary
     */
    private function timeCardPdfHtml(
        array $summary
    ): string
    {
        $exporter =
            new EmployeeTimeCardPdfExporter();


        $method =
            new ReflectionMethod(
                $exporter,
                'html'
            );


        return
            (string)$method->invoke(
                $exporter,
                $summary,
                $this->company(),
                $summary['employees'][0]
            );
    }


    /**
     * @return array<string,mixed>
     */
    private function company(): array
    {
        return [
            'company_name' =>
                'Test Company',

            'address' =>
                '100 Test Street',

            'city' =>
                'Reno',

            'state' =>
                'NV',

            'zip' =>
                '89501',

            'timezone' =>
                'America/Los_Angeles'
        ];
    }


    /**
     * @return array<int,array<int,string|null>>
     */
    private function csvRows(
        string $csv
    ): array
    {
        $stream =
            fopen(
                'php://temp',
                'w+'
            );


        if ($stream === false) {

            throw new RuntimeException(
                'The temporary CSV stream could not be opened.'
            );
        }


        fwrite(
            $stream,
            $csv
        );


        rewind(
            $stream
        );


        $rows = [];


        while (
            (
                $row =
                    fgetcsv(
                        $stream,
                        null,
                        ',',
                        '"',
                        ''
                    )
            )
            !==
            false
        ) {

            $rows[] =
                $row;
        }


        fclose(
            $stream
        );


        return $rows;
    }


    /**
     * @param array<int,array<int,string|null>> $rows
     */
    private function rowValue(
        array $rows,
        string $label
    ): string
    {
        foreach ($rows as $row) {

            if (
                (
                    $row[0]
                    ??
                    null
                )
                !==
                $label
            ) {

                continue;
            }


            return
                (string)(
                    $row[1]
                    ??
                    ''
                );
        }


        throw new RuntimeException(
            'CSV metadata row was not found: '
            .
            $label
        );
    }
}
