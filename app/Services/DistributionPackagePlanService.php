<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class DistributionPackagePlanService
{
    private string $projectRoot;


    public function __construct(
        ?string $projectRoot = null
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
                'The distribution-package project root cannot be empty.'
            );
        }


        if (!is_dir($projectRoot)) {
            throw new RuntimeException(
                'The distribution-package project root does not exist: '
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
                'The distribution-package project root could not be resolved.'
            );
        }


        $this->projectRoot =
            $resolvedRoot;
    }


    /**
     * @return array<string,mixed>
     */
    public function plan(
        ?string $version = null
    ): array
    {
        $startedAt =
            microtime(
                true
            );


        $version =
            $version === null
                ? $this->readVersion()
                : trim(
                    $version
                );


        $this->validateVersion(
            $version
        );


        $packageBaseName =
            'iqwurkspunch-'
            .
            $version;


        $requiredDirectories =
            $this->requiredDirectories();


        $requiredFiles =
            $this->requiredFiles(
                $version
            );


        $directoryChecks =
            $this->checkPaths(
                $requiredDirectories,
                'directory'
            );


        $fileChecks =
            $this->checkPaths(
                $requiredFiles,
                'file'
            );


        $checks =
            array_merge(
                $directoryChecks,
                $fileChecks
            );


        $failureCount =
            count(
                array_filter(
                    $checks,
                    static fn (
                        array $check
                    ): bool =>
                        (
                            $check['status']
                            ??
                            'FAIL'
                        )
                        ===
                        'FAIL'
                )
            );


        $warningCount =
            count(
                array_filter(
                    $checks,
                    static fn (
                        array $check
                    ): bool =>
                        (
                            $check['status']
                            ??
                            'FAIL'
                        )
                        ===
                        'WARN'
                )
            );


        $passCount =
            count(
                $checks
            )
            -
            $failureCount
            -
            $warningCount;


        $overallStatus =
            $failureCount > 0
                ? 'FAIL'
                : (
                    $warningCount > 0
                        ? 'WARN'
                        : 'PASS'
                );


        return [
            'mode' =>
                'plan',

            'application_name' =>
                'IQwurksPunch',

            'application_version' =>
                $version,

            'package_base_name' =>
                $packageBaseName,

            'package_file_name' =>
                $packageBaseName
                .
                '.tar.gz',

            'project_root' =>
                $this->projectRoot,

            'staging_directory' =>
                $this->projectRoot
                .
                '/storage/cache/packages/'
                .
                $packageBaseName,

            'artifact_directory' =>
                $this->projectRoot
                .
                '/storage/exports/packages',

            'artifact_path' =>
                $this->projectRoot
                .
                '/storage/exports/packages/'
                .
                $packageBaseName
                .
                '.tar.gz',

            'include_paths' =>
                $this->includePaths(),

            'required_directories' =>
                $requiredDirectories,

            'required_files' =>
                $requiredFiles,

            'runtime_directories' =>
                $this->runtimeDirectories(),

            'excluded_paths' =>
                $this->excludedPaths(),

            'required_path_checks' =>
                $checks,

            'summary' => [
                'total' =>
                    count(
                        $checks
                    ),

                'passed' =>
                    $passCount,

                'warnings' =>
                    $warningCount,

                'failures' =>
                    $failureCount
            ],

            'overall_status' =>
                $overallStatus,

            'blocked' =>
                $failureCount > 0,

            'can_build' =>
                $failureCount === 0,

            'changes_made' =>
                false,

            'planned_at' =>
                date(
                    DATE_ATOM
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


    private function readVersion(): string
    {
        $versionFile =
            $this->projectRoot
            .
            '/VERSION';


        if (
            !is_file(
                $versionFile
            )
            ||
            !is_readable(
                $versionFile
            )
        ) {
            throw new RuntimeException(
                'The VERSION file is missing or unreadable.'
            );
        }


        $version =
            file_get_contents(
                $versionFile
            );


        if ($version === false) {
            throw new RuntimeException(
                'The VERSION file could not be read.'
            );
        }


        $version =
            trim(
                $version
            );


        if ($version === '') {
            throw new RuntimeException(
                'The VERSION file is empty.'
            );
        }


        return $version;
    }


    private function validateVersion(
        string $version
    ): void
    {
        if ($version === '') {
            throw new RuntimeException(
                'The distribution package version cannot be empty.'
            );
        }


        if (
            preg_match(
                '/^[0-9]+\.[0-9]+\.[0-9]+(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/',
                $version
            )
            !==
            1
        ) {
            throw new RuntimeException(
                'The distribution package version is invalid: '
                .
                $version
            );
        }
    }


    /**
     * @return array<int,string>
     */
    private function includePaths(): array
    {
        return [
            'app',
            'bootstrap',
            'config',
            'database/migrations',
            'database/seeds',
            'deployment',
            'docs',
            'plugins',
            'public',
            'releases',
            'routes',
            'tests',
            '.gitignore',
            'CHANGELOG.md',
            'composer.json',
            'composer.lock',
            'iqwurks',
            'LICENSE',
            'migrate.php',
            'phpunit.xml',
            'README.md',
            'ROADMAP.md',
            'VERSION'
        ];
    }


    /**
     * @return array<int,string>
     */
    private function requiredDirectories(): array
    {
        return [
            'app',
            'bootstrap',
            'config',
            'database/migrations',
            'deployment',
            'docs',
            'plugins',
            'public',
            'releases',
            'routes',
            'tests'
        ];
    }


    /**
     * @return array<int,string>
     */
    private function requiredFiles(
        string $version
    ): array
    {
        return [
            'CHANGELOG.md',
            'composer.json',
            'composer.lock',
            'config/mail.example.php',
            'iqwurks',
            'LICENSE',
            'migrate.php',
            'README.md',
            'releases/'
            .
            $version
            .
            '.md',
            'ROADMAP.md',
            'VERSION'
        ];
    }


    /**
     * @return array<int,string>
     */
    private function runtimeDirectories(): array
    {
        return [
            'database/sqlite',
            'storage/backups',
            'storage/cache',
            'storage/exports',
            'storage/logs',
            'storage/sessions'
        ];
    }


    /**
     * @return array<int,string>
     */
    private function excludedPaths(): array
    {
        return [
            '.git',
            '.github',
            '.phpunit.cache',
            '.env',
            '.env.*',
            'coverage',
            'vendor',
            'config/mail.php',
            'database/sqlite/*.sqlite',
            'database/sqlite/*.sqlite-*',
            'database/sqlite/*.db',
            'storage/backups/*',
            'storage/cache/*',
            'storage/exports/*',
            'storage/logs/*',
            'storage/sessions/*',
            'storage/*.sqlite',
            'storage/*.db',
            'firewall-rules.txt',
            'installed-packages.txt'
        ];
    }


    /**
     * @param array<int,string> $paths
     *
     * @return array<int,array<string,mixed>>
     */
    private function checkPaths(
        array $paths,
        string $type
    ): array
    {
        $checks = [];


        foreach ($paths as $path) {

            $absolutePath =
                $this->projectRoot
                .
                '/'
                .
                $path;


            $exists =
                $type === 'directory'
                    ? is_dir(
                        $absolutePath
                    )
                    : is_file(
                        $absolutePath
                    );


            $readable =
                $exists
                &&
                is_readable(
                    $absolutePath
                );


            $status =
                !$exists
                    ? 'FAIL'
                    : (
                        !$readable
                            ? 'FAIL'
                            : 'PASS'
                    );


            $message =
                !$exists
                    ? 'Required '
                        .
                        $type
                        .
                        ' is missing.'
                    : (
                        !$readable
                            ? 'Required '
                                .
                                $type
                                .
                                ' is not readable.'
                            : 'Required '
                                .
                                $type
                                .
                                ' is present and readable.'
                    );


            $checks[] = [
                'path' =>
                    $path,

                'absolute_path' =>
                    $absolutePath,

                'type' =>
                    $type,

                'exists' =>
                    $exists,

                'readable' =>
                    $readable,

                'status' =>
                    $status,

                'message' =>
                    $message
            ];
        }


        return $checks;
    }
}
