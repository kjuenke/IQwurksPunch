<?php
declare(strict_types=1);

namespace App\Services;

use App\Logging\LoggerFactory;
use App\Logging\LoggerInterface;
use FilesystemIterator;
use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Throwable;

final class UpgradeExecutionOperationsService implements UpgradeExecutionOperationsInterface
{
    private string $projectRoot;

    private string $databasePath;

    private string $backupDirectory;

    private MaintenanceModeService $maintenance;

    private LoggerInterface $backupLogger;

    private ?string $runtimeOwner;

    private ?string $runtimeGroup;


    public function __construct(
        ?string $projectRoot = null,
        ?MaintenanceModeService $maintenance = null,
        ?LoggerInterface $backupLogger = null,
        ?string $runtimeOwner = 'www-data',
        ?string $runtimeGroup = 'www-data'
    )
    {
        $projectRoot =
            $projectRoot
            ??
            dirname(
                __DIR__,
                2
            );


        $projectRoot =
            rtrim(
                trim(
                    $projectRoot
                ),
                DIRECTORY_SEPARATOR
            );


        if ($projectRoot === '') {
            throw new RuntimeException(
                'The upgrade-operations project root cannot be empty.'
            );
        }


        if (!is_dir($projectRoot)) {
            throw new RuntimeException(
                'The upgrade-operations project root does not exist: '
                .
                $projectRoot
            );
        }


        $resolvedRoot =
            realpath(
                $projectRoot
            );


        if ($resolvedRoot === false) {
            throw new RuntimeException(
                'The upgrade-operations project root could not be resolved.'
            );
        }


        $this->projectRoot =
            $resolvedRoot;


        $databaseConfiguration =
            $this->loadConfiguration(
                'database.php'
            );


        if (
            (
                $databaseConfiguration['driver']
                ??
                null
            )
            !==
            'sqlite'
        ) {
            throw new RuntimeException(
                'The configured database driver is not sqlite.'
            );
        }


        $this->databasePath =
            trim(
                (string)(
                    $databaseConfiguration['database']
                    ??
                    ''
                )
            );


        if ($this->databasePath === '') {
            throw new RuntimeException(
                'The SQLite database path is not configured.'
            );
        }


        $backupConfiguration =
            $this->loadConfiguration(
                'backup.php'
            );


        $this->backupDirectory =
            rtrim(
                trim(
                    (string)(
                        $backupConfiguration['directory']
                        ??
                        ''
                    )
                ),
                DIRECTORY_SEPARATOR
            );


        if ($this->backupDirectory === '') {
            throw new RuntimeException(
                'The database backup directory is not configured.'
            );
        }


        if ($maintenance === null) {

            $maintenanceConfiguration =
                $this->loadConfiguration(
                    'maintenance.php'
                );


            $maintenanceFile =
                trim(
                    (string)(
                        $maintenanceConfiguration['file']
                        ??
                        ''
                    )
                );


            if ($maintenanceFile === '') {
                throw new RuntimeException(
                    'The maintenance-mode file is not configured.'
                );
            }


            $maintenance =
                new MaintenanceModeService(
                    $maintenanceFile
                );
        }


        $this->maintenance =
            $maintenance;


        $this->backupLogger =
            $backupLogger
            ??
            LoggerFactory::create(
                'backup'
            );


        $this->runtimeOwner =
            $this->normalizeOwnershipName(
                $runtimeOwner
            );


        $this->runtimeGroup =
            $this->normalizeOwnershipName(
                $runtimeGroup
            );
    }


    /**
     * @return array<string,mixed>
     */
    public function maintenanceStatus(): array
    {
        return
            $this->maintenance->status();
    }


    /**
     * @return array<string,mixed>
     */
    public function activateMaintenance(
        string $reason
    ): array
    {
        return
            $this->maintenance->activate(
                $reason
            );
    }


    /**
     * @return array<string,mixed>
     */
    public function deactivateMaintenance(): array
    {
        return
            $this->maintenance->deactivate();
    }


