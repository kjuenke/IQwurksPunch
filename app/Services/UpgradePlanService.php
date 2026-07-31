<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;
use Throwable;

final class UpgradePlanService
{
    private string $projectRoot;

    private UpgradeReadinessService $readiness;


    public function __construct(
        ?string $projectRoot = null,
        ?UpgradeReadinessService $readiness = null
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
                'The upgrade-plan project root cannot be empty.'
            );
        }


        $this->projectRoot =
            $projectRoot;


        $this->readiness =
            $readiness
            ??
            new UpgradeReadinessService(
                $projectRoot
            );
    }


    /**
     * @return array<string,mixed>
     */
    public function plan(): array
    {
        $startedAt =
            microtime(
                true
            );


        $checks =
            $this->readiness->check();


        $summary =
            $this->summarizeReadiness(
                $checks
            );


        $planningErrors = [];


        try {

            $pendingMigrations =
                $this->pendingMigrations();

        } catch (Throwable $exception) {

            $pendingMigrations = [];


            $planningErrors[] =
                'Pending migrations could not be determined: '
                .
                $exception->getMessage();
        }


        try {

            $maintenanceActive =
                $this->maintenanceActive();

        } catch (Throwable $exception) {

            $maintenanceActive =
                false;


            $planningErrors[] =
                'Maintenance status could not be determined: '
                .
                $exception->getMessage();
        }


        try {

            $newestBackup =
                $this->newestBackup();

        } catch (Throwable $exception) {

            $newestBackup =
                null;


            $planningErrors[] =
                'Backup metadata could not be determined: '
                .
                $exception->getMessage();
        }


        $version =
            $this->applicationVersion();


        $blocked =
            $summary['failure_count']
            >
            0
            ||
            $planningErrors !== [];


        $overallStatus =
            $blocked
                ? UpgradeReadinessService::FAIL
                : (
                    $summary['warning_count'] > 0
                        ? UpgradeReadinessService::WARN
                        : UpgradeReadinessService::PASS
                );


        $stages =
            $this->stages(
                $version,
                $blocked,
                $maintenanceActive,
                $pendingMigrations,
                $newestBackup
            );


        return [
            'generated_at' =>
                date(
                    'Y-m-d H:i:s T'
                ),

            'application_version' =>
                $version,

            'overall_status' =>
                $overallStatus,

            'blocked' =>
                $blocked,

            'can_begin' =>
                !$blocked,

            'pass_count' =>
                $summary['pass_count'],

            'warning_count' =>
                $summary['warning_count'],

            'failure_count' =>
                $summary['failure_count']
                +
                count(
                    $planningErrors
                ),

            'readiness_checks' =>
                $checks,

            'planning_errors' =>
                $planningErrors,

            'maintenance_active' =>
                $maintenanceActive,

            'pending_migrations' =>
                $pendingMigrations,

            'pending_migration_count' =>
                count(
                    $pendingMigrations
                ),

            'newest_backup' =>
                $newestBackup,

            'stages' =>
                $stages,

            'stage_count' =>
                count(
                    $stages
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
     * @param array<int,array<string,mixed>> $checks
     *
     * @return array{
     *     pass_count:int,
     *     warning_count:int,
     *     failure_count:int
     * }
     */
    private function summarizeReadiness(
        array $checks
    ): array
    {
        $passCount = 0;

        $warningCount = 0;

        $failureCount = 0;


        foreach ($checks as $check) {

            $status =
                strtoupper(
                    trim(
                        (string)(
                            $check['status']
                            ??
                            ''
                        )
                    )
                );


            if (
                $status
                ===
                UpgradeReadinessService::PASS
            ) {
                $passCount++;


                continue;
            }


            if (
                $status
                ===
                UpgradeReadinessService::WARN
            ) {
                $warningCount++;


                continue;
            }


            $failureCount++;
        }


        return [
            'pass_count' =>
                $passCount,

            'warning_count' =>
                $warningCount,

            'failure_count' =>
                $failureCount
        ];
    }


    /**
     * @param array<int,string> $pendingMigrations
     * @param array<string,mixed>|null $newestBackup
     *
     * @return array<int,array<string,mixed>>
     */
    private function stages(
        string $version,
        bool $blocked,
        bool $maintenanceActive,
        array $pendingMigrations,
        ?array $newestBackup
    ): array
    {
        $backupReference =
            $newestBackup === null
                ? 'No existing backup was found.'
                : 'Current newest backup: '
                    .
                    (
                        $newestBackup['filename']
                        ??
                        'unknown'
                    )
                    .
                    ' ('
                    .
                    (
                        $newestBackup['modified_at']
                        ??
                        'unknown date'
                    )
                    .
                    ')';


        $migrationDetails =
            $pendingMigrations === []
                ? [
                    'No unapplied migrations are currently waiting.',
                    'The migration command remains safe to run because recorded migrations are skipped.'
                ]
                : [
                    'Pending migration count: '
                    .
                    count(
                        $pendingMigrations
                    ),

                    'Pending: '
                    .
                    implode(
                        ', ',
                        $pendingMigrations
                    )
                ];


        return [
            $this->stage(
                1,
                'preflight',
                'Pre-upgrade validation',
                $blocked
                    ? 'BLOCKED'
                    : 'READY',
                'Confirm there are no hard upgrade-readiness failures.',
                [
                    './iqwurks upgrade:check'
                ],
                [
                    $blocked
                        ? 'Resolve every FAIL result before proceeding.'
                        : 'No hard readiness blockers were detected.'
                ]
            ),

            $this->stage(
                2,
                'maintenance',
                'Maintenance protection',
                $maintenanceActive
                    ? 'READY'
                    : 'REQUIRED',
                'Prevent kiosk and supervisor writes while the upgrade is applied.',
                [
                    './iqwurks maintenance:on IQwurksPunch upgrade to '
                    .
                    $version,

                    './iqwurks maintenance:status'
                ],
                [
                    $maintenanceActive
                        ? 'Maintenance mode is already active.'
                        : 'Maintenance mode must be enabled before any upgrade changes.'
                ]
            ),

            $this->stage(
                3,
                'backup',
                'Pre-upgrade safety backup',
                'REQUIRED',
                'Create and verify a dedicated rollback backup immediately before changing dependencies or the database.',
                [
                    './iqwurks backup:create',
                    './iqwurks backup:verify'
                ],
                [
                    $backupReference,
                    'Record the exact filename created for this upgrade.'
                ]
            ),

            $this->stage(
                4,
                'dependencies',
                'Composer dependencies',
                'REQUIRED',
                'Install the dependency versions recorded in composer.lock.',
                [
                    'composer install --no-interaction --prefer-dist --optimize-autoloader'
                ],
                [
                    'The lock file must remain authoritative.',
                    'Do not run composer update during a production upgrade.'
                ]
            ),

            $this->stage(
                5,
                'migrations',
                'Database migrations',
                $pendingMigrations === []
                    ? 'READY'
                    : 'PENDING',
                'Apply each migration not already recorded in the database.',
                [
                    'php migrate.php',
                    'php migrate.php status'
                ],
                $migrationDetails
            ),

            $this->stage(
                6,
                'permissions',
                'Ownership and permissions',
                'REQUIRED',
                'Restore validated runtime ownership, setgid directory permissions, and executable entry points.',
                [
                    'chown -R www-data:www-data database/sqlite storage/backups storage/cache storage/exports storage/logs storage/sessions',

                    'chmod 2770 database/sqlite storage/backups storage/cache storage/exports storage/logs storage/sessions',

                    'chmod 0750 iqwurks migrate.php',

                    'find public -type d -exec chmod 0755 {} \;',

                    'find public -type f -exec chmod 0644 {} \;'
                ],
                [
                    'Runtime directories must remain writable by www-data.',
                    'Public static files must remain readable by Nginx.'
                ]
            ),

            $this->stage(
                7,
                'validation',
                'Post-upgrade validation',
                'REQUIRED',
                'Confirm application, database, scheduler, backup, and automated-test health.',
                [
                    './iqwurks install:check',
                    './iqwurks database:check',
                    './iqwurks scheduler:check',
                    './iqwurks doctor',
                    'php vendor/bin/phpunit'
                ],
                [
                    'Do not return the kiosk to service while any diagnostic reports FAIL.',
                    'Run PHPUnit when development dependencies are installed.'
                ]
            ),

            $this->stage(
                8,
                'complete',
                'Return to service',
                'REQUIRED',
                'Create a post-upgrade recovery point and disable maintenance mode only after validation succeeds.',
                [
                    './iqwurks backup:create',
                    './iqwurks backup:verify',
                    './iqwurks maintenance:off',
                    './iqwurks maintenance:status'
                ],
                [
                    'Preserve both the pre-upgrade and post-upgrade backup filenames.',
                    'Confirm the kiosk and supervisor login after maintenance mode is disabled.'
                ]
            ),

            $this->stage(
                9,
                'rollback',
                'Rollback reference',
                'AVAILABLE',
                'Restore the matching pre-upgrade application release and verified database backup if the upgrade cannot be accepted.',
                [
                    './iqwurks backup:restore PRE_UPGRADE_BACKUP_FILENAME'
                ],
                [
                    'The displayed backup:restore command is a preview until the supported apply confirmation is supplied.',
                    'Application code and database migration state must remain compatible.',
                    'Leave maintenance mode active throughout rollback.'
                ]
            )
        ];
    }


    /**
     * @param array<int,string> $commands
     * @param array<int,string> $details
     *
     * @return array<string,mixed>
     */
    private function stage(
        int $number,
        string $id,
        string $name,
        string $status,
        string $action,
        array $commands,
        array $details
    ): array
    {
        return [
            'number' =>
                $number,

            'id' =>
                $id,

            'name' =>
                $name,

            'status' =>
                $status,

            'action' =>
                $action,

            'commands' =>
                array_values(
                    $commands
                ),

            'details' =>
                array_values(
                    $details
                )
        ];
    }


    private function applicationVersion(): string
    {
        $path =
            $this->path(
                'VERSION'
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
            return 'unknown';
        }


        $contents =
            file_get_contents(
                $path
            );


        if ($contents === false) {
            return 'unknown';
        }


        $version =
            trim(
                $contents
            );


        return
            $version === ''
                ? 'unknown'
                : $version;
    }


    /**
     * @return array<int,string>
     */
    private function pendingMigrations(): array
    {
        $migrationPaths =
            glob(
                $this->path(
                    'database/migrations/*.php'
                )
            );


        if ($migrationPaths === false) {
            throw new RuntimeException(
                'The migration directory could not be scanned.'
            );
        }


        $available =
            array_values(
                array_map(
                    static fn (
                        string $path
                    ): string =>
                        basename(
                            $path
                        ),
                    $migrationPaths
                )
            );


        sort(
            $available
        );


        $database =
            $this->openDatabase(
                $this->databasePath()
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
                'Recorded migrations could not be queried.'
            );
        }


        $recorded =
            array_values(
                array_map(
                    static fn (
                        mixed $migration
                    ): string =>
                        basename(
                            trim(
                                (string)$migration
                            )
                        ),
                    $statement->fetchAll(
                        PDO::FETCH_COLUMN
                    )
                )
            );


        return
            array_values(
                array_diff(
                    $available,
                    $recorded
                )
            );
    }


    private function maintenanceActive(): bool
    {
        $configuration =
            $this->loadConfiguration(
                'maintenance.php'
            );


        $path =
            trim(
                (string)(
                    $configuration['file']
                    ??
                    ''
                )
            );


        if ($path === '') {
            throw new RuntimeException(
                'The maintenance-mode file is not configured.'
            );
        }


        return
            is_file(
                $path
            );
    }


    /**
     * @return array<string,mixed>|null
     */
    private function newestBackup(): ?array
    {
        $configuration =
            $this->loadConfiguration(
                'backup.php'
            );


        $directory =
            trim(
                (string)(
                    $configuration['directory']
                    ??
                    ''
                )
            );


        if (
            $directory === ''
            ||
            !is_dir(
                $directory
            )
        ) {
            return null;
        }


        $paths =
            glob(
                rtrim(
                    $directory,
                    DIRECTORY_SEPARATOR
                )
                .
                DIRECTORY_SEPARATOR
                .
                '*.sqlite'
            );


        if (
            $paths === false
            ||
            $paths === []
        ) {
            return null;
        }


        usort(
            $paths,
            static function (
                string $left,
                string $right
            ): int {
                return
                    (
                        filemtime(
                            $right
                        )
                        ?:
                        0
                    )
                    <=>
                    (
                        filemtime(
                            $left
                        )
                        ?:
                        0
                    );
            }
        );


        $path =
            $paths[0];


        $modifiedTimestamp =
            filemtime(
                $path
            );


        $size =
            filesize(
                $path
            );


        return [
            'filename' =>
                basename(
                    $path
                ),

            'path' =>
                $path,

            'modified_timestamp' =>
                $modifiedTimestamp === false
                    ? null
                    : $modifiedTimestamp,

            'modified_at' =>
                $modifiedTimestamp === false
                    ? 'unknown'
                    : date(
                        'Y-m-d H:i:s T',
                        $modifiedTimestamp
                    ),

            'size_bytes' =>
                $size === false
                    ? 0
                    : (int)$size
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


    private function databasePath(): string
    {
        $configuration =
            $this->loadConfiguration(
                'database.php'
            );


        if (
            (
                $configuration['driver']
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


        $path =
            trim(
                (string)(
                    $configuration['database']
                    ??
                    ''
                )
            );


        if ($path === '') {
            throw new RuntimeException(
                'The SQLite database path is not configured.'
            );
        }


        return $path;
    }


    private function openDatabase(
        string $path
    ): PDO
    {
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
                'The configured SQLite database is missing or unreadable.'
            );
        }


        $database =
            new PDO(
                'sqlite:'
                .
                $path
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
