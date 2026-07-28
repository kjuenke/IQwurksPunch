<?php
declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\CommandInterface;
use App\Core\Container;
use App\Logging\LoggerInterface;
use App\Services\DatabaseBackupCatalogService;
use App\Services\DatabaseBackupRetentionService;
use App\Services\DatabaseBackupService;
use App\Services\OperationalFailureNotificationService;
use RuntimeException;
use Throwable;

final class BackupRunCommand implements CommandInterface
{
    private DatabaseBackupService $backups;

    private DatabaseBackupRetentionService $retention;

    private LoggerInterface $logger;

    private int $retentionCount;

    private string $lockFile;


    public function __construct()
    {
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


        $this->retentionCount =
            (int)(
                $backupConfig['retention_count']
                ??
                30
            );


        $this->lockFile =
            (string)(
                $backupConfig['lock_file']
                ??
                $projectRoot
                .
                '/storage/cache/backup.lock'
            );


        $this->logger =
            Container::logger(
                'backup'
            );


        $catalog =
            new DatabaseBackupCatalogService(
                $backupDirectory
            );


        $this->backups =
            new DatabaseBackupService(
                Container::db(),
                $sourceDatabasePath,
                $backupDirectory,
                $this->logger
            );


        $this->retention =
            new DatabaseBackupRetentionService(
                $catalog,
                $this->logger
            );
    }


    public function name(): string
    {
        return 'backup:run';
    }


    public function description(): string
    {
        return 'Create a verified backup and apply retention safely.';
    }


    public function execute(
        array $arguments = []
    ): int
    {
        if ($arguments !== []) {

            fwrite(
                STDERR,
                'The backup:run command does not accept arguments.'
                .
                PHP_EOL
            );


            return 1;
        }


        $startedAt =
            microtime(
                true
            );


        $lockHandle =
            null;


        try {

            $lockHandle =
                $this->acquireLock();


            if ($lockHandle === false) {

                $this->logger->warning(
                    'Automatic backup skipped because another backup process is running.',
                    [
                        'command' =>
                            $this->name(),

                        'lock_file' =>
                            $this->lockFile,

                        'process_id' =>
                            getmypid()
                    ]
                );


                echo
                    'Another backup process is already running. Nothing was changed.'
                    .
                    PHP_EOL;


                return 0;
            }


            $this->writeLockMetadata(
                $lockHandle
            );


            $this->logger->info(
                'Automatic backup maintenance started.',
                [
                    'command' =>
                        $this->name(),

                    'process_id' =>
                        getmypid(),

                    'retention_count' =>
                        $this->retentionCount,

                    'timezone' =>
                        date_default_timezone_get(),

                    'php_version' =>
                        PHP_VERSION
                ]
            );


            echo
                'Creating database backup...'
                .
                PHP_EOL;


            $backup =
                $this->backups->create();


            echo
                'Backup created: '
                .
                $backup['filename']
                .
                PHP_EOL;


            echo
                'Integrity check: '
                .
                $backup['integrity']
                .
                PHP_EOL;


            echo
                'Applying retention policy...'
                .
                PHP_EOL;


            $retention =
                $this->retention->prune(
                    $this->retentionCount
                );


            echo
                'Retention target: '
                .
                $this->retentionCount
                .
                PHP_EOL;


            echo
                'Expired backups deleted: '
                .
                $retention['deleted_count']
                .
                PHP_EOL;


            echo
                'Failed deletions: '
                .
                $retention['failed_count']
                .
                PHP_EOL;


            $durationMilliseconds =
                round(
                    (
                        microtime(
                            true
                        )
                        -
                        $startedAt
                    )
                    *
                    1000,
                    2
                );


            if (
                $retention['failed_count']
                >
                0
            ) {
                $this->logger->error(
                    'Automatic backup completed with retention failures.',
                    [
                        'backup_filename' =>
                            $backup['filename'],

                        'backup_size_bytes' =>
                            $backup['size_bytes'],

                        'retention_count' =>
                            $this->retentionCount,

                        'deleted_count' =>
                            $retention['deleted_count'],

                        'failed_count' =>
                            $retention['failed_count'],

                        'duration_milliseconds' =>
                            $durationMilliseconds
                    ]
                );


                fwrite(
                    STDERR,
                    'The backup was created, but one or more expired backups could not be deleted.'
                    .
                    PHP_EOL
                );


                $this->notifyOperationalFailure(
                    'Automatic Database Backup',
                    'A verified database backup was created, but retention cleanup failed.',
                    [
                        'command' =>
                            $this->name(),

                        'backup_filename' =>
                            $backup['filename']
                            ??
                            null,

                        'backup_path' =>
                            $backup['path']
                            ??
                            null,

                        'backup_size_bytes' =>
                            $backup['size_bytes']
                            ??
                            null,

                        'backup_integrity' =>
                            $backup['integrity']
                            ??
                            null,

                        'retention_count' =>
                            $this->retentionCount,

                        'deleted_count' =>
                            $retention['deleted_count']
                            ??
                            null,

                        'failed_count' =>
                            $retention['failed_count']
                            ??
                            null,

                        'retention_result' =>
                            $retention,

                        'duration_milliseconds' =>
                            $durationMilliseconds,

                        'exit_code' =>
                            1
                    ]
                );


                return 1;
            }


            $this->logger->info(
                'Automatic backup maintenance completed successfully.',
                [
                    'backup_filename' =>
                        $backup['filename'],

                    'backup_path' =>
                        $backup['path'],

                    'backup_size_bytes' =>
                        $backup['size_bytes'],

                    'integrity' =>
                        $backup['integrity'],

                    'retention_count' =>
                        $this->retentionCount,

                    'deleted_count' =>
                        $retention['deleted_count'],

                    'duration_milliseconds' =>
                        $durationMilliseconds
                ]
            );


            echo
                'Backup maintenance completed successfully.'
                .
                PHP_EOL;


            echo
                'Duration: '
                .
                number_format(
                    $durationMilliseconds,
                    2
                )
                .
                ' ms'
                .
                PHP_EOL;


            return 0;

        } catch (Throwable $exception) {

            $durationMilliseconds =
                round(
                    (
                        microtime(
                            true
                        )
                        -
                        $startedAt
                    )
                    *
                    1000,
                    2
                );


            $this->logger->critical(
                'Automatic backup maintenance failed.',
                [
                    'command' =>
                        $this->name(),

                    'process_id' =>
                        getmypid(),

                    'exception_class' =>
                        $exception::class,

                    'exception_message' =>
                        $exception->getMessage(),

                    'exception_file' =>
                        $exception->getFile(),

                    'exception_line' =>
                        $exception->getLine(),

                    'duration_milliseconds' =>
                        $durationMilliseconds
                ]
            );


            fwrite(
                STDERR,
                'Backup maintenance failed: '
                .
                $exception->getMessage()
                .
                PHP_EOL
            );


            $this->notifyOperationalFailure(
                'Automatic Database Backup',
                'Automatic database backup maintenance failed unexpectedly.',
                [
                    'command' =>
                        $this->name(),

                    'process_id' =>
                        getmypid(),

                    'lock_file' =>
                        $this->lockFile,

                    'retention_count' =>
                        $this->retentionCount,

                    'exception_class' =>
                        $exception::class,

                    'exception_message' =>
                        $exception->getMessage(),

                    'exception_file' =>
                        $exception->getFile(),

                    'exception_line' =>
                        $exception->getLine(),

                    'duration_milliseconds' =>
                        $durationMilliseconds,

                    'exit_code' =>
                        1
                ]
            );


            return 1;

        } finally {

            if (
                is_resource(
                    $lockHandle
                )
            ) {
                flock(
                    $lockHandle,
                    LOCK_UN
                );


                fclose(
                    $lockHandle
                );
            }
        }
    }


