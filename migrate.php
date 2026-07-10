<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use App\Core\Database;


$db = Database::connection();

$db->setAttribute(
    PDO::ATTR_ERRMODE,
    PDO::ERRMODE_EXCEPTION
);


/*
|--------------------------------------------------------------------------
| Ensure migration table exists
|--------------------------------------------------------------------------
*/

$db->exec(
    "
    CREATE TABLE IF NOT EXISTS migrations
    (
        id INTEGER PRIMARY KEY AUTOINCREMENT,

        migration TEXT NOT NULL UNIQUE,

        batch INTEGER DEFAULT 1,

        executed_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
    "
);



$command =
    $argv[1] ?? 'migrate';



function getExecuted(PDO $db): array
{
    return $db
        ->query(
            "
            SELECT migration
            FROM migrations
            "
        )
        ->fetchAll(PDO::FETCH_COLUMN);
}



function getNextBatch(PDO $db): int
{
    $batch =
        $db
            ->query(
                "
                SELECT MAX(batch)
                FROM migrations
                "
            )
            ->fetchColumn();


    return ((int)$batch) + 1;
}



/*
|--------------------------------------------------------------------------
| MIGRATE
|--------------------------------------------------------------------------
*/

if ($command === 'migrate') {


    $executed =
        getExecuted($db);


    $batch =
        getNextBatch($db);


    $files =
        glob(
            __DIR__
            . '/database/migrations/*.php'
        );


    sort($files);



    foreach ($files as $file) {


        $name =
            basename($file);



        if (
            in_array(
                $name,
                $executed,
                true
            )
        ) {

            echo
                "Skipping: {$name}"
                . PHP_EOL;

            continue;
        }



        echo
            "Running: {$name}"
            . PHP_EOL;



        try {


            $db->beginTransaction();


            $migration =
                require $file;


            $migration->up();



            $stmt =
                $db->prepare(
                    "
                    INSERT INTO migrations
                    (
                        migration,
                        batch
                    )

                    VALUES
                    (
                        :migration,
                        :batch
                    )
                    "
                );


            $stmt->execute(
                [
                    'migration' =>
                        $name,

                    'batch' =>
                        $batch
                ]
            );


            $db->commit();


            echo
                "Completed: {$name}"
                . PHP_EOL;


        } catch (\Throwable $e) {


            if (
                $db->inTransaction()
            ) {

                $db->rollBack();

            }


            echo
                "FAILED: {$name}"
                . PHP_EOL;


            echo
                $e->getMessage()
                . PHP_EOL;


            exit(1);
        }
    }



    echo
        "Migration complete."
        . PHP_EOL;


    exit;
}



/*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
*/

if ($command === 'status') {


    $executed =
        getExecuted($db);



    $files =
        glob(
            __DIR__
            . '/database/migrations/*.php'
        );


    sort($files);



    foreach ($files as $file) {


        $name =
            basename($file);



        echo
            (
                in_array(
                    $name,
                    $executed,
                    true
                )
                ? "[OK] "
                : "[ ] "
            )
            . $name
            . PHP_EOL;
    }


    exit;
}



echo "Unknown command." . PHP_EOL;
