<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Container;
use App\Exports\DailyPayrollCsvExporter;
use App\Exports\PayrollWorkspaceCsvExporter;
use App\Exports\Pdf\DailyPayrollPdfExporter;
use App\Exports\Pdf\EmployeeTimeCardPdfExporter;
use App\Exports\Pdf\PayrollRegisterPdfExporter;
use App\Exports\Pdf\PayrollWorkspacePdfExporter;
use App\Exports\WeeklyPayrollCsvExporter;
use App\Services\CompanySettingsService;
use App\Services\PayrollReportPeriodMetadataService;
use App\Services\PayrollWorkspaceService;
use App\Services\PunchReportService;
use RuntimeException;

class PayrollExportController extends Controller
{
    private PunchReportService $reports;

    private PayrollWorkspaceService $workspace;

    private CompanySettingsService $settings;

    private PayrollReportPeriodMetadataService $periodMetadata;

    private DailyPayrollCsvExporter $dailyCsv;

    private WeeklyPayrollCsvExporter $weeklyCsv;

    private PayrollWorkspaceCsvExporter $workspaceCsv;

    private DailyPayrollPdfExporter $dailyPdf;

    private PayrollRegisterPdfExporter $weeklyPdf;

    private PayrollWorkspacePdfExporter $workspacePdf;

    private EmployeeTimeCardPdfExporter $timeCardPdf;


    public function __construct()
    {
        $this->reports =
            Container::punchReportService();


        $this->workspace =
            Container::payrollWorkspaceService();


        $this->settings =
            Container::companySettingsService();


        $this->periodMetadata =
            Container::payrollReportPeriodMetadataService();


        $this->dailyCsv =
            Container::dailyPayrollCsvExporter();


        $this->weeklyCsv =
            Container::weeklyPayrollCsvExporter();


        $this->workspaceCsv =
            Container::payrollWorkspaceCsvExporter();


        $this->dailyPdf =
            Container::dailyPayrollPdfExporter();


        $this->weeklyPdf =
            Container::payrollRegisterPdfExporter();


        $this->workspacePdf =
            Container::payrollWorkspacePdfExporter();


        $this->timeCardPdf =
            Container::employeeTimeCardPdfExporter();
    }


    public function dailyCsv(): never
    {
        $summary =
            $this->reports
                ->dailySummary();


        $date =
            $summary[0]['date']
            ??
            date(
                'Y-m-d'
            );


        $this->download(
            'iqwurkspunch-daily-payroll-'
            .
            $date
            .
            '.csv',
            $this->dailyCsv
                ->export(
                    $summary
                ),
            'text/csv; charset=UTF-8'
        );
    }


    public function dailyPdf(): never
    {
        $summary =
            $this->reports
                ->dailySummary();


        $company =
            $this->settings
                ->get()
            ??
            [];


        $date =
            $summary[0]['date']
            ??
            date(
                'Y-m-d'
            );


        $this->download(
            'iqwurkspunch-daily-payroll-'
            .
            $date
            .
            '.pdf',
            $this->dailyPdf
                ->export(
                    $summary,
                    $company
                ),
            'application/pdf'
        );
    }


    public function weeklyCsv(): never
    {
        $summary =
            $this->reports
                ->weeklySummary();


        $weekStart =
            $summary['week_start']
            ??
            date(
                'Y-m-d'
            );


        $weekEnd =
            $summary['week_end']
            ??
            $weekStart;


        $this->download(
            'iqwurkspunch-weekly-payroll-'
            .
            $weekStart
            .
            '-to-'
            .
            $weekEnd
            .
            '.csv',
            $this->weeklyCsv
                ->export(
                    $summary
                ),
            'text/csv; charset=UTF-8'
        );
    }


    public function weeklyPdf(): never
    {
        $summary =
            $this->reports
                ->weeklySummary();


        $company =
            $this->settings
                ->get()
            ??
            [];


        $weekStart =
            $summary['week_start']
            ??
            date(
                'Y-m-d'
            );


        $weekEnd =
            $summary['week_end']
            ??
            $weekStart;


        $this->download(
            'iqwurkspunch-weekly-payroll-register-'
            .
            $weekStart
            .
            '-to-'
            .
            $weekEnd
            .
            '.pdf',
            $this->weeklyPdf
                ->export(
                    $summary,
                    $company
                ),
            'application/pdf'
        );
    }


    public function workspaceCsv(): never
    {
        $filters =
            $this->workspaceFilters();


        $summary =
            $this->workspaceSummary(
                $filters
            );


        $this->download(
            'iqwurkspunch-payroll-workspace-'
            .
            $summary['start_date']
            .
            '-to-'
            .
            $summary['end_date']
            .
            '.csv',
            $this->workspaceCsv
                ->export(
                    $summary
                ),
            'text/csv; charset=UTF-8'
        );
    }


