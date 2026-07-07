<?php
declare(strict_types=1);

use App\Controllers\HomeController;

$home = new HomeController();

$router->get('/', [$home, 'index']);

use App\Controllers\SetupController;

$setup = new SetupController();

$router->get('/setup', [$setup, 'index']);

$router->post('/setup', [$setup, 'create']);
