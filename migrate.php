<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use App\Core\Database;

foreach (glob(__DIR__ . '/database/migrations/*.php') as $file) {

    echo "Running migration: " . basename($file) . PHP_EOL;

    $migration = require $file;

    $migration->up();
}

echo "Database migration complete." . PHP_EOL;
