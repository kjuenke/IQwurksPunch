<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Container;
use App\Services\DashboardService;

class DashboardController extends Controller
{
    private DashboardService $dashboard;


    public function __construct()
    {
        $this->dashboard =
            Container::dashboardService();
    }


    public function index(): void
    {
        $this->render(
            'dashboard/index.twig',
            [
                'title' =>
                    'Dashboard',

                'activeMenu' =>
                    'dashboard',

                'dashboard' =>
                    $this->dashboard->summary()
            ]
        );
    }
}
