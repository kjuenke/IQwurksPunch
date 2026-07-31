<?php
declare(strict_types=1);

namespace App\Services;

use JsonException;
use PDO;
use RuntimeException;
use Throwable;

final class UpgradeReadinessService
{
    public const PASS = 'PASS';

    public const WARN = 'WARN';

    public const FAIL = 'FAIL';


    private string $projectRoot;

    /**
     * @var array<string,mixed>
     */
    private array $environment;


    /**
     * @param array<string,mixed> $environment
     */
    public function __construct(
        ?string $projectRoot = null,
        array $environment = []
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
                'The upgrade-readiness project root cannot be empty.'
            );
        }


        $this->projectRoot =
            $projectRoot;


        $this->environment =
            $environment;
    }


    /**
     * @return array<int,array{
     *     status:string,
     *     name:string,
     *     message:string,
     *     details:array<int,string>
     * }>
     */
    public function check(): array
    {
        $checks = [];


        $this->checkApplicationVersion(
            $checks
        );


        $this->checkComposerState(
            $checks
        );


        $this->checkUpgradePaths(
            $checks
        );


        $this->checkDatabaseHealth(
            $checks
        );


        $this->checkMigrationHistory(
            $checks
        );


        $this->checkVerifiedBackup(
            $checks
        );


        $this->checkMaintenanceMode(
            $checks
        );


        $this->checkDiskCapacity(
            $checks
        );


        return $checks;
    }


    /**
     * @param array<int,array<string,mixed>> $checks
     */
    private function checkApplicationVersion(
        array &$checks
    ): void
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
            $this->addCheck(
                $checks,
                self::FAIL,
                'Application version',
                'The installed application version cannot be read.',
                [
                    'Expected: '
                    .
                    $path
                ]
            );


            return;
        }


        $contents =
            file_get_contents(
                $path
            );


        if ($contents === false) {
            $this->addCheck(
                $checks,
                self::FAIL,
                'Application version',
                'The installed application version cannot be read.',
                [
                    'File: '
                    .
                    $path
                ]
            );


            return;
        }


        $version =
            trim(
                $contents
            );


        if (
            $version === ''
            ||
            preg_match(
                '/^\d+\.\d+\.\d+(?:-[0-9A-Za-z][0-9A-Za-z.-]*)?$/',
                $version
            )
            !==
            1
        ) {
            $this->addCheck(
                $checks,
                self::FAIL,
                'Application version',
                'The installed application version is invalid.',
                [
                    'Detected: '
                    .
                    (
                        $version === ''
                            ? 'empty'
                            : $version
                    ),

                    'Expected a semantic version such as 0.8.0 or 0.9.0-dev.'
                ]
            );


            return;
        }


        $this->addCheck(
            $checks,
            self::PASS,
            'Application version',
            'The installed application version is valid.',
            [
                'Installed version: '
                .
                $version
            ]
        );
    }


    /**
     * @param array<int,array<string,mixed>> $checks
     */
    private function checkComposerState(
        array &$checks
    ): void
    {
        $composerJson =
            $this->path(
                'composer.json'
            );


        $composerLock =
            $this->path(
                'composer.lock'
            );


        $autoload =
            $this->path(
                'vendor/autoload.php'
            );


        $problems = [];


        foreach (
            [
                'composer.json' =>
                    $composerJson,

                'composer.lock' =>
                    $composerLock,

                'vendor/autoload.php' =>
                    $autoload
            ]
            as
            $label => $path
        ) {
            if (
                !is_file(
                    $path
                )
                ||
                !is_readable(
                    $path
                )
            ) {
                $problems[] =
                    $label
                    .
                    ' is missing or unreadable.';
            }
        }


        if ($problems === []) {

            try {

                $this->decodeJsonFile(
                    $composerJson
                );


                $this->decodeJsonFile(
                    $composerLock
                );

            } catch (Throwable $exception) {

                $problems[] =
                    $exception->getMessage();
            }
        }


        if ($problems !== []) {
            $this->addCheck(
                $checks,
                self::FAIL,
                'Composer state',
                'Composer metadata or installed dependencies are incomplete.',
                $problems
            );


            return;
        }


        $this->addCheck(
            $checks,
            self::PASS,
            'Composer state',
            'Composer metadata and autoloading are available.',
            [
                'composer.json is valid JSON.',
                'composer.lock is valid JSON.',
                'vendor/autoload.php is readable.'
            ]
        );
    }


    /**
     * @param array<int,array<string,mixed>> $checks
     */
    private function checkUpgradePaths(
        array &$checks
    ): void
    {
        $files = [
            'VERSION',
            'composer.json',
            'composer.lock',
            'migrate.php'
        ];


        $directories = [
            '',
            'database/migrations',
            'database/sqlite',
            'storage/backups',
            'storage/cache'
        ];


        $problems = [];

        $details = [];


        foreach ($files as $relativePath) {

            $path =
                $this->path(
                    $relativePath
                );


            if (!is_file($path)) {
                $problems[] =
                    $relativePath
                    .
                    ' is missing.';


                continue;
            }


            if (!is_readable($path)) {
                $problems[] =
                    $relativePath
                    .
                    ' is not readable.';
            }


            if (!is_writable($path)) {
                $problems[] =
                    $relativePath
                    .
                    ' is not writable by the current process.';
            }


            $details[] =
                $relativePath
                .
                ' mode='
                .
                $this->mode(
                    $path
                );
        }


        foreach ($directories as $relativePath) {

            $path =
                $relativePath === ''
                    ? $this->projectRoot
                    : $this->path(
                        $relativePath
                    );


            $label =
                $relativePath === ''
                    ? 'project root'
                    : $relativePath;


            if (!is_dir($path)) {
                $problems[] =
                    $label
                    .
                    ' is missing.';


                continue;
            }


            if (!is_readable($path)) {
                $problems[] =
                    $label
                    .
                    ' is not readable.';
            }


            if (!is_writable($path)) {
                $problems[] =
                    $label
                    .
                    ' is not writable by the current process.';
            }


            $details[] =
                $label
                .
                ' mode='
                .
                $this->mode(
                    $path
                );
        }


        if ($problems !== []) {
            $this->addCheck(
                $checks,
                self::FAIL,
                'Upgrade paths',
                'One or more application paths are not ready for an upgrade.',
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
            'Upgrade paths',
            'Required application and runtime paths are readable and writable.',
            $details
        );
    }


    /**
     * @param array<int,array<string,mixed>> $checks
     */
    private function checkDatabaseHealth(
        array &$checks
    ): void
    {
        try {

            $databasePath =
                $this->databasePath();


            $problems = [];


            if (!is_file($databasePath)) {
                throw new RuntimeException(
                    'The configured SQLite database does not exist.'
                );
            }


            if (!is_readable($databasePath)) {
                $problems[] =
                    'The database file is not readable.';
            }


            if (!is_writable($databasePath)) {
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
                    $integrityMessages[0]
                )
                ===
                'ok';


            if (!$integrityValid) {
                $problems[] =
                    'SQLite integrity_check did not return ok.';
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


            $size =
                filesize(
                    $databasePath
                );


            $details = [
                'Database: '
                .
                $databasePath,

                'Size: '
                .
                $this->formatBytes(
                    $size === false
                        ? 0
                        : (int)$size
                ),

                'Integrity: '
                .
                (
                    $integrityValid
                        ? 'ok'
                        : implode(
                            '; ',
                            $integrityMessages
                        )
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
                    'Database health',
                    'The active database is not safe to upgrade.',
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
                'Database health',
                'The active SQLite database passed required upgrade checks.',
                $details
            );

        } catch (Throwable $exception) {

            $this->addCheck(
                $checks,
                self::FAIL,
                'Database health',
                'The active database could not be validated for upgrade.',
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


            $recorded =
                $this->columnValues(
                    $database,
                    '
                    SELECT migration
                    FROM migrations
                    ORDER BY migration
                    '
                );


            $recorded =
                array_values(
                    array_unique(
                        array_map(
                            static fn (
                                string $migration
                            ): string =>
                                basename(
                                    trim(
                                        $migration
                                    )
                                ),
                            $recorded
                        )
                    )
                );


            sort(
                $recorded
            );


            $pending =
                array_values(
                    array_diff(
                        $available,
                        $recorded
                    )
                );


            $unknown =
                array_values(
                    array_diff(
                        $recorded,
                        $available
                    )
                );


            $details = [
                'Migration files: '
                .
                count(
                    $available
                ),

                'Recorded migrations: '
                .
                count(
                    $recorded
                )
            ];


            if ($pending !== []) {
                $details[] =
                    'Pending: '
                    .
                    implode(
                        ', ',
                        $pending
                    );
            }


            if ($unknown !== []) {
                $details[] =
                    'Recorded without matching files: '
                    .
                    implode(
                        ', ',
                        $unknown
                    );
            }


            if ($unknown !== []) {
                $this->addCheck(
                    $checks,
                    self::FAIL,
                    'Migration history',
                    'The database contains migration records not present in the application source.',
                    $details
                );


                return;
            }


            if ($pending !== []) {
                $this->addCheck(
                    $checks,
                    self::WARN,
                    'Migration history',
                    'Unapplied migrations are waiting and must be applied during the upgrade.',
                    $details
                );


                return;
            }


            $this->addCheck(
                $checks,
                self::PASS,
                'Migration history',
                'Migration files and recorded migration history are aligned.',
                $details
            );

        } catch (Throwable $exception) {

            $this->addCheck(
                $checks,
                self::FAIL,
                'Migration history',
                'Migration history could not be validated.',
                [
                    $exception->getMessage()
                ]
            );
        }
    }


    /**
     * @param array<int,array<string,mixed>> $checks
     */
    private function checkVerifiedBackup(
        array &$checks
    ): void
    {
        try {

            $backupDirectory =
                $this->backupDirectory();


            if (
                !is_dir(
                    $backupDirectory
                )
                ||
                !is_readable(
                    $backupDirectory
                )
            ) {
                throw new RuntimeException(
                    'The configured backup directory is missing or unreadable.'
                );
            }


            $paths =
                glob(
                    rtrim(
                        $backupDirectory,
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
                $this->addCheck(
                    $checks,
                    self::FAIL,
                    'Verified backup',
                    'No SQLite database backup is available.',
                    [
                        'Backup directory: '
                        .
                        $backupDirectory
                    ]
                );


                return;
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


            $validBackup = null;

            $invalidBackups = [];


            foreach ($paths as $path) {

                try {

                    if (
                        $this->backupIsValid(
                            $path
                        )
                    ) {
                        $validBackup =
                            $path;


                        break;
                    }


                    $invalidBackups[] =
                        basename(
                            $path
                        );

                } catch (Throwable $exception) {

                    $invalidBackups[] =
                        basename(
                            $path
                        );
                }
            }


            if ($validBackup === null) {
                $this->addCheck(
                    $checks,
                    self::FAIL,
                    'Verified backup',
                    'No valid SQLite backup is available for upgrade rollback.',
                    [
                        'Backups inspected: '
                        .
                        count(
                            $paths
                        ),

                        'Invalid or unreadable: '
                        .
                        count(
                            $invalidBackups
                        )
                    ]
                );


                return;
            }


            $modifiedTimestamp =
                filemtime(
                    $validBackup
                );


            if ($modifiedTimestamp === false) {
                throw new RuntimeException(
                    'The verified backup modification time could not be read.'
                );
            }


            $now =
                $this->currentTimestamp();


            $ageSeconds =
                max(
                    0,
                    $now
                    -
                    $modifiedTimestamp
                );


            $size =
                filesize(
                    $validBackup
                );


            $details = [
                'Backup: '
                .
                basename(
                    $validBackup
                ),

                'Modified: '
                .
                date(
                    'Y-m-d H:i:s T',
                    $modifiedTimestamp
                ),

                'Age: '
                .
                $this->formatAge(
                    $ageSeconds
                ),

                'Size: '
                .
                $this->formatBytes(
                    $size === false
                        ? 0
                        : (int)$size
                ),

                'Integrity: ok',

                'Foreign-key violations: 0'
            ];


            if ($invalidBackups !== []) {
                $details[] =
                    'Newer invalid or unreadable backups skipped: '
                    .
                    count(
                        $invalidBackups
                    );
            }


            if ($ageSeconds > 48 * 60 * 60) {
                $this->addCheck(
                    $checks,
                    self::FAIL,
                    'Verified backup',
                    'The newest valid backup is more than 48 hours old.',
                    $details
                );


                return;
            }


            if (
                $ageSeconds > 24 * 60 * 60
                ||
                $invalidBackups !== []
            ) {
                $this->addCheck(
                    $checks,
                    self::WARN,
                    'Verified backup',
                    'A valid backup exists, but a new pre-upgrade backup is recommended.',
                    $details
                );


                return;
            }


            $this->addCheck(
                $checks,
                self::PASS,
                'Verified backup',
                'A recent valid backup is available for upgrade rollback.',
                $details
            );

        } catch (Throwable $exception) {

            $this->addCheck(
                $checks,
                self::FAIL,
                'Verified backup',
                'Backup readiness could not be validated.',
                [
                    $exception->getMessage()
                ]
            );
        }
    }


    /**
     * @param array<int,array<string,mixed>> $checks
     */
    private function checkMaintenanceMode(
        array &$checks
    ): void
    {
        try {

            $maintenanceFile =
                $this->maintenanceFile();


            if (!is_file($maintenanceFile)) {
                $this->addCheck(
                    $checks,
                    self::WARN,
                    'Maintenance mode',
                    'Maintenance mode is inactive.',
                    [
                        'Enable maintenance mode before applying an upgrade.',
                        'File: '
                        .
                        $maintenanceFile
                    ]
                );


                return;
            }


            if (!is_readable($maintenanceFile)) {
                $this->addCheck(
                    $checks,
                    self::FAIL,
                    'Maintenance mode',
                    'Maintenance mode is active, but its metadata cannot be read.',
                    [
                        'File: '
                        .
                        $maintenanceFile
                    ]
                );


                return;
            }


            $metadata =
                $this->decodeJsonFile(
                    $maintenanceFile
                );


            if (
                (
                    $metadata['active']
                    ??
                    null
                )
                !==
                true
            ) {
                $this->addCheck(
                    $checks,
                    self::FAIL,
                    'Maintenance mode',
                    'Maintenance metadata is present but invalid.',
                    [
                        'File: '
                        .
                        $maintenanceFile
                    ]
                );


                return;
            }


            $this->addCheck(
                $checks,
                self::PASS,
                'Maintenance mode',
                'Maintenance mode is active and protecting the installation.',
                [
                    'Reason: '
                    .
                    (
                        $metadata['reason']
                        ??
                        'Not recorded'
                    ),

                    'Started: '
                    .
                    (
                        $metadata['started_at_display']
                        ??
                        $metadata['started_at']
                        ??
                        'Not recorded'
                    ),

                    'File: '
                    .
                    $maintenanceFile
                ]
            );

        } catch (Throwable $exception) {

            $this->addCheck(
                $checks,
                self::FAIL,
                'Maintenance mode',
                'Maintenance-mode readiness could not be validated.',
                [
                    $exception->getMessage()
                ]
            );
        }
    }


    /**
     * @param array<int,array<string,mixed>> $checks
     */
    private function checkDiskCapacity(
        array &$checks
    ): void
    {
        $freeBytes =
            $this->environment['free_bytes']
            ??
            disk_free_space(
                $this->projectRoot
            );


        $totalBytes =
            $this->environment['total_bytes']
            ??
            disk_total_space(
                $this->projectRoot
            );


        if (
            !is_int($freeBytes)
            &&
            !is_float($freeBytes)
        ) {
            $freeBytes =
                false;
        }


        if (
            !is_int($totalBytes)
            &&
            !is_float($totalBytes)
        ) {
            $totalBytes =
                false;
        }


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


        $freeBytes =
            (int)$freeBytes;


        $totalBytes =
            (int)$totalBytes;


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
                $freeBytes
            ),

            'Total: '
            .
            $this->formatBytes(
                $totalBytes
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


        if ($freeBytes < 1024 * 1024 * 1024) {
            $this->addCheck(
                $checks,
                self::FAIL,
                'Disk capacity',
                'Less than 1 GB of disk space is available for the upgrade.',
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
                'Available disk capacity is limited for a safe upgrade.',
                $details
            );


            return;
        }


        $this->addCheck(
            $checks,
            self::PASS,
            'Disk capacity',
            'Available disk capacity is sufficient for a safe upgrade.',
            $details
        );
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


    private function backupDirectory(): string
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


        if ($directory === '') {
            throw new RuntimeException(
                'The database backup directory is not configured.'
            );
        }


        return $directory;
    }


    private function maintenanceFile(): string
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


    private function backupIsValid(
        string $path
    ): bool
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
            return false;
        }


        $database =
            $this->openDatabase(
                $path
            );


        $integrityMessages =
            $this->columnValues(
                $database,
                'PRAGMA integrity_check'
            );


        if (
            count(
                $integrityMessages
            )
            !==
            1
            ||
            strtolower(
                $integrityMessages[0]
            )
            !==
            'ok'
        ) {
            return false;
        }


        return
            $this->rows(
                $database,
                'PRAGMA foreign_key_check'
            )
            ===
            [];
    }


    /**
     * @return array<int,string>
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
                'A required database query could not be executed.'
            );
        }


        return
            array_values(
                array_map(
                    static fn (
                        mixed $value
                    ): string =>
                        trim(
                            (string)$value
                        ),
                    $statement->fetchAll(
                        PDO::FETCH_COLUMN
                    )
                )
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
                'A required database query could not be executed.'
            );
        }


        return
            $statement->fetchAll();
    }


    /**
     * @return array<string,mixed>
     *
     * @throws JsonException
     */
    private function decodeJsonFile(
        string $path
    ): array
    {
        $contents =
            file_get_contents(
                $path
            );


        if ($contents === false) {
            throw new RuntimeException(
                'A JSON file could not be read: '
                .
                basename(
                    $path
                )
            );
        }


        $decoded =
            json_decode(
                $contents,
                true,
                512,
                JSON_THROW_ON_ERROR
            );


        if (!is_array($decoded)) {
            throw new RuntimeException(
                'A JSON file did not contain an object: '
                .
                basename(
                    $path
                )
            );
        }


        return $decoded;
    }


    private function currentTimestamp(): int
    {
        $timestamp =
            $this->environment['now']
            ??
            time();


        if (!is_numeric($timestamp)) {
            return time();
        }


        return
            (int)$timestamp;
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


    private function mode(
        string $path
    ): string
    {
        $permissions =
            fileperms(
                $path
            );


        if ($permissions === false) {
            return 'unknown';
        }


        return
            substr(
                sprintf(
                    '%o',
                    $permissions
                ),
                -4
            );
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
                array_values(
                    $details
                )
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
