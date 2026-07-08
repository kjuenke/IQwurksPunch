<?php
declare(strict_types=1);

use App\Controllers\HomeController;

$home = new HomeController();

$router->get('/', [$home, 'index']);

use App\Controllers\SetupController;

$setup = new SetupController();

$router->get('/setup', [$setup, 'index']);

$router->post('/setup', [$setup, 'create']);

use App\Controllers\AuthController;

$auth = new AuthController();

$router->get('/login', [$auth, 'login']);

$router->post('/login', [$auth, 'authenticate']);

$router->get('/logout', [$auth, 'logout']);

use App\Controllers\DashboardController;

$dashboard = new DashboardController();

$router->get(
    '/dashboard',
    [$dashboard, 'index']
);
