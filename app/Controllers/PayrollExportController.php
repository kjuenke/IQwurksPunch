<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Container;
use App\Exports\DailyPayrollCsvExporter;
use App\Exports\WeeklyPayrollCsvExporter;
use App\Services\PunchReportService;

class PayrollExportController extends Controller
{
    private PunchReportService $reports;

    private DailyPayrollCsvExporter $dailyCsv;

    private WeeklyPayrollCsvExporter $weeklyCsv;


    public function __construct()
    {
        $this->reports =
            Container::punchReportService();


        $this->dailyCsv =
            Container::dailyPayrollCsvExporter();


        $this->weeklyCsv =
            Container::weeklyPayrollCsvExporter();
    }


    public function dailyCsv(): never
    {
        $summary =
            $this->reports->dailySummary();


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
            $this->dailyCsv->export(
                $summary
            )
        );
    }


    public function weeklyCsv(): never
    {
        $summary =
            $this->reports->weeklySummary();


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
            $this->weeklyCsv->export(
                $summary
            )
        );
    }


    private function download(
        string $filename,
        string $contents
    ): never
    {
        if (headers_sent()) {

            throw new \RuntimeException(
                'The payroll export cannot be downloaded because output has already started.'
            );
        }


        header(
            'Content-Type: text/csv; charset=UTF-8'
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
