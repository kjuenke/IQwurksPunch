<?php
declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

final class InstallationPreflightService
{
    public const PASS = 'PASS';

    public const WARN = 'WARN';

    public const FAIL = 'FAIL';


    private string $projectRoot;

    /**
     * @var array<int,string>
     */
    private array $requiredExtensions;

    /**
     * @var array<string,array<int,string>>
     */
    private array $requiredCommands;

    /**
     * @var array<string,mixed>
     */
    private array $environment;


    /**
     * @param array<int,string>|null $requiredExtensions
     * @param array<string,array<int,string>>|null $requiredCommands
     * @param array<string,mixed> $environment
     */
    public function __construct(
        ?string $projectRoot = null,
        ?array $requiredExtensions = null,
        ?array $requiredCommands = null,
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
            throw new InvalidArgumentException(
                'The installation-preflight project root cannot be empty.'
            );
        }


        $this->projectRoot =
            $projectRoot;


        $this->requiredExtensions =
            $requiredExtensions
            ??
            [
                'ctype',
                'curl',
                'dom',
                'fileinfo',
                'filter',
                'intl',
                'json',
                'mbstring',
                'openssl',
                'pcre',
                'PDO',
                'pdo_sqlite',
                'session',
                'SimpleXML',
                'tokenizer',
                'xml',
                'xmlreader',
                'xmlwriter',
                'zip'
            ];


        $this->requiredCommands =
            $requiredCommands
            ??
            [
                'PHP CLI' => [
                    'php'
                ],

                'Composer' => [
                    'composer'
                ],

                'SQLite CLI' => [
                    'sqlite3'
                ],

                'Git' => [
                    'git'
                ],

                'cURL' => [
                    'curl'
                ],

                'Unzip' => [
                    'unzip'
                ],

                'Nginx' => [
                    'nginx'
                ],

                'PHP-FPM' => [
                    'php-fpm8.5',
                    'php-fpm'
                ],

                'Cron' => [
                    'cron'
                ],

                'File locking' => [
                    'flock'
                ],

                'Logrotate' => [
                    'logrotate'
                ]
            ];


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


        $this->checkOperatingSystem(
            $checks
        );


        $this->checkPhpVersion(
            $checks
        );


        $this->checkPhpExtensions(
            $checks
        );


        $this->checkSystemCommands(
            $checks
        );


        $this->checkApplicationSource(
            $checks
        );


        $this->checkComposerDependencies(
            $checks
        );


        $this->checkRuntimeDirectories(
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
    private function checkOperatingSystem(
        array &$checks
    ): void
    {
        $release =
            $this->operatingSystemRelease();


        $identifier =
            strtolower(
                trim(
                    (string)(
                        $release['ID']
                        ??
                        ''
                    )
                )
            );


        $version =
            trim(
                (string)(
                    $release['VERSION_ID']
                    ??
                    ''
                )
            );


        $prettyName =
            trim(
                (string)(
                    $release['PRETTY_NAME']
                    ??
                    ''
                )
            );


        $details = [];


        if ($prettyName !== '') {
            $details[] =
                'Detected: '
                .
                $prettyName;
        }


        if ($identifier !== 'ubuntu') {
            $this->addCheck(
                $checks,
                self::FAIL,
                'Operating system',
                'The supported production platform is 64-bit Ubuntu Linux.',
                $details
            );


            return;
        }


        if (
            $version === ''
            ||
            version_compare(
                $version,
                '24.04',
                '<'
            )
        ) {
            $this->addCheck(
                $checks,
                self::WARN,
                'Operating system',
                'Ubuntu was detected, but the release is older than the validated platform baseline.',
                $details
            );


            return;
        }


        $this->addCheck(
            $checks,
            self::PASS,
            'Operating system',
            'The operating system satisfies the supported Ubuntu baseline.',
            $details
        );
    }


    /**
     * @param array<int,array<string,mixed>> $checks
     */
    private function checkPhpVersion(
        array &$checks
    ): void
    {
        $version =
            trim(
                (string)(
                    $this->environment['php_version']
                    ??
                    PHP_VERSION
                )
            );


        if (
            $version === ''
            ||
            version_compare(
                $version,
                '8.5.0',
                '<'
            )
        ) {
            $this->addCheck(
                $checks,
                self::FAIL,
                'PHP version',
                'PHP 8.5 or newer is required.',
                [
                    'Detected: '
                    .
                    (
                        $version === ''
                            ? 'unknown'
                            : $version
                    )
                ]
            );


            return;
        }


        $this->addCheck(
            $checks,
            self::PASS,
            'PHP version',
            'The PHP version satisfies the application requirement.',
            [
                'Detected: '
                .
                $version,

                'Required: 8.5.0 or newer'
            ]
        );
    }


    /**
     * @param array<int,array<string,mixed>> $checks
     */
    private function checkPhpExtensions(
        array &$checks
    ): void
    {
        $loadedExtensions =
            $this->environment['loaded_extensions']
            ??
            get_loaded_extensions();


        if (!is_array($loadedExtensions)) {
            $loadedExtensions = [];
        }


        $normalizedLoaded =
            array_map(
                static fn (
                    mixed $extension
                ): string =>
                    strtolower(
                        trim(
                            (string)$extension
                        )
                    ),
                $loadedExtensions
            );


        $missing = [];


        foreach ($this->requiredExtensions as $extension) {

            if (
                !in_array(
                    strtolower(
                        $extension
                    ),
                    $normalizedLoaded,
                    true
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
                    ),

                    'Required extension count: '
                    .
                    count(
                        $this->requiredExtensions
                    )
                ]
            );


            return;
        }


        $this->addCheck(
            $checks,
            self::PASS,
            'PHP extensions',
            'All required PHP extensions are loaded.',
            [
                'Required extension count: '
                .
                count(
                    $this->requiredExtensions
                )
            ]
        );
    }


