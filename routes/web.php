<?php
declare(strict_types=1);

use App\Controllers\HomeController;

$home = new HomeController();

$router->get('/', [$home, 'index']);
