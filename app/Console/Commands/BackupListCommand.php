<?php
declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\CommandInterface;
use App\Services\DatabaseBackupCatalogService;
use Throwable;

final class BackupListCommand implements CommandInterface
{
    private DatabaseBackupCatalogService $backups;


    public function __construct(
        ?DatabaseBackupCatalogService $backups = null
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


        $backupConfig =
            require $projectRoot
            .
            '/config/backup.php';


        $backupDirectory =
            (string)(
                $backupConfig['directory']
                ??
                $projectRoot
                .
                '/storage/backups'
            );


        $this->backups =
            new DatabaseBackupCatalogService(
                $backupDirectory
            );
    }


    public function name(): string
    {
        return 'backup:list';
    }


    public function description(): string
    {
        return 'List available SQLite database backups.';
    }


    public function execute(
        array $arguments = []
    ): int
    {
        try {

            $backups =
                $this->backups->all();


            echo
                'Backup directory: '
                .
                $this->backups->directory()
                .
                PHP_EOL;


            if ($backups === []) {

                echo
                    'No database backups are available.'
                    .
                    PHP_EOL;


                return 0;
            }


            echo
                'Available backups: '
                .
                count(
                    $backups
                )
                .
                PHP_EOL
                .
                PHP_EOL;


            echo
                str_pad(
                    '#',
                    5
                )
                .
                str_pad(
                    'Created',
                    27
                )
                .
                str_pad(
                    'Size',
                    14
                )
                .
                'Filename'
                .
                PHP_EOL;


            echo
                str_repeat(
                    '-',
                    90
                )
                .
                PHP_EOL;


            $totalBytes = 0;


            foreach (
                $backups
                as $index =>
                $backup
            ) {
                $sizeBytes =
                    (int)$backup['size_bytes'];


                $totalBytes +=
                    $sizeBytes;


                echo
                    str_pad(
                        (string)(
                            $index
                            +
                            1
                        ),
                        5
                    )
                    .
                    str_pad(
                        (string)$backup['modified_at'],
                        27
                    )
                    .
                    str_pad(
                        $this->formatBytes(
                            $sizeBytes
                        ),
                        14
                    )
                    .
                    (string)$backup['filename']
                    .
                    PHP_EOL;
            }


            echo
                PHP_EOL
                .
                'Total backup storage: '
                .
                $this->formatBytes(
                    $totalBytes
                )
                .
                PHP_EOL;


            return 0;

        } catch (Throwable $exception) {

            fwrite(
                STDERR,
                'Backup listing failed: '
                .
                $exception->getMessage()
                .
                PHP_EOL
            );


            return 1;
        }
    }


    private function formatBytes(
        int $bytes
    ): string
    {
        if ($bytes < 1024) {

            return
                number_format(
                    $bytes
                )
                .
                ' B';
        }


        $kilobytes =
            $bytes
            /
            1024;


        if ($kilobytes < 1024) {

            return
                number_format(
                    $kilobytes,
                    2
                )
                .
                ' KB';
        }


        $megabytes =
            $kilobytes
            /
            1024;


        if ($megabytes < 1024) {

            return
                number_format(
                    $megabytes,
                    2
                )
                .
                ' MB';
        }


        return
            number_format(
                $megabytes
                /
                1024,
                2
            )
            .
            ' GB';
    }
}
