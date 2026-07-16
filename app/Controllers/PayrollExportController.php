<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Container;
use App\Exports\DailyPayrollCsvExporter;
use App\Exports\Pdf\DailyPayrollPdfExporter;
use App\Exports\Pdf\PayrollRegisterPdfExporter;
use App\Exports\WeeklyPayrollCsvExporter;
use App\Services\CompanySettingsService;
use App\Services\PunchReportService;
use RuntimeException;

class PayrollExportController extends Controller
{
    private PunchReportService $reports;

    private CompanySettingsService $settings;

    private DailyPayrollCsvExporter $dailyCsv;

    private WeeklyPayrollCsvExporter $weeklyCsv;

    private DailyPayrollPdfExporter $dailyPdf;

    private PayrollRegisterPdfExporter $weeklyPdf;


    public function __construct()
    {
        $this->reports =
            Container::punchReportService();


        $this->settings =
            Container::companySettingsService();


        $this->dailyCsv =
            Container::dailyPayrollCsvExporter();


        $this->weeklyCsv =
            Container::weeklyPayrollCsvExporter();


        $this->dailyPdf =
            Container::dailyPayrollPdfExporter();


        $this->weeklyPdf =
            Container::payrollRegisterPdfExporter();
    }


    public function dailyCsv(): never
    {
        $summary =
            $this->reports->dailySummary();


        $date =
            $summary[0]['date']
            ??
            date('Y-m-d');


        $this->download(
            'iqwurkspunch-daily-payroll-'
            .
            $date
            .
            '.csv',
            $this->dailyCsv->export(
                $summary
            ),
            'text/csv; charset=UTF-8'
        );
    }


    public function dailyPdf(): never
    {
        $summary =
            $this->reports->dailySummary();


        $company =
            $this->settings->get()
            ??
            [];


        $date =
            $summary[0]['date']
            ??
            date('Y-m-d');


        $this->download(
            'iqwurkspunch-daily-payroll-'
            .
            $date
            .
            '.pdf',
            $this->dailyPdf->export(
                $summary,
                $company
            ),
            'application/pdf'
        );
    }


    public function weeklyCsv(): never
    {
        $summary =
            $this->reports->weeklySummary();


        $weekStart =
            $summary['week_start']
            ??
            date('Y-m-d');


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
            $this->weeklyCsv->export(
                $summary
            ),
            'text/csv; charset=UTF-8'
        );
    }


    public function weeklyPdf(): never
    {
        $summary =
            $this->reports->weeklySummary();


        $company =
            $this->settings->get()
            ??
            [];


        $weekStart =
            $summary['week_start']
            ??
            date('Y-m-d');


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
            $this->weeklyPdf->export(
                $summary,
                $company
            ),
            'application/pdf'
        );
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
