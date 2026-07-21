<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;
use Throwable;

final class SystemDoctorService
{
    private const PASS = 'PASS';

    private const WARN = 'WARN';

    private const FAIL = 'FAIL';


    private const REQUIRED_EXTENSIONS = [
        'ctype',
        'curl',
        'dom',
        'fileinfo',
        'filter',
        'hash',
        'intl',
        'json',
        'libxml',
        'mbstring',
        'openssl',
        'pdo',
        'pdo_sqlite',
        'session',
        'sqlite3',
        'xml',
        'xmlreader',
        'xmlwriter',
        'zip',
    ];


    private const REQUIRED_CORE_TABLES = [
        'employees',
        'migrations',
        'punches',
        'settings',
        'users',
    ];


    private string $projectRoot;


    public function __construct(
        string $projectRoot
    )
    {
        $this->projectRoot =
            rtrim(
                $projectRoot,
                DIRECTORY_SEPARATOR
            );
    }


    /**
     * @return array<string,mixed>
     */
    public function diagnose(): array
    {
        $startedAt =
            microtime(
                true
            );


        $checks = [];


        $this->checkApplicationVersion(
            $checks
        );


        $this->checkPhpVersion(
            $checks
        );


        $this->checkPhpExtensions(
            $checks
        );


        $this->checkComposerAutoload(
            $checks
        );


        $this->checkConfigurationFiles(
            $checks
        );


        $this->checkApplicationTimezone(
            $checks
        );


        $this->checkMailConfiguration(
            $checks
        );


        $this->checkMailPermissions(
            $checks
        );


        $this->checkRuntimeDirectories(
            $checks
        );


        $this->checkDiskCapacity(
            $checks
        );


        $this->checkMaintenanceMode(
            $checks
        );


        $this->checkActiveDatabase(
            $checks
        );


        $this->checkMigrationHistory(
            $checks
        );


        $this->checkBackups(
            $checks
        );


        $crontab =
            $this->readCrontab();


        $this->checkSchedulerCron(
            $checks,
            $crontab
        );


        $this->checkBackupCron(
            $checks,
            $crontab
        );


        $this->checkSchedulerActivity(
            $checks
        );


        $this->checkMailActivity(
            $checks
        );


        $passCount =
            count(
                array_filter(
                    $checks,
                    static fn (
                        array $check
                    ): bool =>
                        $check['status']
                        ===
                        self::PASS
                )
            );


        $warningCount =
            count(
                array_filter(
                    $checks,
                    static fn (
                        array $check
                    ): bool =>
                        $check['status']
                        ===
                        self::WARN
                )
            );


        $failureCount =
            count(
                array_filter(
                    $checks,
                    static fn (
                        array $check
                    ): bool =>
                        $check['status']
                        ===
                        self::FAIL
                )
            );


        $overallStatus =
            $failureCount > 0
                ? self::FAIL
                : (
                    $warningCount > 0
                        ? self::WARN
                        : self::PASS
                );


        return [
            'overall_status' =>
                $overallStatus,

            'checks' =>
                $checks,

            'pass_count' =>
                $passCount,

            'warning_count' =>
                $warningCount,

            'failure_count' =>
                $failureCount,

            'total_count' =>
                count(
                    $checks
                ),

            'checked_at' =>
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
     * @param array<int,array<string,mixed>> $checks
     */
    private function checkApplicationVersion(
        array &$checks
    ): void
    {
        $path =
            $this->projectRoot
            .
            '/VERSION';


        if (
            !is_file(
                $path
            )
        ) {
            $this->addCheck(
                $checks,
                self::FAIL,
                'Application version',
                'The VERSION file is missing.',
                [
                    $path
                ]
            );


            return;
        }


        if (
            !is_readable(
                $path
            )
        ) {
            $this->addCheck(
                $checks,
                self::FAIL,
                'Application version',
                'The VERSION file is not readable.',
                [
                    $path
                ]
            );


            return;
        }


        $version =
            trim(
                (string)file_get_contents(
                    $path
                )
            );


        if ($version === '') {

            $this->addCheck(
                $checks,
                self::FAIL,
                'Application version',
                'The VERSION file is empty.'
            );


            return;
        }


        $this->addCheck(
            $checks,
            self::PASS,
            'Application version',
            'Installed version is '
            .
            $version
            .
            '.'
        );
    }


    /**
     * @param array<int,array<string,mixed>> $checks
     */
    private function checkPhpVersion(
        array &$checks
    ): void
    {
        $minimum =
            '8.5.0';


        if (
            version_compare(
                PHP_VERSION,
                $minimum,
                '<'
            )
        ) {
            $this->addCheck(
                $checks,
                self::FAIL,
                'PHP version',
                'PHP '
                .
                $minimum
                .
                ' or newer is required.',
                [
                    'Installed: '
                    .
                    PHP_VERSION
                ]
            );


            return;
        }


        $this->addCheck(
            $checks,
            self::PASS,
            'PHP version',
            'PHP '
            .
            PHP_VERSION
            .
            ' satisfies the application requirement.'
        );
    }


    /**
     * @param array<int,array<string,mixed>> $checks
     */
    private function checkPhpExtensions(
        array &$checks
    ): void
    {
        $missing = [];


        foreach (
            self::REQUIRED_EXTENSIONS
            as $extension
        ) {
            if (
                !extension_loaded(
                    $extension
                )
            ) {
                $missing[] =
                    $extension;
            }
        }


        if ($missing !== []) {

            $this->addCheck(
                $checks,
                self::FAIL,
                'PHP extensions',
                'One or more required PHP extensions are missing.',
                [
                    'Missing: '
                    .
                    implode(
                        ', ',
                        $missing
                    )
                ]
            );


            return;
        }


        $this->addCheck(
            $checks,
            self::PASS,
            'PHP extensions',
            'All '
            .
            count(
                self::REQUIRED_EXTENSIONS
            )
            .
            ' required PHP extensions are loaded.'
        );
    }


    /**
     * @param array<int,array<string,mixed>> $checks
     */
    private function checkComposerAutoload(
        array &$checks
    ): void
    {
        $autoload =
            $this->projectRoot
            .
            '/vendor/autoload.php';


        if (
            !is_file(
                $autoload
            )
            ||
            !is_readable(
                $autoload
            )
        ) {
            $this->addCheck(
                $checks,
                self::FAIL,
                'Composer dependencies',
                'Composer autoloading is unavailable.',
                [
                    $autoload,
                    'Run composer install.'
                ]
            );


            return;
        }


        $this->addCheck(
            $checks,
            self::PASS,
            'Composer dependencies',
            'Composer autoloading is available.'
        );
    }


    /**
     * @param array<int,array<string,mixed>> $checks
     */
    private function checkConfigurationFiles(
        array &$checks
    ): void
    {
        $filenames = [
            'app.php',
            'backup.php',
            'database.php',
            'logging.php',
            'maintenance.php',
        ];


        $problems = [];


        foreach ($filenames as $filename) {

            $path =
                $this->projectRoot
                .
                '/config/'
                .
                $filename;


            if (
                !is_file(
                    $path
                )
            ) {
                $problems[] =
                    $filename
                    .
                    ' is missing.';


                continue;
            }


            if (
                !is_readable(
                    $path
                )
            ) {
                $problems[] =
                    $filename
                    .
                    ' is not readable.';


                continue;
            }


            try {

                $configuration =
                    require $path;


                if (!is_array($configuration)) {

                    $problems[] =
                        $filename
                        .
                        ' did not return an array.';
                }

            } catch (Throwable $exception) {

                $problems[] =
                    $filename
                    .
                    ': '
                    .
                    $exception->getMessage();
            }
        }


        if ($problems !== []) {

            $this->addCheck(
                $checks,
                self::FAIL,
                'Configuration files',
                'One or more required configuration files are invalid.',
                $problems
            );


            return;
        }


        $this->addCheck(
            $checks,
            self::PASS,
            'Configuration files',
            'All required application configuration files are readable and valid.'
        );
    }


    /**
     * @param array<int,array<string,mixed>> $checks
     */
    private function checkApplicationTimezone(
        array &$checks
    ): void
    {
        try {

            $configuration =
                $this->loadConfiguration(
                    'app.php'
                );


            $timezone =
                trim(
                    (string)(
                        $configuration['timezone']
                        ??
                        ''
                    )
                );


            if (
                $timezone === ''
                ||
                !in_array(
                    $timezone,
                    timezone_identifiers_list(),
                    true
                )
            ) {
                $this->addCheck(
                    $checks,
                    self::FAIL,
                    'Application timezone',
                    'The configured fallback timezone is invalid.',
                    [
                        'Configured value: '
                        .
                        (
                            $timezone === ''
                                ? '(empty)'
                                : $timezone
                        )
                    ]
                );


                return;
            }


            $this->addCheck(
                $checks,
                self::PASS,
                'Application timezone',
                'Fallback timezone is '
                .
                $timezone
                .
                '.'
            );

        } catch (Throwable $exception) {

            $this->addCheck(
                $checks,
                self::FAIL,
                'Application timezone',
                'The application timezone could not be checked.',
                [
                    $exception->getMessage()
                ]
            );
        }
    }


    /**
     * @param array<int,array<string,mixed>> $checks
     */
    private function checkMailConfiguration(
        array &$checks
    ): void
    {
        $path =
            $this->projectRoot
            .
            '/config/mail.php';


        if (
            !is_file(
                $path
            )
        ) {
            $this->addCheck(
                $checks,
                self::WARN,
                'Mail configuration',
                'Active SMTP configuration is missing.',
                [
                    'Copy config/mail.example.php to config/mail.php and configure it.'
                ]
            );


            return;
        }


        if (
            !is_readable(
                $path
            )
        ) {
            $this->addCheck(
                $checks,
                self::FAIL,
                'Mail configuration',
                'The SMTP configuration file is not readable.'
            );


            return;
        }


        try {

            $configuration =
                require $path;


            if (!is_array($configuration)) {

                throw new RuntimeException(
                    'The mail configuration did not return an array.'
                );
            }


            $required = [
                'host',
                'port',
                'username',
                'password',
                'encryption',
                'from_email',
                'from_name',
            ];


            $problems = [];


            foreach ($required as $key) {

                if (
                    !array_key_exists(
                        $key,
                        $configuration
                    )
                    ||
                    trim(
                        (string)$configuration[$key]
                    )
                    ===
                    ''
                ) {
                    $problems[] =
                        'Missing or empty setting: '
                        .
                        $key;
                }
            }


            $port =
                (int)(
                    $configuration['port']
                    ??
                    0
                );


            if (
                $port < 1
                ||
                $port > 65535
            ) {
                $problems[] =
                    'SMTP port must be between 1 and 65535.';
            }


            $fromEmail =
                trim(
                    (string)(
                        $configuration['from_email']
                        ??
                        ''
                    )
                );


            if (
                $fromEmail !== ''
                &&
                filter_var(
                    $fromEmail,
                    FILTER_VALIDATE_EMAIL
                )
                ===
                false
            ) {
                $problems[] =
                    'The sender email address is invalid.';
            }


            $placeholders = [
                'smtp.example.com',
                'SMTP_USERNAME',
                'SMTP_PASSWORD',
                'timeclock@example.com',
            ];


            foreach ($configuration as $value) {

                if (
                    in_array(
                        trim(
                            (string)$value
                        ),
                        $placeholders,
                        true
                    )
                ) {
                    $problems[] =
                        'The active mail configuration contains example placeholder values.';


                    break;
                }
            }


            if ($problems !== []) {

                $this->addCheck(
                    $checks,
                    self::FAIL,
                    'Mail configuration',
                    'The active SMTP configuration is incomplete or invalid.',
                    array_values(
                        array_unique(
                            $problems
                        )
                    )
                );


                return;
            }


            $this->addCheck(
                $checks,
                self::PASS,
                'Mail configuration',
                'The active SMTP configuration is structurally valid.',
                [
                    'No live SMTP connection was attempted.'
                ]
            );

        } catch (Throwable $exception) {

            $this->addCheck(
                $checks,
                self::FAIL,
                'Mail configuration',
                'The SMTP configuration could not be loaded.',
                [
                    $exception->getMessage()
                ]
            );
        }
    }


    /**
     * @param array<int,array<string,mixed>> $checks
     */
    private function checkMailPermissions(
        array &$checks
    ): void
    {
        $path =
            $this->projectRoot
            .
            '/config/mail.php';


        if (
            !is_file(
                $path
            )
        ) {
            $this->addCheck(
                $checks,
                self::WARN,
                'Mail file permissions',
                'Permission checks were skipped because config/mail.php is missing.'
            );


            return;
        }


        $permissions =
            fileperms(
                $path
            );


        if ($permissions === false) {

            $this->addCheck(
                $checks,
                self::WARN,
                'Mail file permissions',
                'The permissions for config/mail.php could not be read.'
            );


            return;
        }


        $mode =
            sprintf(
                '%04o',
                $permissions
                &
                0777
            );


        $otherAccess =
            (
                $permissions
                &
                0007
            )
            !==
            0;


        $groupWrite =
            (
                $permissions
                &
                0020
            )
            !==
            0;


        if (
            $otherAccess
            ||
            $groupWrite
        ) {
            $this->addCheck(
                $checks,
                self::WARN,
                'Mail file permissions',
                'SMTP credentials are accessible more broadly than recommended.',
                [
                    'Current mode: '
                    .
                    $mode,

                    'Recommended mode: 0600 or 0640',

                    'Suggested command: chmod 640 config/mail.php'
                ]
            );


            return;
        }


        $this->addCheck(
            $checks,
            self::PASS,
            'Mail file permissions',
            'SMTP configuration permissions are appropriately restricted.',
            [
                'Current mode: '
                .
                $mode
            ]
        );
    }


    /**
     * @param array<int,array<string,mixed>> $checks
     */
    private function checkRuntimeDirectories(
        array &$checks
    ): void
    {
        $paths = [
            'database/sqlite' => true,
            'database/migrations' => false,
            'storage/backups' => true,
            'storage/cache' => true,
            'storage/exports' => true,
            'storage/logs' => true,
            'storage/sessions' => true,
        ];


        $problems = [];

        $details = [];


        foreach (
            $paths
            as $relativePath =>
            $mustBeWritable
        ) {
            $path =
                $this->projectRoot
                .
                '/'
                .
                $relativePath;


            if (
                !is_dir(
                    $path
                )
            ) {
                $problems[] =
                    $relativePath
                    .
                    ' is missing.';


                continue;
            }


            if (
                !is_readable(
                    $path
                )
            ) {
                $problems[] =
                    $relativePath
                    .
                    ' is not readable.';
            }


            if (
                $mustBeWritable
                &&
                !is_writable(
                    $path
                )
            ) {
                $problems[] =
                    $relativePath
                    .
                    ' is not writable.';
            }


            $permissions =
                fileperms(
                    $path
                );


            $details[] =
                $relativePath
                .
                ' mode='
                .
                (
                    $permissions === false
                        ? 'unknown'
                        : sprintf(
                            '%04o',
                            $permissions
                            &
                            0777
                        )
                );
        }


        if ($problems !== []) {

            $this->addCheck(
                $checks,
                self::FAIL,
                'Runtime directories',
                'One or more required directories are unavailable.',
                [
                    ...$problems,
                    ...$details
                ]
            );


            return;
        }


        $this->addCheck(
            $checks,
            self::PASS,
            'Runtime directories',
            'All required application directories are available.',
            $details
        );
    }


    /**
     * @param array<int,array<string,mixed>> $checks
     */
    private function checkDiskCapacity(
        array &$checks
    ): void
    {
        $freeBytes =
            disk_free_space(
                $this->projectRoot
            );


        $totalBytes =
            disk_total_space(
                $this->projectRoot
            );


        if (
            $freeBytes === false
            ||
            $totalBytes === false
            ||
            $totalBytes <= 0
        ) {
            $this->addCheck(
                $checks,
                self::WARN,
                'Disk capacity',
                'Available disk capacity could not be determined.'
            );


            return;
        }


        $freePercent =
            (
                $freeBytes
                /
                $totalBytes
            )
            *
            100;


        $details = [
            'Free: '
            .
            $this->formatBytes(
                (int)$freeBytes
            ),

            'Total: '
            .
            $this->formatBytes(
                (int)$totalBytes
            ),

            'Free percentage: '
            .
            number_format(
                $freePercent,
                1
            )
            .
            '%'
        ];


        if ($freeBytes < 500 * 1024 * 1024) {

            $this->addCheck(
                $checks,
                self::FAIL,
                'Disk capacity',
                'Less than 500 MB of disk space remains.',
                $details
            );


            return;
        }


        if (
            $freeBytes < 2 * 1024 * 1024 * 1024
            ||
            $freePercent < 10
        ) {
            $this->addCheck(
                $checks,
                self::WARN,
                'Disk capacity',
                'Available disk capacity is becoming limited.',
                $details
            );


            return;
        }


        $this->addCheck(
            $checks,
            self::PASS,
            'Disk capacity',
            'Available disk capacity is sufficient.',
            $details
        );
    }


    /**
     * @param array<int,array<string,mixed>> $checks
     */
    private function checkMaintenanceMode(
        array &$checks
    ): void
    {
        try {

            $configuration =
                $this->loadConfiguration(
                    'maintenance.php'
                );


            $maintenance =
                new MaintenanceModeService(
                    (string)(
                        $configuration['file']
                        ??
                        ''
                    )
                );


            $status =
                $maintenance->status();


            if (
                (bool)(
                    $status['active']
                    ??
                    false
                )
            ) {
                $this->addCheck(
                    $checks,
                    self::WARN,
                    'Maintenance mode',
                    'Web maintenance mode is active.',
                    [
                        'Reason: '
                        .
                        (
                            $status['reason']
                            ??
                            'Not recorded'
                        ),

                        'Started: '
                        .
                        (
                            $status['started_at_display']
                            ??
                            $status['started_at']
                            ??
                            'Not recorded'
                        )
                    ]
                );


                return;
            }


            $this->addCheck(
                $checks,
                self::PASS,
                'Maintenance mode',
                'Web maintenance mode is inactive.'
            );

        } catch (Throwable $exception) {

            $this->addCheck(
                $checks,
                self::FAIL,
                'Maintenance mode',
                'Maintenance-mode status could not be determined.',
                [
                    $exception->getMessage()
                ]
            );
        }
    }


    /**
     * @param array<int,array<string,mixed>> $checks
     */
    private function checkActiveDatabase(
        array &$checks
    ): void
    {
        try {

            $databasePath =
                $this->databasePath();


            $problems = [];


            if (
                !is_file(
                    $databasePath
                )
            ) {
                throw new RuntimeException(
                    'The configured SQLite database does not exist.'
                );
            }


            if (
                !is_readable(
                    $databasePath
                )
            ) {
                $problems[] =
                    'The database file is not readable.';
            }


            if (
                !is_writable(
                    $databasePath
                )
            ) {
                $problems[] =
                    'The database file is not writable.';
            }


            if (
                !is_writable(
                    dirname(
                        $databasePath
                    )
                )
            ) {
                $problems[] =
                    'The database directory is not writable.';
            }


            $database =
                $this->openDatabase(
                    $databasePath
                );


            $integrityMessages =
                $this->columnValues(
                    $database,
                    'PRAGMA integrity_check'
                );


            $integrityValid =
                count(
                    $integrityMessages
                )
                ===
                1
                &&
                strtolower(
                    trim(
                        (string)$integrityMessages[0]
                    )
                )
                ===
                'ok';


            if (!$integrityValid) {

                $problems[] =
                    'SQLite integrity_check failed.';
            }


            $foreignKeyViolations =
                $this->rows(
                    $database,
                    'PRAGMA foreign_key_check'
                );


            if ($foreignKeyViolations !== []) {

                $problems[] =
                    'Foreign-key violations were detected.';
            }


            $tables =
                $this->columnValues(
                    $database,
                    "
                    SELECT name
                    FROM sqlite_master
                    WHERE type = 'table'
                      AND name NOT LIKE 'sqlite_%'
                    ORDER BY name
                    "
                );


            $tables =
                array_map(
                    static fn (
                        mixed $table
                    ): string =>
                        (string)$table,
                    $tables
                );


            $missingTables =
                array_values(
                    array_diff(
                        self::REQUIRED_CORE_TABLES,
                        $tables
                    )
                );


            if ($missingTables !== []) {

                $problems[] =
                    'Missing core tables: '
                    .
                    implode(
                        ', ',
                        $missingTables
                    );
            }


            $journalMode =
                (string)$this->scalar(
                    $database,
                    'PRAGMA journal_mode'
                );


            $sqliteVersion =
                (string)$this->scalar(
                    $database,
                    'SELECT sqlite_version()'
                );


            $sizeBytes =
                filesize(
                    $databasePath
                );


            $details = [
                'Path: '
                .
                $databasePath,

                'Size: '
                .
                $this->formatBytes(
                    $sizeBytes === false
                        ? 0
                        : (int)$sizeBytes
                ),

                'SQLite version: '
                .
                $sqliteVersion,

                'Journal mode: '
                .
                $journalMode,

                'Application tables: '
                .
                count(
                    $tables
                ),

                'Foreign-key violations: '
                .
                count(
                    $foreignKeyViolations
                )
            ];


            if ($problems !== []) {

                $this->addCheck(
                    $checks,
                    self::FAIL,
                    'Active database',
                    'The active SQLite database failed one or more required checks.',
                    [
                        ...$problems,
                        ...$details
                    ]
                );


                return;
            }


            if (
                strtolower(
                    $journalMode
                )
                !==
                'wal'
            ) {
                $this->addCheck(
                    $checks,
                    self::WARN,
                    'Active database',
                    'The database passed required checks, but journal mode is not WAL.',
                    $details
                );


                return;
            }


            $this->addCheck(
                $checks,
                self::PASS,
                'Active database',
                'The active SQLite database is healthy.',
                $details
            );

        } catch (Throwable $exception) {

            $this->addCheck(
                $checks,
                self::FAIL,
                'Active database',
                'The active SQLite database could not be checked.',
                [
                    $exception->getMessage()
                ]
            );
        }
    }


    /**
     * @param array<int,array<string,mixed>> $checks
     */
    private function checkMigrationHistory(
        array &$checks
    ): void
    {
        try {

            $migrationFiles =
                glob(
                    $this->projectRoot
                    .
                    '/database/migrations/*.php'
                );


            if ($migrationFiles === false) {

                throw new RuntimeException(
                    'The migration directory could not be scanned.'
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


            $database =
                $this->openDatabase(
                    $this->databasePath()
                );


            $executedValues =
                $this->columnValues(
                    $database,
                    '
                    SELECT migration
                    FROM migrations
                    ORDER BY migration
                    '
                );


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
                            $executedValues
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


            $details = [
                'Migration files: '
                .
                count(
                    $expected
                ),

                'Recorded migrations: '
                .
                count(
                    $executed
                )
            ];


            if ($missing !== []) {

                $details[] =
                    'Missing: '
                    .
                    implode(
                        ', ',
                        $missing
                    );
            }


            if ($unknown !== []) {

                $details[] =
                    'Unknown: '
                    .
                    implode(
                        ', ',
                        $unknown
                    );
            }


            if (
                $missing !== []
                ||
                $unknown !== []
            ) {
                $this->addCheck(
                    $checks,
                    self::FAIL,
                    'Migration history',
                    'The database migration history does not match the installed application.',
                    $details
                );


                return;
            }


            $this->addCheck(
                $checks,
                self::PASS,
                'Migration history',
                'All installed migration files are recorded in the database.',
                $details
            );

        } catch (Throwable $exception) {

            $this->addCheck(
                $checks,
                self::FAIL,
                'Migration history',
                'Migration history could not be checked.',
                [
                    $exception->getMessage()
                ]
            );
        }
    }


    /**
     * @param array<int,array<string,mixed>> $checks
     */
    private function checkBackups(
        array &$checks
    ): void
    {
        try {

            $configuration =
                $this->loadConfiguration(
                    'backup.php'
                );


            $directory =
                rtrim(
                    (string)(
                        $configuration['directory']
                        ??
                        ''
                    ),
                    DIRECTORY_SEPARATOR
                );


            if (
                $directory === ''
                ||
                !is_dir(
                    $directory
                )
            ) {
                throw new RuntimeException(
                    'The configured backup directory does not exist.'
                );
            }


            $paths =
                glob(
                    $directory
                    .
                    '/*.sqlite'
                );


            if ($paths === false) {

                throw new RuntimeException(
                    'The backup directory could not be scanned.'
                );
            }


            $paths =
                array_values(
                    array_filter(
                        $paths,
                        static fn (
                            string $path
                        ): bool =>
                            is_file(
                                $path
                            )
                    )
                );


            if ($paths === []) {

                $this->addCheck(
                    $checks,
                    self::WARN,
                    'Database backups',
                    'No SQLite backups are currently available.',
                    [
                        'Directory: '
                        .
                        $directory
                    ]
                );


                return;
            }


            usort(
                $paths,
                static fn (
                    string $left,
                    string $right
                ): int =>
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
                    )
            );


            $newest =
                $paths[0];


            $database =
                $this->openDatabase(
                    $newest
                );


            $integrity =
                $this->columnValues(
                    $database,
                    'PRAGMA integrity_check'
                );


            $integrityValid =
                count(
                    $integrity
                )
                ===
                1
                &&
                strtolower(
                    trim(
                        (string)$integrity[0]
                    )
                )
                ===
                'ok';


            $foreignKeys =
                $this->rows(
                    $database,
                    'PRAGMA foreign_key_check'
                );


            if (
                !$integrityValid
                ||
                $foreignKeys !== []
            ) {
                $this->addCheck(
                    $checks,
                    self::FAIL,
                    'Database backups',
                    'The newest database backup failed verification.',
                    [
                        'Backup: '
                        .
                        basename(
                            $newest
                        ),

                        'Integrity: '
                        .
                        (
                            $integrityValid
                                ? 'ok'
                                : 'failed'
                        ),

                        'Foreign-key violations: '
                        .
                        count(
                            $foreignKeys
                        )
                    ]
                );


                return;
            }


            $newestTimestamp =
                filemtime(
                    $newest
                );


            if ($newestTimestamp === false) {

                throw new RuntimeException(
                    'The newest backup modification time could not be read.'
                );
            }


            $ageSeconds =
                max(
                    0,
                    time()
                    -
                    $newestTimestamp
                );


            $totalBytes =
                array_sum(
                    array_map(
                        static fn (
                            string $path
                        ): int =>
                            (int)(
                                filesize(
                                    $path
                                )
                                ?:
                                0
                            ),
                        $paths
                    )
                );


            $details = [
                'Available backups: '
                .
                count(
                    $paths
                ),

                'Newest: '
                .
                basename(
                    $newest
                ),

                'Newest age: '
                .
                $this->formatAge(
                    $ageSeconds
                ),

                'Total storage: '
                .
                $this->formatBytes(
                    $totalBytes
                ),

                'Retention target: '
                .
                (int)(
                    $configuration['retention_count']
                    ??
                    0
                )
            ];


            if ($ageSeconds > 48 * 60 * 60) {

                $this->addCheck(
                    $checks,
                    self::WARN,
                    'Database backups',
                    'The newest verified database backup is more than 48 hours old.',
                    $details
                );


                return;
            }


            $this->addCheck(
                $checks,
                self::PASS,
                'Database backups',
                'A recent verified database backup is available.',
                $details
            );

        } catch (Throwable $exception) {

            $this->addCheck(
                $checks,
                self::FAIL,
                'Database backups',
                'Database backups could not be checked.',
                [
                    $exception->getMessage()
                ]
            );
        }
    }


    /**
     * @param array<int,array<string,mixed>> $checks
     * @param array<string,mixed> $crontab
     */
    private function checkSchedulerCron(
        array &$checks,
        array $crontab
    ): void
    {
        if (!$crontab['available']) {

            $this->addCheck(
                $checks,
                self::WARN,
                'Scheduler cron',
                'The current crontab could not be inspected.',
                [
                    $crontab['message']
                ]
            );


            return;
        }


        if (
            str_contains(
                $crontab['contents'],
                'schedule:run'
            )
        ) {
            $this->addCheck(
                $checks,
                self::PASS,
                'Scheduler cron',
                'A schedule:run cron entry is installed.'
            );


            return;
        }


        $this->addCheck(
            $checks,
            self::WARN,
            'Scheduler cron',
            'No schedule:run cron entry was found.'
        );
    }


    /**
     * @param array<int,array<string,mixed>> $checks
     * @param array<string,mixed> $crontab
     */
    private function checkBackupCron(
        array &$checks,
        array $crontab
    ): void
    {
        if (!$crontab['available']) {

            $this->addCheck(
                $checks,
                self::WARN,
                'Automatic backup cron',
                'The current crontab could not be inspected.',
                [
                    $crontab['message']
                ]
            );


            return;
        }


        if (
            str_contains(
                $crontab['contents'],
                'backup:run'
            )
        ) {
            $this->addCheck(
                $checks,
                self::PASS,
                'Automatic backup cron',
                'A backup:run cron entry is installed.'
            );


            return;
        }


        $this->addCheck(
            $checks,
            self::WARN,
            'Automatic backup cron',
            'No automatic backup:run cron entry was found.',
            [
                'Manual backups work, but scheduled backups are not yet configured.'
            ]
        );
    }


    /**
     * @param array<int,array<string,mixed>> $checks
     */
    private function checkSchedulerActivity(
        array &$checks
    ): void
    {
        $path =
            $this->projectRoot
            .
            '/storage/logs/scheduler.log';


        if (
            !is_file(
                $path
            )
        ) {
            $this->addCheck(
                $checks,
                self::WARN,
                'Scheduler activity',
                'The scheduler log does not exist.'
            );


            return;
        }


        $timestamp =
            filemtime(
                $path
            );


        $size =
            filesize(
                $path
            );


        if ($timestamp === false) {

            $this->addCheck(
                $checks,
                self::WARN,
                'Scheduler activity',
                'The scheduler log modification time could not be read.'
            );


            return;
        }


        $ageSeconds =
            max(
                0,
                time()
                -
                $timestamp
            );


        $details = [
            'Last activity: '
            .
            date(
                'Y-m-d H:i:s T',
                $timestamp
            ),

            'Age: '
            .
            $this->formatAge(
                $ageSeconds
            ),

            'Log size: '
            .
            $this->formatBytes(
                $size === false
                    ? 0
                    : (int)$size
            )
        ];


        if ($ageSeconds > 10 * 60) {

            $this->addCheck(
                $checks,
                self::WARN,
                'Scheduler activity',
                'No scheduler log activity has been recorded in the last 10 minutes.',
                $details
            );


            return;
        }


        $this->addCheck(
            $checks,
            self::PASS,
            'Scheduler activity',
            'Recent scheduler activity was detected.',
            $details
        );
    }


    /**
     * @param array<int,array<string,mixed>> $checks
     */
    private function checkMailActivity(
        array &$checks
    ): void
    {
        $path =
            $this->projectRoot
            .
            '/storage/logs/mail.log';


        if (
            !is_file(
                $path
            )
        ) {
            $this->addCheck(
                $checks,
                self::WARN,
                'Mail activity',
                'The mail log does not exist.'
            );


            return;
        }


        $contents =
            file_get_contents(
                $path
            );


        $timestamp =
            filemtime(
                $path
            );


        if (
            $contents === false
            ||
            $timestamp === false
        ) {
            $this->addCheck(
                $checks,
                self::WARN,
                'Mail activity',
                'The mail log could not be read completely.'
            );


            return;
        }


        $ageSeconds =
            max(
                0,
                time()
                -
                $timestamp
            );


        $successfulDelivery =
            str_contains(
                $contents,
                'Email delivery completed successfully.'
            );


        $details = [
            'Last log activity: '
            .
            date(
                'Y-m-d H:i:s T',
                $timestamp
            ),

            'Age: '
            .
            $this->formatAge(
                $ageSeconds
            ),

            'Log size: '
            .
            $this->formatBytes(
                (int)(
                    filesize(
                        $path
                    )
                    ?:
                    0
                )
            )
        ];


        if (!$successfulDelivery) {

            $this->addCheck(
                $checks,
                self::WARN,
                'Mail activity',
                'No successful mail delivery was found in the current mail log.',
                $details
            );


            return;
        }


        if ($ageSeconds > 7 * 24 * 60 * 60) {

            $this->addCheck(
                $checks,
                self::WARN,
                'Mail activity',
                'The most recent mail-log activity is more than seven days old.',
                $details
            );


            return;
        }


        $this->addCheck(
            $checks,
            self::PASS,
            'Mail activity',
            'Recent successful mail delivery activity was found.',
            $details
        );
    }


    /**
     * @return array{
     *     available:bool,
     *     contents:string,
     *     message:string
     * }
     */
    private function readCrontab(): array
    {
        if (
            !function_exists(
                'exec'
            )
        ) {
            return [
                'available' =>
                    false,

                'contents' =>
                    '',

                'message' =>
                    'The PHP exec function is unavailable.'
            ];
        }


        $crontabCommand =
            is_executable(
                '/usr/bin/crontab'
            )
                ? '/usr/bin/crontab'
                : 'crontab';


        $output = [];

        $exitCode = 1;


        exec(
            $crontabCommand
            .
            ' -l 2>&1',
            $output,
            $exitCode
        );


        $contents =
            implode(
                PHP_EOL,
                $output
            );


        if ($exitCode !== 0) {

            return [
                'available' =>
                    false,

                'contents' =>
                    $contents,

                'message' =>
                    $contents === ''
                        ? 'crontab -l returned exit code '
                            .
                            $exitCode
                            .
                            '.'
                        : $contents
            ];
        }


        return [
            'available' =>
                true,

            'contents' =>
                $contents,

            'message' =>
                'Crontab inspected successfully.'
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
            $this->projectRoot
            .
            '/config/'
            .
            $filename;


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


    /**
     * @return array<int,mixed>
     */
    private function columnValues(
        PDO $database,
        string $sql
    ): array
    {
        $statement =
            $database->query(
                $sql
            );


        if ($statement === false) {

            throw new RuntimeException(
                'A diagnostic database query could not be executed.'
            );
        }


        return
            $statement->fetchAll(
                PDO::FETCH_COLUMN
            );
    }


    /**
     * @return array<int,array<string,mixed>>
     */
    private function rows(
        PDO $database,
        string $sql
    ): array
    {
        $statement =
            $database->query(
                $sql
            );


        if ($statement === false) {

            throw new RuntimeException(
                'A diagnostic database query could not be executed.'
            );
        }


        return
            $statement->fetchAll();
    }


    private function scalar(
        PDO $database,
        string $sql
    ): mixed
    {
        $statement =
            $database->query(
                $sql
            );


        if ($statement === false) {

            throw new RuntimeException(
                'A diagnostic database query could not be executed.'
            );
        }


        return
            $statement->fetchColumn();
    }


    /**
     * @param array<int,array<string,mixed>> $checks
     * @param array<int,string> $details
     */
    private function addCheck(
        array &$checks,
        string $status,
        string $name,
        string $message,
        array $details = []
    ): void
    {
        $checks[] = [
            'status' =>
                $status,

            'name' =>
                $name,

            'message' =>
                $message,

            'details' =>
                $details
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


    private function formatAge(
        int $seconds
    ): string
    {
        if ($seconds < 60) {

            return
                $seconds
                .
                ' second'
                .
                (
                    $seconds === 1
                        ? ''
                        : 's'
                );
        }


        $minutes =
            intdiv(
                $seconds,
                60
            );


        if ($minutes < 60) {

            return
                $minutes
                .
                ' minute'
                .
                (
                    $minutes === 1
                        ? ''
                        : 's'
                );
        }


        $hours =
            intdiv(
                $minutes,
                60
            );


        if ($hours < 48) {

            return
                $hours
                .
                ' hour'
                .
                (
                    $hours === 1
                        ? ''
                        : 's'
                );
        }


        $days =
            intdiv(
                $hours,
                24
            );


        return
            $days
            .
            ' day'
            .
            (
                $days === 1
                    ? ''
                    : 's'
            );
    }
}
