<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Container;
use App\Services\PunchReportService;

class ReportController extends Controller
{
    private PunchReportService $reports;


    public function __construct()
    {
        $this->reports =
            Container::punchReportService();
    }


    public function punches(): void
    {
        $punches =
            $this->reports->today();


        $this->render(
            'reports/punches.twig',
            [
                'title' =>
                    'Punch Report',

                'activeMenu' =>
                    'reports',

                'punches' =>
                    $punches
            ]
        );
    }


    public function payroll(): void
    {
        $summary =
            $this->reports->dailySummary();


        $this->render(
            'reports/payroll.twig',
            [
                'title' =>
                    'Payroll Summary',

                'activeMenu' =>
                    'reports',

                'summary' =>
                    $summary
            ]
        );
    }


    public function weeklyPayroll(): void
    {
        $summary =
            $this->reports->weeklySummary();


        $this->render(
            'reports/weekly-payroll.twig',
            [
                'title' =>
                    'Weekly Payroll Summary',

                'activeMenu' =>
                    'reports',

                'summary' =>
                    $summary
            ]
        );
    }
}
