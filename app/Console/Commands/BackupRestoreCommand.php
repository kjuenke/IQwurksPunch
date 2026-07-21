<?php
declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\CommandInterface;
use App\Core\Container;
use App\Services\DatabaseRestoreService;
use App\Services\MaintenanceModeService;
use InvalidArgumentException;
use Throwable;

final class BackupRestoreCommand implements CommandInterface
{
    private DatabaseRestoreService $restore;


    public function __construct(
        ?DatabaseRestoreService $restore = null
    )
    {
        if ($restore !== null) {

            $this->restore =
                $restore;


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


        $maintenanceConfig =
            require $projectRoot
            .
            '/config/maintenance.php';


        $databasePath =
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


        $lockFile =
            (string)(
                $backupConfig['lock_file']
                ??
                $projectRoot
                .
                '/storage/cache/backup.lock'
            );


        $maintenance =
            new MaintenanceModeService(
                (string)$maintenanceConfig['file']
            );


        $this->restore =
            new DatabaseRestoreService(
                $databasePath,
                $backupDirectory,
                $projectRoot
                .
                '/database/migrations',
                $lockFile,
                $maintenance,
                Container::logger(
                    'restore'
                ),
                Container::logger(
                    'backup'
                )
            );
    }


    public function name(): string
    {
        return 'backup:restore';
    }


    public function description(): string
    {
        return 'Preview or safely restore a verified SQLite backup.';
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


            $filename =
                $options['filename'];


            $preview =
                $this->restore->preview(
                    $filename
                );


            $this->displayPreview(
                $preview
            );


            if (!$preview['ready']) {

                fwrite(
                    STDERR,
                    PHP_EOL
                    .
                    'The selected backup is not safe to restore.'
                    .
                    PHP_EOL
                );


                return 1;
            }


            if (!$options['apply']) {

                echo
                    PHP_EOL
                    .
                    'Preview only. The active database was not changed.'
                    .
                    PHP_EOL;


                echo
                    'To apply this restore, run:'
                    .
                    PHP_EOL;


                echo
                    '  ./iqwurks backup:restore '
                    .
                    escapeshellarg(
                        $filename
                    )
                    .
                    ' --apply --confirm='
                    .
                    escapeshellarg(
                        $filename
                    )
                    .
                    PHP_EOL;


                return 0;
            }


            echo
                PHP_EOL
                .
                'Restore confirmation accepted.'
                .
                PHP_EOL;


            echo
                'Entering protected restore workflow...'
                .
                PHP_EOL;


            $result =
                $this->restore->restore(
                    $filename
                );


            echo
                PHP_EOL
                .
                'Database restore completed successfully.'
                .
                PHP_EOL;


            echo
                'Restored backup: '
                .
                $result['restored_filename']
                .
                PHP_EOL;


            echo
                'Active database: '
                .
                $result['restored_path']
                .
                PHP_EOL;


            echo
                'Safety backup: '
                .
                $result['safety_backup_filename']
                .
                PHP_EOL;


            echo
                'Safety backup integrity: '
                .
                $result['safety_backup_integrity']
                .
                PHP_EOL;


            echo
                'Post-restore integrity: '
                .
                $result['post_restore_integrity']
                .
                PHP_EOL;


            echo
                'Foreign-key violations: '
                .
                $result['post_restore_foreign_key_violations']
                .
                PHP_EOL;


            echo
                'Migration history: '
                .
                (
                    $result['post_restore_migrations']['complete']
                        ? 'complete'
                        : 'incomplete'
                )
                .
                PHP_EOL;


            echo
                'Maintenance mode: '
                .
                (
                    $result['maintenance_remains_active']
                        ? 'remains active'
                        : 'disabled'
                )
                .
                PHP_EOL;


            echo
                'Completed: '
                .
                $result['completed_at']
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
                'Restore command failed: '
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
     *     filename:string,
     *     apply:bool,
     *     confirm:?string
     * }
     */
    private function parseArguments(
        array $arguments
    ): array
    {
        $filename =
            null;


        $apply =
            false;


        $confirm =
            null;


        foreach ($arguments as $argument) {

            $argument =
                trim(
                    (string)$argument
                );


            if ($argument === '--apply') {

                $apply =
                    true;


                continue;
            }


            if (
                str_starts_with(
                    $argument,
                    '--confirm='
                )
            ) {
                $confirm =
                    substr(
                        $argument,
                        strlen(
                            '--confirm='
                        )
                    );


                continue;
            }


            if (
                str_starts_with(
                    $argument,
                    '--'
                )
            ) {
                throw new InvalidArgumentException(
                    'Unknown restore argument: '
                    .
                    $argument
                );
            }


            if ($filename !== null) {

                throw new InvalidArgumentException(
                    'Only one backup filename may be supplied.'
                );
            }


            $filename =
                $argument;
        }


        if (
            $filename === null
            ||
            $filename === ''
        ) {
            throw new InvalidArgumentException(
                'A backup filename is required. Run ./iqwurks backup:list to view available backups.'
            );
        }


        if (
            $confirm !== null
            &&
            !$apply
        ) {
            throw new InvalidArgumentException(
                '--confirm may only be used together with --apply.'
            );
        }


        if ($apply) {

            if (
                $confirm === null
                ||
                $confirm === ''
            ) {
                throw new InvalidArgumentException(
                    'Applying a restore requires --confirm=BACKUP_FILENAME.'
                );
            }


            if ($confirm !== $filename) {

                throw new InvalidArgumentException(
                    'The --confirm value must exactly match the selected backup filename.'
                );
            }
        }


        return [
            'filename' =>
                $filename,

            'apply' =>
                $apply,

            'confirm' =>
                $confirm
        ];
    }


    /**
     * @param array<string,mixed> $preview
     */
    private function displayPreview(
        array $preview
    ): void
    {
        $backup =
            $preview['backup'];


        $migrations =
            $preview['migrations'];


        echo
            'IQwurksPunch Database Restore Preview'
            .
            PHP_EOL;


        echo
            str_repeat(
                '=',
                42
            )
            .
            PHP_EOL;


        echo
            'Status: '
            .
            (
                $preview['ready']
                    ? 'READY'
                    : 'BLOCKED'
            )
            .
            PHP_EOL;


        echo
            'Backup: '
            .
            $backup['filename']
            .
            PHP_EOL;


        echo
            'Path: '
            .
            $backup['path']
            .
            PHP_EOL;


        echo
            'Modified: '
            .
            $backup['modified_at']
            .
            PHP_EOL;


        echo
            'Size: '
            .
            number_format(
                (int)$backup['size_bytes']
            )
            .
            ' bytes'
            .
            PHP_EOL;


        echo
            'Integrity check: '
            .
            (
                $backup['integrity_valid']
                    ? 'ok'
                    : 'failed'
            )
            .
            PHP_EOL;


        echo
            'Foreign-key violations: '
            .
            $backup['foreign_key_violation_count']
            .
            PHP_EOL;


        echo
            'Required core tables: '
            .
            (
                $preview['required_tables_valid']
                    ? 'present'
                    : 'missing'
            )
            .
            PHP_EOL;


        echo
            'Migration history: '
            .
            (
                $migrations['complete']
                    ? 'complete'
                    : 'incomplete'
            )
            .
            PHP_EOL;


        echo
            'Expected migrations: '
            .
            $migrations['expected_count']
            .
            PHP_EOL;


        echo
            'Recorded migrations: '
            .
            $migrations['executed_count']
            .
            PHP_EOL;


        if (
            $preview['missing_core_tables']
            !==
            []
        ) {
            echo
                PHP_EOL
                .
                'Missing core tables:'
                .
                PHP_EOL;


            foreach (
                $preview['missing_core_tables']
                as $table
            ) {
                echo
                    '  - '
                    .
                    $table
                    .
                    PHP_EOL;
            }
        }


        if (
            $migrations['missing']
            !==
            []
        ) {
            echo
                PHP_EOL
                .
                'Missing migrations:'
                .
                PHP_EOL;


            foreach (
                $migrations['missing']
                as $migration
            ) {
                echo
                    '  - '
                    .
                    $migration
                    .
                    PHP_EOL;
            }
        }


        if (
            $migrations['unknown']
            !==
            []
        ) {
            echo
                PHP_EOL
                .
                'Unknown migrations:'
                .
                PHP_EOL;


            foreach (
                $migrations['unknown']
                as $migration
            ) {
                echo
                    '  - '
                    .
                    $migration
                    .
                    PHP_EOL;
            }
        }
    }
}
