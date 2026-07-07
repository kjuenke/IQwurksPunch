<?php
declare(strict_types=1);

use App\Core\Application;
use App\Core\Config;
use App\Core\Router;

require_once __DIR__ . '/../vendor/autoload.php';

Config::load();

$app = new Application();

$router = new Router();

require_once __DIR__ . '/../routes/web.php';

$app->setRouter($router);

return $app;
