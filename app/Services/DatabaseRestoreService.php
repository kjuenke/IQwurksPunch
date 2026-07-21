<?php
declare(strict_types=1);

namespace App\Services;

use App\Logging\LoggerInterface;
use PDO;
use RuntimeException;
use Throwable;

final class DatabaseRestoreService
{
    private const REQUIRED_CORE_TABLES = [
        'employees',
        'migrations',
        'punches',
        'settings',
        'users',
    ];


    private string $databasePath;

    private string $backupDirectory;

    private string $migrationDirectory;

    private string $lockFile;

    private MaintenanceModeService $maintenance;

    private LoggerInterface $logger;

    private LoggerInterface $backupLogger;


    public function __construct(
        string $databasePath,
        string $backupDirectory,
        string $migrationDirectory,
        string $lockFile,
        MaintenanceModeService $maintenance,
        LoggerInterface $logger,
        LoggerInterface $backupLogger
    )
    {
        $this->databasePath =
            $databasePath;


        $this->backupDirectory =
            rtrim(
                $backupDirectory,
                DIRECTORY_SEPARATOR
            );


        $this->migrationDirectory =
            rtrim(
                $migrationDirectory,
                DIRECTORY_SEPARATOR
            );


        $this->lockFile =
            $lockFile;


        $this->maintenance =
            $maintenance;


        $this->logger =
            $logger;


        $this->backupLogger =
            $backupLogger;
    }


    /**
     * @return array<string,mixed>
     */
    public function preview(
        string $filename
    ): array
    {
        $verification =
            new DatabaseBackupVerificationService(
                $this->backupDirectory,
                $this->logger
            );


        $backup =
            $verification->verify(
                $filename
            );


        $candidate =
            $this->inspectCandidate(
                (string)$backup['path']
            );


        $ready =
            (bool)$backup['valid']
            &&
            (bool)$candidate['required_tables_valid']
            &&
            (bool)$candidate['migrations']['complete'];


        return [
            'ready' =>
                $ready,

            'backup' =>
                $backup,

            'required_tables_valid' =>
                $candidate['required_tables_valid'],

            'tables' =>
                $candidate['tables'],

            'missing_core_tables' =>
                $candidate['missing_core_tables'],

            'migrations' =>
                $candidate['migrations']
        ];
    }


