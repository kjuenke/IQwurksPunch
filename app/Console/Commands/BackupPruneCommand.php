<?php
declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\CommandInterface;
use App\Core\Container;
use App\Services\DatabaseBackupCatalogService;
use App\Services\DatabaseBackupRetentionService;
use InvalidArgumentException;
use Throwable;

final class BackupPruneCommand implements CommandInterface
{
    private DatabaseBackupRetentionService $retention;

    private int $defaultKeep;


    public function __construct(
        ?DatabaseBackupRetentionService $retention = null,
        ?int $defaultKeep = null
    )
    {
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


        $this->defaultKeep =
            $defaultKeep
            ??
            (int)(
                $backupConfig['retention_count']
                ??
                30
            );


        $this->retention =
            $retention
            ??
            new DatabaseBackupRetentionService(
                new DatabaseBackupCatalogService(
                    $backupDirectory
                ),
                Container::logger(
                    'backup'
                )
            );
    }


    public function name(): string
    {
        return 'backup:prune';
    }


    public function description(): string
    {
        return 'Preview or delete backups exceeding the retention limit.';
    }


    public function execute(
        array $arguments = []
    ): int
    {
        try {

            $options =
                $this->parseArguments(
                    $arguments
                );


            $keep =
                $options['keep'];


            $delete =
                $options['delete'];


            $plan =
                $this->retention->plan(
                    $keep
                );


            echo
                'Backup directory: '
                .
                $plan['directory']
                .
                PHP_EOL;


            echo
                'Retention target: keep newest '
                .
                $keep
                .
                ' backup'
                .
                (
                    $keep === 1
                        ? ''
                        : 's'
                )
                .
                PHP_EOL;


            echo
                'Available backups: '
                .
                $plan['total_count']
                .
                PHP_EOL;


            echo
                'Backups exceeding retention: '
                .
                $plan['removable_count']
                .
                PHP_EOL;


            echo
                'Recoverable storage: '
                .
                $this->formatBytes(
                    (int)$plan['removable_bytes']
                )
                .
                PHP_EOL;


            if (
                $plan['removable_count']
                ===
                0
            ) {
                echo
                    'No backups need pruning.'
                    .
                    PHP_EOL;


                return 0;
            }


            echo
                PHP_EOL
                .
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


            foreach (
                $plan['removable']
                as $index =>
                $backup
            ) {
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
                            (int)$backup['size_bytes']
                        ),
                        14
                    )
                    .
                    (string)$backup['filename']
                    .
                    PHP_EOL;
            }


            if (!$delete) {

                echo
                    PHP_EOL
                    .
                    'Preview only. No backup files were deleted.'
                    .
                    PHP_EOL;


                echo
                    'To apply this retention plan, run:'
                    .
                    PHP_EOL;


                echo
                    '  ./iqwurks backup:prune --keep='
                    .
                    $keep
                    .
                    ' --delete'
                    .
                    PHP_EOL;


                return 0;
            }


            echo
                PHP_EOL
                .
                'Deleting backups exceeding retention...'
                .
                PHP_EOL;


            $result =
                $this->retention->prune(
                    $keep
                );


            echo
                'Deleted backups: '
                .
                $result['deleted_count']
                .
                PHP_EOL;


            echo
                'Failed deletions: '
                .
                $result['failed_count']
                .
                PHP_EOL;


            if (
                $result['failed_count']
                >
                0
            ) {
                foreach (
                    $result['failed']
                    as $failure
                ) {
                    fwrite(
                        STDERR,
                        '- '
                        .
                        (
                            $failure['filename']
                            ??
                            'Unknown backup'
                        )
                        .
                        ': '
                        .
                        (
                            $failure['error']
                            ??
                            'Unknown error'
                        )
                        .
                        PHP_EOL
                    );
                }


                return 1;
            }


            echo
                'Backup retention completed successfully.'
                .
                PHP_EOL;


            return 0;

        } catch (Throwable $exception) {

            fwrite(
                STDERR,
                'Backup retention failed: '
                .
                $exception->getMessage()
                .
                PHP_EOL
            );


            return 1;
        }
    }


    /**
     * @param array<int,mixed> $arguments
     *
     * @return array{
     *     keep:int,
     *     delete:bool
     * }
     */
    private function parseArguments(
        array $arguments
    ): array
    {
        $keep =
            $this->defaultKeep;


        $delete = false;


        foreach ($arguments as $argument) {

            $argument =
                (string)$argument;


            if ($argument === '--delete') {

                $delete = true;


                continue;
            }


            if (
                preg_match(
                    '/^--keep=(\d+)$/',
                    $argument,
                    $matches
                )
                ===
                1
            ) {
                $keep =
                    (int)$matches[1];


                continue;
            }


            throw new InvalidArgumentException(
                'Unknown argument: '
                .
                $argument
                .
                '. Supported arguments are --keep=N and --delete.'
            );
        }


        if ($keep < 1) {

            throw new InvalidArgumentException(
                'The --keep value must be at least 1.'
            );
        }


        return [
            'keep' =>
                $keep,

            'delete' =>
                $delete
        ];
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