    /**
     * Operational notification failures must never replace the original
     * backup failure or alter its exit code.
     *
     * @param array<string,mixed> $details
     */
    private function notifyOperationalFailure(
        string $source,
        string $summary,
        array $details
    ): void
    {
        try {

            $notifications =
                new OperationalFailureNotificationService();


            $sent =
                $notifications->sendFailure(
                    $source,
                    $summary,
                    $details
                );


            if (!$sent) {

                fwrite(
                    STDERR,
                    'Warning: the operational failure notification could not be delivered.'
                    .
                    PHP_EOL
                );
            }

        } catch (Throwable $notificationException) {

            fwrite(
                STDERR,
                'Warning: the operational failure notification could not be delivered: '
                .
                $notificationException->getMessage()
                .
                PHP_EOL
            );
        }
    }


    /**
     * @return resource|false
     */
    private function acquireLock(): mixed
    {
        $lockDirectory =
            dirname(
                $this->lockFile
            );


        if (
            !is_dir(
                $lockDirectory
            )
        ) {
            $created =
                mkdir(
                    $lockDirectory,
                    0770,
                    true
                );


            if (
                !$created
                &&
                !is_dir(
                    $lockDirectory
                )
            ) {
                throw new RuntimeException(
                    'The backup lock directory could not be created: '
                    .
                    $lockDirectory
                );
            }
        }


        if (
            !is_writable(
                $lockDirectory
            )
        ) {
            throw new RuntimeException(
                'The backup lock directory is not writable: '
                .
                $lockDirectory
            );
        }


        $handle =
            fopen(
                $this->lockFile,
                'c+'
            );


        if ($handle === false) {

            throw new RuntimeException(
                'The backup lock file could not be opened: '
                .
                $this->lockFile
            );
        }


        if (
            !flock(
                $handle,
                LOCK_EX
                |
                LOCK_NB
            )
        ) {
            fclose(
                $handle
            );


            return false;
        }


        return $handle;
    }


    /**
     * @param resource $lockHandle
     */
    private function writeLockMetadata(
        mixed $lockHandle
    ): void
    {
        if (
            !is_resource(
                $lockHandle
            )
        ) {
            return;
        }


        ftruncate(
            $lockHandle,
            0
        );


        rewind(
            $lockHandle
        );


        fwrite(
            $lockHandle,
            json_encode(
                [
                    'command' =>
                        $this->name(),

                    'process_id' =>
                        getmypid(),

                    'started_at' =>
                        date(
                            DATE_ATOM
                        )
                ],
                JSON_PRETTY_PRINT
                |
                JSON_UNESCAPED_SLASHES
            )
            .
            PHP_EOL
        );


        fflush(
            $lockHandle
        );
    }
}