    /**
     * @return array<string,mixed>
     */
    public function restore(
        string $filename
    ): array
    {
        $startedAt =
            microtime(
                true
            );


        $lockHandle =
            null;


        $stagePath =
            null;


        $rollbackPath =
            null;


        $activeDatabaseMoved =
            false;


        $restoreCommitted =
            false;


        $rollbackCompleted =
            false;


        $maintenanceStatus =
            $this->maintenance->status();


        $maintenanceWasActive =
            (bool)(
                $maintenanceStatus['active']
                ??
                false
            );


        try {

            $lockHandle =
                $this->acquireLock();


            if ($lockHandle === false) {

                throw new RuntimeException(
                    'Another backup or restore process is already running.'
                );
            }


            $this->writeLockMetadata(
                $lockHandle,
                $filename
            );


            if (!$maintenanceWasActive) {

                $this->maintenance->activate(
                    'Database restore: '
                    .
                    $filename
                );
            }


            $this->logger->warning(
                'Database restore started.',
                [
                    'backup_filename' =>
                        $filename,

                    'database_path' =>
                        $this->databasePath,

                    'process_id' =>
                        getmypid(),

                    'maintenance_was_already_active' =>
                        $maintenanceWasActive
                ]
            );


            $preview =
                $this->preview(
                    $filename
                );


            if (!$preview['ready']) {

                throw new RuntimeException(
                    'The selected backup did not pass restore validation.'
                );
            }


            $activeDatabasePath =
                $this->resolveActiveDatabasePath();


            $activeMode =
                fileperms(
                    $activeDatabasePath
                );


            $activeMode =
                $activeMode === false
                    ? 0640
                    : $activeMode
                    &
                    0777;


            $sourceDatabase =
                $this->openWritableDatabase(
                    $activeDatabasePath
                );


            $backupService =
                new DatabaseBackupService(
                    $sourceDatabase,
                    $activeDatabasePath,
                    $this->backupDirectory,
                    $this->backupLogger
                );


            $safetyBackup =
                $backupService->create();


            $this->checkpointDatabase(
                $sourceDatabase
            );


            unset(
                $backupService,
                $sourceDatabase
            );


            gc_collect_cycles();


            $stagePath =
                $this->temporaryDatabasePath(
                    'restore'
                );


            $this->copyDatabaseFile(
                (string)$preview['backup']['path'],
                $stagePath
            );


            @chmod(
                $stagePath,
                $activeMode
            );


            $stagedHealth =
                $this->inspectInstallableDatabase(
                    $stagePath
                );


            if (!$stagedHealth['healthy']) {

                throw new RuntimeException(
                    'The staged restore database failed validation: '
                    .
                    implode(
                        ' | ',
                        $stagedHealth['errors']
                    )
                );
            }


            $rollbackPath =
                $this->temporaryDatabasePath(
                    'rollback'
                );


            $this->removeDatabaseSidecars(
                $activeDatabasePath
            );


            if (
                !rename(
                    $activeDatabasePath,
                    $rollbackPath
                )
            ) {
                throw new RuntimeException(
                    'The active database could not be moved to the rollback location.'
                );
            }


            $activeDatabaseMoved =
                true;


            if (
                !rename(
                    $stagePath,
                    $activeDatabasePath
                )
            ) {
                if (
                    !rename(
                        $rollbackPath,
                        $activeDatabasePath
                    )
                ) {
                    throw new RuntimeException(
                        'The restored database could not be installed, and the original database could not be reinstated.'
                    );
                }


                $activeDatabaseMoved =
                    false;


                throw new RuntimeException(
                    'The restored database could not be installed. The original database was reinstated.'
                );
            }


            $stagePath =
                null;


            @chmod(
                $activeDatabasePath,
                $activeMode
            );


            clearstatcache(
                true,
                $activeDatabasePath
            );


            $postRestore =
                $this->inspectInstallableDatabase(
                    $activeDatabasePath
                );


            if (!$postRestore['healthy']) {

                throw new RuntimeException(
                    'The restored database failed its post-installation checks: '
                    .
                    implode(
                        ' | ',
                        $postRestore['errors']
                    )
                );
            }


            $restoreCommitted =
                true;


            $activeDatabaseMoved =
                false;


            $rollbackRemoved =
                false;


            if (
                $rollbackPath !== null
                &&
                is_file(
                    $rollbackPath
                )
            ) {
                $rollbackRemoved =
                    unlink(
                        $rollbackPath
                    );


                if (!$rollbackRemoved) {

                    $this->logger->warning(
                        'The temporary rollback database could not be removed after a successful restore.',
                        [
                            'rollback_path' =>
                                $rollbackPath
                        ]
                    );
                }
            }


            if (!$maintenanceWasActive) {

                $this->maintenance->deactivate();
            }


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


            $result = [
                'restored_filename' =>
                    $filename,

                'restored_path' =>
                    $activeDatabasePath,

                'safety_backup_filename' =>
                    $safetyBackup['filename'],

                'safety_backup_path' =>
                    $safetyBackup['path'],

                'safety_backup_integrity' =>
                    $safetyBackup['integrity'],

                'post_restore_healthy' =>
                    $postRestore['healthy'],

                'post_restore_integrity' =>
                    $postRestore['integrity_valid']
                        ? 'ok'
                        : 'failed',

                'post_restore_foreign_key_violations' =>
                    $postRestore['foreign_key_violation_count'],

                'post_restore_migrations' =>
                    $postRestore['migrations'],

                'rollback_file_removed' =>
                    $rollbackRemoved,

                'maintenance_remains_active' =>
                    $maintenanceWasActive,

                'completed_at' =>
                    date(
                        'Y-m-d H:i:s T'
                    ),

                'duration_milliseconds' =>
                    $durationMilliseconds
            ];


            $this->logger->warning(
                'Database restore completed successfully.',
                $result
            );


            return $result;

        } catch (Throwable $exception) {

            $rollbackError =
                null;


            if (
                $activeDatabaseMoved
                &&
                $rollbackPath !== null
                &&
                is_file(
                    $rollbackPath
                )
            ) {
                try {

                    if (
                        is_file(
                            $this->databasePath
                        )
                        &&
                        !unlink(
                            $this->databasePath
                        )
                    ) {
                        throw new RuntimeException(
                            'The failed restored database could not be removed.'
                        );
                    }


                    $this->removeDatabaseSidecars(
                        $this->databasePath
                    );


                    if (
                        !rename(
                            $rollbackPath,
                            $this->databasePath
                        )
                    ) {
                        throw new RuntimeException(
                            'The original database could not be restored from the rollback file.'
                        );
                    }


                    clearstatcache(
                        true,
                        $this->databasePath
                    );


                    $rollbackHealth =
                        $this->inspectInstallableDatabase(
                            $this->databasePath
                        );


                    if (!$rollbackHealth['healthy']) {

                        throw new RuntimeException(
                            'The original database was reinstalled but did not pass validation: '
                            .
                            implode(
                                ' | ',
                                $rollbackHealth['errors']
                            )
                        );
                    }


                    $rollbackCompleted =
                        true;

                } catch (Throwable $rollbackException) {

                    $rollbackError =
                        $rollbackException->getMessage();
                }
            }


            if (
                $stagePath !== null
                &&
                is_file(
                    $stagePath
                )
            ) {
                @unlink(
                    $stagePath
                );
            }


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
                'Database restore failed.',
                [
                    'backup_filename' =>
                        $filename,

                    'database_path' =>
                        $this->databasePath,

                    'restore_committed' =>
                        $restoreCommitted,

                    'rollback_completed' =>
                        $rollbackCompleted,

                    'rollback_error' =>
                        $rollbackError,

                    'maintenance_active' =>
                        $this->maintenance->isActive(),

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


            $message =
                'Database restore failed: '
                .
                $exception->getMessage();


            if ($rollbackCompleted) {

                $message .=
                    ' The original database was restored successfully.';
            }


            if ($rollbackError !== null) {

                $message .=
                    ' Automatic rollback also failed: '
                    .
                    $rollbackError;
            }


            $message .=
                ' Maintenance mode remains active for safety.';


            throw new RuntimeException(
                $message,
                0,
                $exception
            );

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
     * @return array<string,mixed>
     */
    private function inspectCandidate(
        string $databasePath
    ): array
    {
        $database =
            $this->openReadOnlyDatabase(
                $databasePath
            );


        $tables =
            $this->databaseTables(
                $database
            );


        $missingCoreTables =
            array_values(
                array_diff(
                    self::REQUIRED_CORE_TABLES,
                    $tables
                )
            );


        return [
            'tables' =>
                $tables,

            'missing_core_tables' =>
                $missingCoreTables,

            'required_tables_valid' =>
                $missingCoreTables
                ===
                [],

            'migrations' =>
                $this->migrationStatus(
                    $database
                )
        ];
    }


    /**
     * @return array<string,mixed>
     */
    private function inspectInstallableDatabase(
        string $databasePath
    ): array
    {
        $healthService =
            new DatabaseHealthService(
                $databasePath,
                $this->logger
            );


        $health =
            $healthService->inspect();


        try {

            $database =
                $this->openReadOnlyDatabase(
                    $databasePath
                );


            $migrations =
                $this->migrationStatus(
                    $database
                );

        } catch (Throwable $exception) {

            $migrations = [
                'complete' =>
                    false,

                'expected_count' =>
                    0,

                'executed_count' =>
                    0,

                'missing' =>
                    [],

                'unknown' =>
                    [],

                'error' =>
                    $exception->getMessage()
            ];
        }


        $errors =
            $health['errors'];


        if (!$migrations['complete']) {

            $errors[] =
                'The restored database migration history does not match the installed application.';
        }


        return [
            ...$health,

            'healthy' =>
                $health['healthy']
                &&
                $migrations['complete'],

            'errors' =>
                array_values(
                    array_unique(
                        $errors
                    )
                ),

            'migrations' =>
                $migrations
        ];
    }


    /**
     * @return array{
     *     complete:bool,
     *     expected_count:int,
     *     executed_count:int,
     *     missing:array<int,string>,
     *     unknown:array<int,string>
     * }
     */
    private function migrationStatus(
        PDO $database
    ): array
    {
        $migrationFiles =
            glob(
                $this->migrationDirectory
                .
                '/*.php'
            );


        if ($migrationFiles === false) {

            throw new RuntimeException(
                'The application migration directory could not be scanned.'
            );
        }


        sort(
            $migrationFiles
        );


        $expected =
            array_values(
                array_map(
                    static fn (
                        string $path
                    ): string =>
                        pathinfo(
                            basename(
                                $path
                            ),
                            PATHINFO_FILENAME
                        ),
                    $migrationFiles
                )
            );


        $statement =
            $database->query(
                '
                SELECT migration
                FROM migrations
                ORDER BY migration
                '
            );


        if ($statement === false) {

            throw new RuntimeException(
                'The database migration history could not be read.'
            );
        }


        $executed =
            array_values(
                array_unique(
                    array_map(
                        static fn (
                            mixed $migration
                        ): string =>
                            pathinfo(
                                basename(
                                    trim(
                                        (string)$migration
                                    )
                                ),
                                PATHINFO_FILENAME
                            ),
                        $statement->fetchAll(
                            PDO::FETCH_COLUMN
                        )
                    )
                )
            );


        sort(
            $executed
        );


        $missing =
            array_values(
                array_diff(
                    $expected,
                    $executed
                )
            );


        $unknown =
            array_values(
                array_diff(
                    $executed,
                    $expected
                )
            );


        return [
            'complete' =>
                $missing
                ===
                []
                &&
                $unknown
                ===
                [],

            'expected_count' =>
                count(
                    $expected
                ),

            'executed_count' =>
                count(
                    $executed
                ),

            'missing' =>
                $missing,

            'unknown' =>
                $unknown
        ];
    }


    /**
     * @return array<int,string>
     */
    private function databaseTables(
        PDO $database
    ): array
    {
        $statement =
            $database->query(
                "
                SELECT name
                FROM sqlite_master
                WHERE type = 'table'
                  AND name NOT LIKE 'sqlite_%'
                ORDER BY name
                "
            );


        if ($statement === false) {

            throw new RuntimeException(
                'The database table list could not be read.'
            );
        }


        return
            array_values(
                array_map(
                    static fn (
                        mixed $table
                    ): string =>
                        (string)$table,
                    $statement->fetchAll(
                        PDO::FETCH_COLUMN
                    )
                )
            );
    }


    private function resolveActiveDatabasePath(): string
    {
        if (
            !is_file(
                $this->databasePath
            )
        ) {
            throw new RuntimeException(
                'The active database does not exist: '
                .
                $this->databasePath
            );
        }


        if (
            !is_readable(
                $this->databasePath
            )
            ||
            !is_writable(
                $this->databasePath
            )
        ) {
            throw new RuntimeException(
                'The active database must be readable and writable.'
            );
        }


        if (
            !is_writable(
                dirname(
                    $this->databasePath
                )
            )
        ) {
            throw new RuntimeException(
                'The active database directory is not writable.'
            );
        }


        $realPath =
            realpath(
                $this->databasePath
            );


        if ($realPath === false) {

            throw new RuntimeException(
                'The active database path could not be resolved.'
            );
        }


        return $realPath;
    }


    private function openWritableDatabase(
        string $databasePath
    ): PDO
    {
        $database =
            new PDO(
                'sqlite:'
                .
                $databasePath
            );


        $database->setAttribute(
            PDO::ATTR_ERRMODE,
            PDO::ERRMODE_EXCEPTION
        );


        $database->setAttribute(
            PDO::ATTR_DEFAULT_FETCH_MODE,
            PDO::FETCH_ASSOC
        );


        $database->exec(
            'PRAGMA busy_timeout = 10000'
        );


        return $database;
    }


    private function openReadOnlyDatabase(
        string $databasePath
    ): PDO
    {
        $database =
            new PDO(
                'sqlite:'
                .
                $databasePath
            );


        $database->setAttribute(
            PDO::ATTR_ERRMODE,
            PDO::ERRMODE_EXCEPTION
        );


        $database->setAttribute(
            PDO::ATTR_DEFAULT_FETCH_MODE,
            PDO::FETCH_ASSOC
        );


        $database->exec(
            'PRAGMA query_only = ON'
        );


        return $database;
    }


    private function checkpointDatabase(
        PDO $database
    ): void
    {
        $statement =
            $database->query(
                'PRAGMA wal_checkpoint(TRUNCATE)'
            );


        if ($statement === false) {

            throw new RuntimeException(
                'The active database WAL checkpoint could not be executed.'
            );
        }


        $result =
            $statement->fetch(
                PDO::FETCH_NUM
            );


        if (
            is_array(
                $result
            )
            &&
            isset(
                $result[0]
            )
            &&
            (int)$result[0]
            !==
            0
        ) {
            throw new RuntimeException(
                'The active database is busy and could not be checkpointed safely.'
            );
        }
    }


    private function copyDatabaseFile(
        string $sourcePath,
        string $destinationPath
    ): void
    {
        $source =
            fopen(
                $sourcePath,
                'rb'
            );


        if ($source === false) {

            throw new RuntimeException(
                'The selected backup could not be opened for reading.'
            );
        }


        $destination =
            fopen(
                $destinationPath,
                'xb'
            );


        if ($destination === false) {

            fclose(
                $source
            );


            throw new RuntimeException(
                'The staged restore database could not be created.'
            );
        }


        try {

            $bytesCopied =
                stream_copy_to_stream(
                    $source,
                    $destination
                );


            if ($bytesCopied === false) {

                throw new RuntimeException(
                    'The selected backup could not be copied into the staging area.'
                );
            }


            fflush(
                $destination
            );


            if (
                function_exists(
                    'fsync'
                )
            ) {
                fsync(
                    $destination
                );
            }

        } catch (Throwable $exception) {

            fclose(
                $source
            );


            fclose(
                $destination
            );


            @unlink(
                $destinationPath
            );


            throw $exception;
        }


        fclose(
            $source
        );


        fclose(
            $destination
        );


        $sourceSize =
            filesize(
                $sourcePath
            );


        $destinationSize =
            filesize(
                $destinationPath
            );


        if (
            $sourceSize === false
            ||
            $destinationSize === false
            ||
            $sourceSize <= 0
            ||
            $sourceSize !== $destinationSize
        ) {
            @unlink(
                $destinationPath
            );


            throw new RuntimeException(
                'The staged restore database size does not match the selected backup.'
            );
        }
    }


    private function temporaryDatabasePath(
        string $purpose
    ): string
    {
        $directory =
            dirname(
                $this->databasePath
            );


        $filename =
            pathinfo(
                $this->databasePath,
                PATHINFO_FILENAME
            );


        return
            $directory
            .
            DIRECTORY_SEPARATOR
            .
            '.'
            .
            $filename
            .
            '.'
            .
            $purpose
            .
            '.'
            .
            date(
                'Ymd_His'
            )
            .
            '.'
            .
            bin2hex(
                random_bytes(
                    4
                )
            )
            .
            '.sqlite';
    }


    private function removeDatabaseSidecars(
        string $databasePath
    ): void
    {
        foreach (
            [
                $databasePath
                .
                '-wal',

                $databasePath
                .
                '-shm',

                $databasePath
                .
                '-journal'
            ]
            as $sidecar
        ) {
            if (
                is_file(
                    $sidecar
                )
                &&
                !unlink(
                    $sidecar
                )
            ) {
                throw new RuntimeException(
                    'A stale SQLite sidecar file could not be removed: '
                    .
                    $sidecar
                );
            }
        }
    }


    /**
     * @return resource|false
     */
    private function acquireLock(): mixed
    {
        $directory =
            dirname(
                $this->lockFile
            );


        if (
            !is_dir(
                $directory
            )
        ) {
            $created =
                mkdir(
                    $directory,
                    0770,
                    true
                );


            if (
                !$created
                &&
                !is_dir(
                    $directory
                )
            ) {
                throw new RuntimeException(
                    'The restore lock directory could not be created.'
                );
            }
        }


        if (
            !is_writable(
                $directory
            )
        ) {
            throw new RuntimeException(
                'The restore lock directory is not writable.'
            );
        }


        $handle =
            fopen(
                $this->lockFile,
                'c+'
            );


        if ($handle === false) {

            throw new RuntimeException(
                'The backup and restore lock file could not be opened.'
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
        mixed $lockHandle,
        string $filename
    ): void
    {
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
                        'backup:restore',

                    'backup_filename' =>
                        $filename,

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