    /**
     * @return array<string,mixed>
     */
    public function createVerifiedBackup(): array
    {
        $databasePath =
            $this->resolveDatabasePath();


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
            'PRAGMA foreign_keys = ON'
        );


        $database->exec(
            'PRAGMA busy_timeout = 10000'
        );


        $backups =
            new DatabaseBackupService(
                $database,
                $databasePath,
                $this->backupDirectory,
                $this->backupLogger
            );


        $created =
            $backups->create();


        unset(
            $backups,
            $database
        );


        gc_collect_cycles();


        $verificationService =
            new DatabaseBackupVerificationService(
                $this->backupDirectory,
                $this->backupLogger
            );


        $verification =
            $verificationService->verify(
                (string)$created['filename']
            );


        if (
            (
                $verification['valid']
                ??
                false
            )
            !==
            true
        ) {
            throw new RuntimeException(
                'The newly created upgrade backup did not pass verification.'
            );
        }


        return [
            'filename' =>
                $created['filename'],

            'path' =>
                $created['path'],

            'source_path' =>
                $created['source_path'],

            'size_bytes' =>
                $created['size_bytes'],

            'integrity' =>
                $created['integrity'],

            'created_at' =>
                $created['created_at'],

            'creation_duration_milliseconds' =>
                $created['duration_milliseconds'],

            'verified' =>
                true,

            'verification' =>
                $verification
        ];
    }


    /**
     * @return array<string,mixed>
     */
    public function applyPermissions(): array
    {
        $startedAt =
            microtime(
                true
            );


        $directoryCount = 0;

        $fileCount = 0;

        $runtimeDefinitions = [
            'database/sqlite' =>
                0660,

            'storage/backups' =>
                0640,

            'storage/cache' =>
                0660,

            'storage/exports' =>
                0660,

            'storage/logs' =>
                0660,

            'storage/sessions' =>
                0660
        ];


        foreach (
            $runtimeDefinitions
            as $relativeDirectory => $fileMode
        ) {
            $directory =
                $this->path(
                    $relativeDirectory
                );


            if (!is_dir($directory)) {
                throw new RuntimeException(
                    'A required runtime directory is missing: '
                    .
                    $relativeDirectory
                );
            }


            $this->applyOwnership(
                $directory
            );


            $this->applyMode(
                $directory,
                02770
            );


            $directoryCount++;


            $iterator =
                new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator(
                        $directory,
                        FilesystemIterator::SKIP_DOTS
                    ),
                    RecursiveIteratorIterator::SELF_FIRST
                );


            foreach ($iterator as $item) {

                if (!$item instanceof SplFileInfo) {
                    continue;
                }


                $path =
                    $item->getPathname();


                if ($item->isLink()) {
                    continue;
                }


                $this->applyOwnership(
                    $path
                );


                if ($item->isDir()) {

                    $this->applyMode(
                        $path,
                        02770
                    );


                    $directoryCount++;


                    continue;
                }


                if ($item->isFile()) {

                    $this->applyMode(
                        $path,
                        $fileMode
                    );


                    $fileCount++;
                }
            }
        }


        $publicDirectory =
            $this->path(
                'public'
            );


        if (!is_dir($publicDirectory)) {
            throw new RuntimeException(
                'The public application directory is missing.'
            );
        }


        $this->applyMode(
            $publicDirectory,
            0755
        );


        $directoryCount++;


        $publicIterator =
            new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $publicDirectory,
                    FilesystemIterator::SKIP_DOTS
                ),
                RecursiveIteratorIterator::SELF_FIRST
            );


        foreach ($publicIterator as $item) {

            if (!$item instanceof SplFileInfo) {
                continue;
            }


            if ($item->isLink()) {
                continue;
            }


            if ($item->isDir()) {

                $this->applyMode(
                    $item->getPathname(),
                    0755
                );


                $directoryCount++;


                continue;
            }


            if ($item->isFile()) {

                $this->applyMode(
                    $item->getPathname(),
                    0644
                );


                $fileCount++;
            }
        }


        foreach (
            [
                'iqwurks',
                'migrate.php'
            ]
            as $entryPoint
        ) {
            $path =
                $this->path(
                    $entryPoint
                );


            if (!is_file($path)) {
                throw new RuntimeException(
                    'A required application entry point is missing: '
                    .
                    $entryPoint
                );
            }


            $this->applyMode(
                $path,
                0750
            );


            $fileCount++;
        }


        return [
            'successful' =>
                true,

            'runtime_owner' =>
                $this->runtimeOwner,

            'runtime_group' =>
                $this->runtimeGroup,

            'directory_count' =>
                $directoryCount,

            'file_count' =>
                $fileCount,

            'completed_at' =>
                date(
                    'Y-m-d H:i:s T'
                ),

            'duration_milliseconds' =>
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
                )
        ];
    }


    /**
     * @return array<string,mixed>
     */
    private function loadConfiguration(
        string $filename
    ): array
    {
        $path =
            $this->path(
                'config/'
                .
                $filename
            );


        if (
            !is_file(
                $path
            )
            ||
            !is_readable(
                $path
            )
        ) {
            throw new RuntimeException(
                'Configuration file is missing or unreadable: '
                .
                $filename
            );
        }


        $configuration =
            require $path;


        if (!is_array($configuration)) {
            throw new RuntimeException(
                'Configuration file did not return an array: '
                .
                $filename
            );
        }


        return $configuration;
    }


    private function resolveDatabasePath(): string
    {
        if (
            !is_file(
                $this->databasePath
            )
            ||
            !is_readable(
                $this->databasePath
            )
            ||
            !is_writable(
                $this->databasePath
            )
        ) {
            throw new RuntimeException(
                'The active SQLite database must exist and be readable and writable.'
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
                'The active SQLite database directory is not writable.'
            );
        }


        $resolved =
            realpath(
                $this->databasePath
            );


        if ($resolved === false) {
            throw new RuntimeException(
                'The active SQLite database path could not be resolved.'
            );
        }


        return $resolved;
    }


    private function applyOwnership(
        string $path
    ): void
    {
        if ($this->runtimeOwner !== null) {

            try {

                $changed =
                    chown(
                        $path,
                        $this->runtimeOwner
                    );

            } catch (Throwable $exception) {

                throw new RuntimeException(
                    'Owner could not be applied to '
                    .
                    $path
                    .
                    ': '
                    .
                    $exception->getMessage(),
                    0,
                    $exception
                );
            }


            if (!$changed) {
                throw new RuntimeException(
                    'Owner could not be applied to '
                    .
                    $path
                    .
                    '.'
                );
            }
        }


        if ($this->runtimeGroup !== null) {

            try {

                $changed =
                    chgrp(
                        $path,
                        $this->runtimeGroup
                    );

            } catch (Throwable $exception) {

                throw new RuntimeException(
                    'Group could not be applied to '
                    .
                    $path
                    .
                    ': '
                    .
                    $exception->getMessage(),
                    0,
                    $exception
                );
            }


            if (!$changed) {
                throw new RuntimeException(
                    'Group could not be applied to '
                    .
                    $path
                    .
                    '.'
                );
            }
        }
    }


    private function applyMode(
        string $path,
        int $mode
    ): void
    {
        try {

            $changed =
                chmod(
                    $path,
                    $mode
                );

        } catch (Throwable $exception) {

            throw new RuntimeException(
                'Permissions could not be applied to '
                .
                $path
                .
                ': '
                .
                $exception->getMessage(),
                0,
                $exception
            );
        }


        if (!$changed) {
            throw new RuntimeException(
                'Permissions could not be applied to '
                .
                $path
                .
                '.'
            );
        }
    }


    private function normalizeOwnershipName(
        ?string $name
    ): ?string
    {
        if ($name === null) {
            return null;
        }


        $name =
            trim(
                $name
            );


        return
            $name === ''
                ? null
                : $name;
    }


    private function path(
        string $relativePath
    ): string
    {
        return
            $this->projectRoot
            .
            DIRECTORY_SEPARATOR
            .
            ltrim(
                $relativePath,
                DIRECTORY_SEPARATOR
            );
    }
}
