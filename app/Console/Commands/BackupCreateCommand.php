<?php
declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\CommandInterface;
use App\Core\Container;
use App\Services\DatabaseBackupService;
use Throwable;

final class BackupCreateCommand implements CommandInterface
{
    private DatabaseBackupService $backups;


    public function __construct(
        ?DatabaseBackupService $backups = null
    )
    {
        if ($backups !== null) {

            $this->backups =
                $backups;


            return;
        }


        $projectRoot =
            dirname(
                __DIR__,
                3
            );


        $databaseConfig =
            require $projectRoot
            .
            '/config/database.php';


        $backupConfig =
            require $projectRoot
            .
            '/config/backup.php';


        $sourceDatabasePath =
            (string)(
                $databaseConfig['database']
                ??
                ''
            );


        $backupDirectory =
            (string)(
                $backupConfig['directory']
                ??
                $projectRoot
                .
                '/storage/backups'
            );


        $this->backups =
            new DatabaseBackupService(
                Container::db(),
                $sourceDatabasePath,
                $backupDirectory,
                Container::logger(
                    'backup'
                )
            );
    }


    public function name(): string
    {
        return 'backup:create';
    }


    public function description(): string
    {
        return 'Create and verify a timestamped SQLite database backup.';
    }


    public function execute(
        array $arguments = []
    ): int
    {
        try {

            $result =
                $this->backups->create();


            echo
                'Database backup created successfully.'
                .
                PHP_EOL;


            echo
                'File: '
                .
                $result['filename']
                .
                PHP_EOL;


            echo
                'Path: '
                .
                $result['path']
                .
                PHP_EOL;


            echo
                'Size: '
                .
                number_format(
                    (int)$result['size_bytes']
                )
                .
                ' bytes'
                .
                PHP_EOL;


            echo
                'Integrity check: '
                .
                $result['integrity']
                .
                PHP_EOL;


            echo
                'Created: '
                .
                $result['created_at']
                .
                PHP_EOL;


            echo
                'Duration: '
                .
                number_format(
                    (float)$result['duration_milliseconds'],
                    2
                )
                .
                ' ms'
                .
                PHP_EOL;


            return 0;

        } catch (Throwable $exception) {

            fwrite(
                STDERR,
                'Backup creation failed: '
                .
                $exception->getMessage()
                .
                PHP_EOL
            );


            return 1;
        }
    }
}
