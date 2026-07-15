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
}
