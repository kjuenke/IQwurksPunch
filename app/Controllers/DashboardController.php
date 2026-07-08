<?php
declare(strict_types=1);

namespace App\Controllers;

class DashboardController extends Controller
{
    public function index(): void
    {
        $this->render(
            'dashboard/index.twig',
            [
                'title' => 'Dashboard',
                'activeMenu' => 'dashboard'
            ]
        );
    }
}
