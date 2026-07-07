<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use App\Core\Database;

$db = Database::connection();

$migrations = $db->query(
    "SELECT migration FROM migrations"
)->fetchAll(PDO::FETCH_COLUMN);

foreach (glob(__DIR__ . '/database/migrations/*.php') as $file) {

    $name = basename($file);

    if (in_array($name, $migrations, true)) {
        echo "Skipping: {$name}" . PHP_EOL;
        continue;
    }

    echo "Running: {$name}" . PHP_EOL;

    $migration = require $file;

    $migration->up();

    $stmt = $db->prepare(
        "INSERT INTO migrations (migration) VALUES (:migration)"
    );

    $stmt->execute([
        'migration' => $name
    ]);

    echo "Completed: {$name}" . PHP_EOL;
}

echo "Migration complete." . PHP_EOL;