    public function workspacePdf(): never
    {
        $filters =
            $this->workspaceFilters();


        $summary =
            $this->workspaceSummary(
                $filters
            );


        $company =
            $this->settings
                ->get()
            ??
            [];


        $this->download(
            'iqwurkspunch-payroll-workspace-'
            .
            $summary['start_date']
            .
            '-to-'
            .
            $summary['end_date']
            .
            '.pdf',
            $this->workspacePdf
                ->export(
                    $summary,
                    $company
                ),
            'application/pdf'
        );
    }


    public function timeCardPdf(): never
    {
        $filters =
            $this->workspaceFilters();


        if ($filters['employee_id'] === null) {

            throw new RuntimeException(
                'Select one employee before downloading an employee time card.'
            );
        }


        $summary =
            $this->workspaceSummary(
                $filters
            );


        $company =
            $this->settings
                ->get()
            ??
            [];


        $employee =
            $summary['employees'][0]
            ??
            null;


        if (!is_array($employee)) {

            throw new RuntimeException(
                'No payroll activity was found for the selected employee and date range.'
            );
        }


        $employeeNumber =
            $this->safeFilenamePart(
                (string)(
                    $employee['employee_number']
                    ??
                    $filters['employee_id']
                )
            );


        $this->download(
            'iqwurkspunch-time-card-'
            .
            $employeeNumber
            .
            '-'
            .
            $summary['start_date']
            .
            '-to-'
            .
            $summary['end_date']
            .
            '.pdf',
            $this->timeCardPdf
                ->export(
                    $summary,
                    $company
                ),
            'application/pdf'
        );
    }


    /**
     * @param array{
     *     start_date:string,
     *     end_date:string,
     *     employee_id:?int,
     *     department:?string
     * } $filters
     *
     * @return array<string,mixed>
     */
    private function workspaceSummary(
        array $filters
    ): array
    {
        $summary =
            $this->workspace
                ->summary(
                    $filters['start_date'],
                    $filters['end_date'],
                    $filters['employee_id'],
                    $filters['department']
                );


        return
            $this->periodMetadata
                ->enrich(
                    $summary,
                    $filters['start_date'],
                    $filters['end_date']
                );
    }


    /**
     * @return array{
     *     start_date:string,
     *     end_date:string,
     *     employee_id:?int,
     *     department:?string
     * }
     */
    private function workspaceFilters(): array
    {
        $startDate =
            trim(
                (string)(
                    $_GET['start_date']
                    ??
                    ''
                )
            );


        $endDate =
            trim(
                (string)(
                    $_GET['end_date']
                    ??
                    ''
                )
            );


        if (
            $startDate === ''
            ||
            $endDate === ''
        ) {

            throw new RuntimeException(
                'Start date and end date are required for Payroll Workspace exports.'
            );
        }


        $employeeId =
            $this->employeeFilter(
                $_GET['employee_id']
                ??
                null
            );


        $department =
            $this->departmentFilter(
                $_GET['department']
                ??
                null
            );


        return [
            'start_date' =>
                $startDate,

            'end_date' =>
                $endDate,

            'employee_id' =>
                $employeeId,

            'department' =>
                $department
        ];
    }


    private function employeeFilter(
        mixed $value
    ): ?int
    {
        if (
            $value === null
            ||
            $value === ''
            ||
            $value === 'all'
        ) {

            return null;
        }


        $employeeId =
            filter_var(
                $value,
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' =>
                            1
                    ]
                ]
            );


        return
            $employeeId === false
                ? null
                : (int)$employeeId;
    }


    private function departmentFilter(
        mixed $value
    ): ?string
    {
        if (
            !is_string(
                $value
            )
        ) {

            return null;
        }


        $department =
            trim(
                $value
            );


        if (
            $department === ''
            ||
            $department === 'all'
        ) {

            return null;
        }


        return $department;
    }


    private function safeFilenamePart(
        string $value
    ): string
    {
        $value =
            preg_replace(
                '/[^A-Za-z0-9_-]+/',
                '-',
                trim(
                    $value
                )
            )
            ??
            'employee';


        $value =
            trim(
                $value,
                '-_'
            );


        return
            $value === ''
                ? 'employee'
                : $value;
    }


    private function download(
        string $filename,
        string $contents,
        string $contentType
    ): never
    {
        if (headers_sent()) {

            throw new RuntimeException(
                'The payroll export cannot be downloaded because output has already started.'
            );
        }


        header(
            'Content-Type: '
            .
            $contentType
        );


        header(
            'Content-Disposition: attachment; filename="'
            .
            $filename
            .
            '"'
        );


        header(
            'Content-Length: '
            .
            strlen(
                $contents
            )
        );


        header(
            'Cache-Control: no-store, no-cache, must-revalidate'
        );


        header(
            'Pragma: no-cache'
        );


        echo $contents;


        exit;
    }
}
