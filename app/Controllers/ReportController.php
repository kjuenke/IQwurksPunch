<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Repositories\PunchRepository;
use App\Services\PunchReportService;

class ReportController extends Controller
{
    private PunchReportService $reports;


    public function __construct()
    {
        $repository = new PunchRepository(
            Database::connection()
        );


        $this->reports =
            new PunchReportService(
                $repository
            );
    }



    public function punches(): void
    {
        $punches =
            $this->reports->today();


        $this->render(
            'reports/punches.twig',
            [
                'title' => 'Punch Report',
                'activeMenu' => 'reports',
                'punches' => $punches
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
                'title' => 'Payroll Summary',
                'activeMenu' => 'reports',
                'summary' => $summary
            ]
        );
    }

}