    /**
     * @param array<int,array<string,mixed>> $checks
     */
    private function checkSystemCommands(
        array &$checks
    ): void
    {
        $missing = [];

        $found = [];


        foreach (
            $this->requiredCommands
            as
            $label => $candidates
        ) {
            $path = null;


            foreach ($candidates as $candidate) {

                $path =
                    $this->locateCommand(
                        $candidate
                    );


                if ($path !== null) {
                    break;
                }
            }


            if ($path === null) {
                $missing[] =
                    $label;


                continue;
            }


            $found[] =
                $label
                .
                ': '
                .
                $path;
        }


        if ($missing !== []) {
            $this->addCheck(
                $checks,
                self::FAIL,
                'System commands',
                'One or more required operating-system commands are unavailable.',
                [
                    'Missing: '
                    .
                    implode(
                        ', ',
                        $missing
                    ),

                    ...$found
                ]
            );


            return;
        }


        $this->addCheck(
            $checks,
            self::PASS,
            'System commands',
            'All required operating-system commands are available.',
            $found
        );
    }


    /**
     * @param array<int,array<string,mixed>> $checks
     */
    private function checkApplicationSource(
        array &$checks
    ): void
    {
        $requiredFiles = [
            'VERSION',
            'composer.json',
            'composer.lock',
            'iqwurks',
            'migrate.php',
            'bootstrap/app.php',
            'public/index.php'
        ];


        $requiredDirectories = [
            'app',
            'config',
            'database/migrations',
            'database/sqlite',
            'public',
            'storage'
        ];


        $missing = [];


        foreach ($requiredFiles as $relativePath) {

            if (
                !is_file(
                    $this->path(
                        $relativePath
                    )
                )
            ) {
                $missing[] =
                    $relativePath;
            }
        }


        foreach ($requiredDirectories as $relativePath) {

            if (
                !is_dir(
                    $this->path(
                        $relativePath
                    )
                )
            ) {
                $missing[] =
                    $relativePath
                    .
                    '/';
            }
        }


        if ($missing !== []) {
            $this->addCheck(
                $checks,
                self::FAIL,
                'Application source',
                'The application source tree is incomplete.',
                [
                    'Missing: '
                    .
                    implode(
                        ', ',
                        $missing
                    ),

                    'Project root: '
                    .
                    $this->projectRoot
                ]
            );


            return;
        }


        $this->addCheck(
            $checks,
            self::PASS,
            'Application source',
            'The required application source files and directories are present.',
            [
                'Project root: '
                .
                $this->projectRoot
            ]
        );
    }


