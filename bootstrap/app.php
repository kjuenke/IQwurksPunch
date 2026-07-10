<?php
declare(strict_types=1);

use App\Core\Application;
use App\Core\Config;
use App\Core\Database;
use App\Core\Router;

require_once __DIR__ . '/../vendor/autoload.php';

Config::load();


try {

    $db =
        Database::connection();


    $stmt =
        $db->query(
            "
            SELECT timezone
            FROM company_settings
            LIMIT 1
            "
        );


    $timezone =
        $stmt->fetchColumn();


    if (
        $timezone
        &&
        in_array(
            $timezone,
            timezone_identifiers_list(),
            true
        )
    ) {

        date_default_timezone_set(
            $timezone
        );

    } else {

        date_default_timezone_set(
            'America/Los_Angeles'
        );
    }


} catch (\Throwable $e) {

    date_default_timezone_set(
        'America/Los_Angeles'
    );
}



$app = new Application();

$router = new Router();

require_once __DIR__ . '/../routes/web.php';

$app->setRouter($router);

return $app;