    /**
     * @param array<int,array<string,mixed>> $checks
     */
    private function checkComposerDependencies(
        array &$checks
    ): void
    {
        $autoloadPath =
            $this->path(
                'vendor/autoload.php'
            );


        if (
            !is_file(
                $autoloadPath
            )
            ||
            !is_readable(
                $autoloadPath
            )
        ) {
            $this->addCheck(
                $checks,
                self::FAIL,
                'Composer dependencies',
                'Composer dependencies are not installed or the autoloader is unreadable.',
                [
                    'Expected: '
                    .
                    $autoloadPath,

                    'Run composer install from the project root.'
                ]
            );


            return;
        }


        $this->addCheck(
            $checks,
            self::PASS,
            'Composer dependencies',
            'Composer dependencies and autoloading are available.',
            [
                'Autoloader: '
                .
                $autoloadPath
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
        $runtimeDirectories = [
            'database/sqlite',
            'storage/backups',
            'storage/cache',
            'storage/exports',
            'storage/logs',
            'storage/sessions'
        ];


        $problems = [];

        $details = [];


        foreach ($runtimeDirectories as $relativePath) {

            $path =
                $this->path(
                    $relativePath
                );


            if (!is_dir($path)) {
                $problems[] =
                    $relativePath
                    .
                    ' is missing.';


                continue;
            }


            if (!is_writable($path)) {
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
                        : substr(
                            sprintf(
                                '%o',
                                $permissions
                            ),
                            -4
                        )
                );
        }


        if ($problems !== []) {
            $this->addCheck(
                $checks,
                self::FAIL,
                'Runtime directories',
                'One or more required runtime directories are unavailable or unwritable.',
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
            'All required runtime directories exist and are writable.',
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
            $freeBytes = false;
        }


        if (
            !is_int($totalBytes)
            &&
            !is_float($totalBytes)
        ) {
            $totalBytes = false;
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
                'Less than 1 GB of disk space is available.',
                $details
            );


            return;
        }


        if (
            $freeBytes < 5 * 1024 * 1024 * 1024
            ||
            $freePercent < 10
        ) {
            $this->addCheck(
                $checks,
                self::WARN,
                'Disk capacity',
                'Available disk capacity is limited.',
                $details
            );


            return;
        }


        $this->addCheck(
            $checks,
            self::PASS,
            'Disk capacity',
            'Available disk capacity is sufficient for installation.',
            $details
        );
    }


    /**
     * @return array<string,string>
     */
    private function operatingSystemRelease(): array
    {
        $override =
            $this->environment['os_release']
            ??
            null;


        if (is_array($override)) {
            return
                array_map(
                    static fn (
                        mixed $value
                    ): string =>
                        trim(
                            (string)$value
                        ),
                    $override
                );
        }


        $path =
            '/etc/os-release';


        if (
            !is_file(
                $path
            )
            ||
            !is_readable(
                $path
            )
        ) {
            return [];
        }


        $lines =
            file(
                $path,
                FILE_IGNORE_NEW_LINES
                |
                FILE_SKIP_EMPTY_LINES
            );


        if ($lines === false) {
            return [];
        }


        $release = [];


        foreach ($lines as $line) {

            if (
                str_starts_with(
                    trim(
                        $line
                    ),
                    '#'
                )
                ||
                !str_contains(
                    $line,
                    '='
                )
            ) {
                continue;
            }


            [
                $key,
                $value
            ] =
                explode(
                    '=',
                    $line,
                    2
                );


            $release[
                trim(
                    $key
                )
            ] =
                trim(
                    trim(
                        $value
                    ),
                    "\"'"
                );
        }


        return $release;
    }


    private function locateCommand(
        string $command
    ): ?string
    {
        $configuredPaths =
            $this->environment['command_paths']
            ??
            null;


        if (is_array($configuredPaths)) {

            if (
                !array_key_exists(
                    $command,
                    $configuredPaths
                )
            ) {
                return null;
            }


            $path =
                $configuredPaths[$command];


            if (
                !is_string(
                    $path
                )
                ||
                trim(
                    $path
                )
                ===
                ''
            ) {
                return null;
            }


            return
                trim(
                    $path
                );
        }


        $knownDirectories = [
            '/usr/local/bin',
            '/usr/local/sbin',
            '/usr/bin',
            '/usr/sbin',
            '/bin',
            '/sbin'
        ];


        foreach ($knownDirectories as $directory) {

            $path =
                $directory
                .
                '/'
                .
                $command;


            if (
                is_file(
                    $path
                )
                &&
                is_executable(
                    $path
                )
            ) {
                return $path;
            }
        }


        if (!function_exists('exec')) {
            return null;
        }


        $output = [];

        $exitCode = 1;


        exec(
            'command -v '
            .
            escapeshellarg(
                $command
            )
            .
            ' 2>/dev/null',
            $output,
            $exitCode
        );


        if (
            $exitCode !== 0
            ||
            $output === []
        ) {
            return null;
        }


        $path =
            trim(
                (string)$output[0]
            );


        return
            $path === ''
                ? null
                : $path;
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
}
