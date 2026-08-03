<?php
declare(strict_types=1);

namespace App\Services;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

final class DistributionPackageVerificationService
{
    private string $projectRoot;

    private ProcessRunnerInterface $processes;


    public function __construct(
        ?string $projectRoot = null,
        ?ProcessRunnerInterface $processes = null
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
                'The distribution-verification project root cannot be empty.'
            );
        }


        if (!is_dir($projectRoot)) {
            throw new RuntimeException(
                'The distribution-verification project root does not exist: '
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
                'The distribution-verification project root could not be resolved.'
            );
        }


        $this->projectRoot =
            rtrim(
                $resolvedRoot,
                DIRECTORY_SEPARATOR
            );


        $this->processes =
            $processes
            ??
            new ProcessRunnerService(
                120.0
            );
    }


    /**
     * @return array<string,mixed>
     */
    public function verify(
        string $archivePath,
        ?string $expectedSha256 = null
    ): array
    {
        $startedAt =
            microtime(
                true
            );


        $archivePath =
            $this->resolveArchivePath(
                $archivePath
            );


        $expectedSha256 =
            $this->normalizeExpectedSha256(
                $expectedSha256
            );


        $archiveSha256 =
            hash_file(
                'sha256',
                $archivePath
            );


        if ($archiveSha256 === false) {
            throw new RuntimeException(
                'The distribution archive could not be hashed.'
            );
        }


        $archiveSize =
            filesize(
                $archivePath
            );


        if ($archiveSize === false) {
            throw new RuntimeException(
                'The distribution archive size could not be read.'
            );
        }


        $workspace =
            sys_get_temp_dir()
            .
            DIRECTORY_SEPARATOR
            .
            'iqwurks-package-verify-'
            .
            bin2hex(
                random_bytes(
                    8
                )
            );


        $failures = [];

        $packageRootName =
            null;

        $applicationVersion =
            null;

        $manifestSchemaVersion =
            null;

        $manifestEntryCount =
            0;

        $verifiedFiles =
            0;

        $verifiedDirectories =
            0;

        $listProcess =
            null;

        $extractProcess =
            null;

        $workspaceRemoved =
            false;


        if (
            $expectedSha256 !== null
            &&
            !hash_equals(
                $expectedSha256,
                $archiveSha256
            )
        ) {
            $failures[] =
                $this->failure(
                    'archive_checksum_mismatch',
                    basename(
                        $archivePath
                    ),
                    'The archive SHA-256 digest does not match the expected value.',
                    $expectedSha256,
                    $archiveSha256
                );
        }


        try {

            if (
                !mkdir(
                    $workspace,
                    0700,
                    true
                )
                &&
                !is_dir(
                    $workspace
                )
            ) {
                throw new RuntimeException(
                    'The temporary package-verification workspace could not be created.'
                );
            }


            $listProcess =
                $this->processes->run(
                    [
                        'tar',
                        '--list',
                        '--gzip',
                        '--file',
                        $archivePath
                    ],
                    $this->projectRoot,
                    [],
                    120.0
                );


            if (
                (
                    $listProcess['successful']
                    ??
                    false
                )
                !==
                true
            ) {
                throw new RuntimeException(
                    'The distribution archive could not be listed: '
                    .
                    trim(
                        (string)(
                            $listProcess['stderr']
                            ??
                            'Unknown TAR listing failure.'
                        )
                    )
                );
            }


            $archiveEntries =
                $this->archiveEntries(
                    (string)(
                        $listProcess['stdout']
                        ??
                        ''
                    )
                );


            $topLevelRoots = [];


            foreach ($archiveEntries as $archiveEntry) {

                $safeEntry =
                    $this->validateRelativePath(
                        $archiveEntry
                    );


                $topLevelRoot =
                    explode(
                        '/',
                        $safeEntry,
                        2
                    )[0];


                $topLevelRoots[$topLevelRoot] =
                    true;
            }


            $rootNames =
                array_keys(
                    $topLevelRoots
                );


            sort(
                $rootNames,
                SORT_STRING
            );


            if (
                count(
                    $rootNames
                )
                !==
                1
            ) {
                $failures[] =
                    $this->failure(
                        'invalid_package_root_count',
                        '.',
                        'The archive must contain exactly one top-level package directory.',
                        '1',
                        (string)count(
                            $rootNames
                        )
                    );

            } else {

                $packageRootName =
                    $rootNames[0];


                $extractProcess =
                    $this->processes->run(
                        [
                            'tar',
                            '--extract',
                            '--gzip',
                            '--file',
                            $archivePath,
                            '--directory',
                            $workspace,
                            '--no-same-owner',
                            '--same-permissions'
                        ],
                        $this->projectRoot,
                        [],
                        120.0
                    );


                if (
                    (
                        $extractProcess['successful']
                        ??
                        false
                    )
                    !==
                    true
                ) {
                    throw new RuntimeException(
                        'The distribution archive could not be extracted: '
                        .
                        trim(
                            (string)(
                                $extractProcess['stderr']
                                ??
                                'Unknown TAR extraction failure.'
                            )
                        )
                    );
                }


                $packageRoot =
                    $workspace
                    .
                    DIRECTORY_SEPARATOR
                    .
                    $packageRootName;


                if (!is_dir($packageRoot)) {
                    $failures[] =
                        $this->failure(
                            'package_root_not_directory',
                            $packageRootName,
                            'The top-level package entry is not a directory.'
                        );

                } else {

                    $this->verifyPackageRoot(
                        $packageRoot,
                        $packageRootName,
                        $archivePath,
                        $failures,
                        $applicationVersion,
                        $manifestSchemaVersion,
                        $manifestEntryCount,
                        $verifiedFiles,
                        $verifiedDirectories
                    );
                }
            }

        } finally {

            if (is_dir($workspace)) {
                $this->removeDirectory(
                    $workspace
                );
            }


            $workspaceRemoved =
                !file_exists(
                    $workspace
                );
        }


        return [
            'mode' =>
                'verify',

            'successful' =>
                $failures === [],

            'overall_status' =>
                $failures === []
                    ? 'PASS'
                    : 'FAIL',

            'archive_path' =>
                $archivePath,

            'archive_file_name' =>
                basename(
                    $archivePath
                ),

            'archive_size_bytes' =>
                $archiveSize,

            'archive_sha256' =>
                $archiveSha256,

            'expected_sha256' =>
                $expectedSha256,

            'checksum_matches' =>
                $expectedSha256 === null
                ||
                hash_equals(
                    $expectedSha256,
                    $archiveSha256
                ),

            'package_root' =>
                $packageRootName,

            'application_version' =>
                $applicationVersion,

            'manifest_schema_version' =>
                $manifestSchemaVersion,

            'manifest_entries' =>
                $manifestEntryCount,

            'verified_files' =>
                $verifiedFiles,

            'verified_directories' =>
                $verifiedDirectories,

            'failure_count' =>
                count(
                    $failures
                ),

            'failures' =>
                $failures,

            'list_process' =>
                $listProcess,

            'extract_process' =>
                $extractProcess,

            'temporary_workspace_removed' =>
                $workspaceRemoved,

            'changes_made' =>
                false,

            'verified_at' =>
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


    /**
     * @param array<int,array<string,mixed>> $failures
     */
    private function verifyPackageRoot(
        string $packageRoot,
        string $packageRootName,
        string $archivePath,
        array &$failures,
        ?string &$applicationVersion,
        mixed &$manifestSchemaVersion,
        int &$manifestEntryCount,
        int &$verifiedFiles,
        int &$verifiedDirectories
    ): void
    {
        $this->verifyNoSymbolicLinks(
            $packageRoot,
            $failures
        );


        $rootMode =
            $this->fileMode(
                $packageRoot
            );


        if ($rootMode !== '0755') {
            $failures[] =
                $this->failure(
                    'package_root_mode_mismatch',
                    $packageRootName,
                    'The top-level package directory must use mode 0755.',
                    '0755',
                    $rootMode
                );
        }


        $manifestPath =
            $packageRoot
            .
            DIRECTORY_SEPARATOR
            .
            'PACKAGE-MANIFEST.json';


        if (!is_file($manifestPath)) {
            $failures[] =
                $this->failure(
                    'package_manifest_missing',
                    'PACKAGE-MANIFEST.json',
                    'The portable package manifest is missing.'
                );


            return;
        }


        try {

            $manifest =
                json_decode(
                    (string)file_get_contents(
                        $manifestPath
                    ),
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );

        } catch (Throwable $exception) {

            throw new RuntimeException(
                'The portable package manifest contains invalid JSON: '
                .
                $exception->getMessage(),
                0,
                $exception
            );
        }


        if (!is_array($manifest)) {
            throw new RuntimeException(
                'The portable package manifest did not contain an object.'
            );
        }


        $manifestSchemaVersion =
            $manifest['schema_version']
            ??
            null;


        if ($manifestSchemaVersion !== 1) {
            $failures[] =
                $this->failure(
                    'unsupported_manifest_schema',
                    'PACKAGE-MANIFEST.json',
                    'The package manifest schema version is unsupported.',
                    '1',
                    (string)$manifestSchemaVersion
                );
        }


        $manifestPackageRoot =
            trim(
                (string)(
                    $manifest['package_base_name']
                    ??
                    ''
                )
            );


        if ($manifestPackageRoot !== $packageRootName) {
            $failures[] =
                $this->failure(
                    'package_root_mismatch',
                    $packageRootName,
                    'The archive root does not match the package manifest.',
                    $manifestPackageRoot,
                    $packageRootName
                );
        }


        $manifestPackageFile =
            trim(
                (string)(
                    $manifest['package_file_name']
                    ??
                    ''
                )
            );


        if (
            $manifestPackageFile
            !==
            basename(
                $archivePath
            )
        ) {
            $failures[] =
                $this->failure(
                    'package_filename_mismatch',
                    basename(
                        $archivePath
                    ),
                    'The archive filename does not match the package manifest.',
                    $manifestPackageFile,
                    basename(
                        $archivePath
                    )
                );
        }


        $applicationVersion =
            trim(
                (string)(
                    $manifest['application_version']
                    ??
                    ''
                )
            );


        $versionPath =
            $packageRoot
            .
            DIRECTORY_SEPARATOR
            .
            'VERSION';


        $packagedVersion =
            is_file($versionPath)
                ? trim(
                    (string)file_get_contents(
                        $versionPath
                    )
                )
                : '';


        if (
            $applicationVersion === ''
            ||
            $packagedVersion === ''
            ||
            $applicationVersion !== $packagedVersion
        ) {
            $failures[] =
                $this->failure(
                    'version_mismatch',
                    'VERSION',
                    'The VERSION file does not match the package manifest.',
                    $applicationVersion,
                    $packagedVersion
                );
        }


        foreach (
            $this->requiredPaths()
            as
            $requiredPath
        ) {
            if (
                !file_exists(
                    $this->packagePath(
                        $packageRoot,
                        $requiredPath
                    )
                )
            ) {
                $failures[] =
                    $this->failure(
                        'required_path_missing',
                        $requiredPath,
                        'A required installation path is missing.'
                    );
            }
        }


        $manifestEntries =
            $manifest['entries']
            ??
            [];


        if (!is_array($manifestEntries)) {
            throw new RuntimeException(
                'The portable package manifest entries are invalid.'
            );
        }


        $manifestEntryCount =
            count(
                $manifestEntries
            );


        $expectedEntries = [
            'PACKAGE-MANIFEST.json' =>
                true
        ];


        foreach ($manifestEntries as $entry) {

            if (!is_array($entry)) {
                $failures[] =
                    $this->failure(
                        'invalid_manifest_entry',
                        'PACKAGE-MANIFEST.json',
                        'A package manifest entry is not an object.'
                    );


                continue;
            }


            $relativePath =
                $this->validateRelativePath(
                    (string)(
                        $entry['path']
                        ??
                        ''
                    )
                );


            $expectedEntries[$relativePath] =
                true;


            foreach (
                $this->parentDirectories(
                    $relativePath
                )
                as
                $parentDirectory
            ) {
                $expectedEntries[$parentDirectory] =
                    true;
            }


            $type =
                trim(
                    (string)(
                        $entry['type']
                        ??
                        ''
                    )
                );


            $expectedMode =
                trim(
                    (string)(
                        $entry['package_mode']
                        ??
                        ''
                    )
                );


            $absolutePath =
                $this->packagePath(
                    $packageRoot,
                    $relativePath
                );


            if ($type === 'directory') {

                if (!is_dir($absolutePath)) {
                    $failures[] =
                        $this->failure(
                            'manifest_directory_missing',
                            $relativePath,
                            'A manifest-listed directory is missing.'
                        );


                    continue;
                }


                $actualMode =
                    $this->fileMode(
                        $absolutePath
                    );


                if (
                    $expectedMode !== ''
                    &&
                    $actualMode !== $expectedMode
                ) {
                    $failures[] =
                        $this->failure(
                            'directory_mode_mismatch',
                            $relativePath,
                            'A packaged directory mode does not match the manifest.',
                            $expectedMode,
                            $actualMode
                        );


                    continue;
                }


                $verifiedDirectories++;


                continue;
            }


            if ($type !== 'file') {
                $failures[] =
                    $this->failure(
                        'unsupported_manifest_entry_type',
                        $relativePath,
                        'A package manifest entry has an unsupported type.',
                        'file or directory',
                        $type
                    );


                continue;
            }


            if (!is_file($absolutePath)) {
                $failures[] =
                    $this->failure(
                        'manifest_file_missing',
                        $relativePath,
                        'A manifest-listed file is missing.'
                    );


                continue;
            }


            $expectedDigest =
                strtolower(
                    trim(
                        (string)(
                            $entry['sha256']
                            ??
                            ''
                        )
                    )
                );


            $actualDigest =
                hash_file(
                    'sha256',
                    $absolutePath
                );


            if (
                $expectedDigest === ''
                ||
                $actualDigest === false
                ||
                !hash_equals(
                    $expectedDigest,
                    $actualDigest
                )
            ) {
                $failures[] =
                    $this->failure(
                        'file_checksum_mismatch',
                        $relativePath,
                        'A packaged file digest does not match the manifest.',
                        $expectedDigest,
                        $actualDigest === false
                            ? null
                            : $actualDigest
                    );


                continue;
            }


            $actualMode =
                $this->fileMode(
                    $absolutePath
                );


            if (
                $expectedMode !== ''
                &&
                $actualMode !== $expectedMode
            ) {
                $failures[] =
                    $this->failure(
                        'file_mode_mismatch',
                        $relativePath,
                        'A packaged file mode does not match the manifest.',
                        $expectedMode,
                        $actualMode
                    );


                continue;
            }


            $verifiedFiles++;
        }


        foreach (
            $this->actualPackageEntries(
                $packageRoot
            )
            as
            $actualEntry
        ) {
            if (!isset($expectedEntries[$actualEntry])) {
                $failures[] =
                    $this->failure(
                        'unlisted_package_entry',
                        $actualEntry,
                        'The archive contains an entry that is not listed or implied by the package manifest.'
                    );
            }
        }


        foreach (
            $this->runtimeDirectories()
            as
            $runtimeDirectory
        ) {
            $absoluteDirectory =
                $this->packagePath(
                    $packageRoot,
                    $runtimeDirectory
                );


            if (!is_dir($absoluteDirectory)) {
                $failures[] =
                    $this->failure(
                        'runtime_directory_missing',
                        $runtimeDirectory,
                        'A required runtime directory is missing.'
                    );


                continue;
            }


            foreach (
                $this->recursiveFiles(
                    $absoluteDirectory
                )
                as
                $runtimeFile
            ) {
                $failures[] =
                    $this->failure(
                        'runtime_data_present',
                        $runtimeDirectory
                        .
                        '/'
                        .
                        $runtimeFile,
                        'A runtime directory contains packaged private or generated data.'
                    );
            }
        }


        foreach (
            $this->forbiddenPaths()
            as
            $forbiddenPath
        ) {
            $absolutePath =
                $this->packagePath(
                    $packageRoot,
                    $forbiddenPath
                );


            if (
                file_exists($absolutePath)
                ||
                is_link($absolutePath)
            ) {
                $failures[] =
                    $this->failure(
                        'forbidden_path_present',
                        $forbiddenPath,
                        'A forbidden path is present in the distribution archive.'
                    );
            }
        }
    }


    private function resolveArchivePath(
        string $archivePath
    ): string
    {
        $archivePath =
            trim(
                $archivePath
            );


        if ($archivePath === '') {
            throw new RuntimeException(
                'The distribution archive path cannot be empty.'
            );
        }


        if (!$this->isAbsolutePath($archivePath)) {
            $archivePath =
                $this->projectRoot
                .
                DIRECTORY_SEPARATOR
                .
                $archivePath;
        }


        $resolvedPath =
            realpath(
                $archivePath
            );


        if (
            $resolvedPath === false
            ||
            !is_file($resolvedPath)
            ||
            !is_readable($resolvedPath)
        ) {
            throw new RuntimeException(
                'The distribution archive is missing or unreadable: '
                .
                $archivePath
            );
        }


        return $resolvedPath;
    }


    private function normalizeExpectedSha256(
        ?string $expectedSha256
    ): ?string
    {
        if ($expectedSha256 === null) {
            return null;
        }


        $expectedSha256 =
            strtolower(
                trim(
                    $expectedSha256
                )
            );


        if (
            preg_match(
                '/^[a-f0-9]{64}$/',
                $expectedSha256
            )
            !==
            1
        ) {
            throw new RuntimeException(
                'The expected archive SHA-256 digest is invalid.'
            );
        }


        return $expectedSha256;
    }


    /**
     * @return array<int,string>
     */
    private function archiveEntries(
        string $output
    ): array
    {
        $entries = [];


        foreach (
            preg_split(
                '/\R/',
                $output
            )
            ?:
            []
            as
            $entry
        ) {
            $entry =
                rtrim(
                    trim(
                        $entry
                    ),
                    '/'
                );


            if ($entry === '') {
                continue;
            }


            $entries[] =
                $entry;
        }


        if ($entries === []) {
            throw new RuntimeException(
                'The distribution archive did not contain any entries.'
            );
        }


        return $entries;
    }


    private function validateRelativePath(
        string $path
    ): string
    {
        $path =
            str_replace(
                '\\',
                '/',
                trim(
                    $path
                )
            );


        $path =
            trim(
                $path,
                '/'
            );


        if (
            $path === ''
            ||
            str_contains(
                $path,
                "\0"
            )
            ||
            str_starts_with(
                $path,
                '/'
            )
            ||
            preg_match(
                '/^[A-Za-z]:\//',
                $path
            )
            ===
            1
        ) {
            throw new RuntimeException(
                'The distribution archive contains an invalid path.'
            );
        }


        foreach (
            explode(
                '/',
                $path
            )
            as
            $segment
        ) {
            if (
                $segment === ''
                ||
                $segment === '.'
                ||
                $segment === '..'
            ) {
                throw new RuntimeException(
                    'The distribution archive contains an unsafe path: '
                    .
                    $path
                );
            }
        }


        return $path;
    }


    /**
     * @param array<int,array<string,mixed>> $failures
     */
    private function verifyNoSymbolicLinks(
        string $packageRoot,
        array &$failures
    ): void
    {
        $iterator =
            new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $packageRoot,
                    FilesystemIterator::SKIP_DOTS
                ),
                RecursiveIteratorIterator::SELF_FIRST
            );


        foreach ($iterator as $entry) {

            if (!$entry->isLink()) {
                continue;
            }


            $failures[] =
                $this->failure(
                    'symbolic_link_present',
                    $this->relativePath(
                        $packageRoot,
                        $entry->getPathname()
                    ),
                    'Symbolic links are not permitted in a distribution archive.'
                );
        }
    }


    /**
     * @return array<int,string>
     */
    private function actualPackageEntries(
        string $packageRoot
    ): array
    {
        $entries = [];


        $iterator =
            new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $packageRoot,
                    FilesystemIterator::SKIP_DOTS
                ),
                RecursiveIteratorIterator::SELF_FIRST
            );


        foreach ($iterator as $entry) {
            $entries[] =
                $this->relativePath(
                    $packageRoot,
                    $entry->getPathname()
                );
        }


        sort(
            $entries,
            SORT_STRING
        );


        return $entries;
    }


    /**
     * @return array<int,string>
     */
    private function recursiveFiles(
        string $directory
    ): array
    {
        $files = [];


        $iterator =
            new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $directory,
                    FilesystemIterator::SKIP_DOTS
                )
            );


        foreach ($iterator as $entry) {

            if ($entry->isFile()) {
                $files[] =
                    $this->relativePath(
                        $directory,
                        $entry->getPathname()
                    );
            }
        }


        sort(
            $files,
            SORT_STRING
        );


        return $files;
    }


    /**
     * @return array<int,string>
     */
    private function parentDirectories(
        string $relativePath
    ): array
    {
        $parents = [];

        $directory =
            dirname(
                $relativePath
            );


        while (
            $directory !== '.'
            &&
            $directory !== ''
        ) {
            $parents[] =
                str_replace(
                    '\\',
                    '/',
                    $directory
                );


            $directory =
                dirname(
                    $directory
                );
        }


        return $parents;
    }


    private function relativePath(
        string $root,
        string $path
    ): string
    {
        return
            str_replace(
                '\\',
                '/',
                substr(
                    $path,
                    strlen(
                        rtrim(
                            $root,
                            DIRECTORY_SEPARATOR
                        )
                    )
                    +
                    1
                )
            );
    }


    private function packagePath(
        string $packageRoot,
        string $relativePath
    ): string
    {
        return
            $packageRoot
            .
            DIRECTORY_SEPARATOR
            .
            str_replace(
                '/',
                DIRECTORY_SEPARATOR,
                $relativePath
            );
    }


    private function fileMode(
        string $path
    ): ?string
    {
        $permissions =
            fileperms(
                $path
            );


        if ($permissions === false) {
            return null;
        }


        return
            substr(
                sprintf(
                    '%04o',
                    $permissions
                    &
                    07777
                ),
                -4
            );
    }


    /**
     * @return array<string,mixed>
     */
    private function failure(
        string $code,
        string $path,
        string $message,
        ?string $expected = null,
        ?string $actual = null
    ): array
    {
        return [
            'code' =>
                $code,

            'path' =>
                $path,

            'message' =>
                $message,

            'expected' =>
                $expected,

            'actual' =>
                $actual
        ];
    }


    /**
     * Authoritative required files from DistributionPackagePlanService,
     * plus the portable manifest generated during staging.
     *
     * @return array<int,string>
     */
    private function requiredPaths(): array
    {
        return [
            'PACKAGE-MANIFEST.json',
            'CHANGELOG.md',
            'composer.json',
            'composer.lock',
            'config/mail.example.php',
            'iqwurks',
            'LICENSE',
            'migrate.php',
            'README.md',
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
    private function forbiddenPaths(): array
    {
        return [
            '.git',
            '.env',
            'config/mail.php',
            'firewall-rules.txt',
            'installed-packages.txt',
            'vendor'
        ];
    }


    private function isAbsolutePath(
        string $path
    ): bool
    {
        return
            str_starts_with(
                $path,
                DIRECTORY_SEPARATOR
            )
            ||
            preg_match(
                '/^[A-Za-z]:[\\\\\/]/',
                $path
            )
            ===
            1;
    }


    private function removeDirectory(
        string $path
    ): void
    {
        if (
            !is_dir($path)
            ||
            is_link($path)
        ) {
            return;
        }


        $items =
            scandir(
                $path
            );


        if ($items === false) {
            return;
        }


        foreach ($items as $item) {

            if (
                $item === '.'
                ||
                $item === '..'
            ) {
                continue;
            }


            $itemPath =
                $path
                .
                DIRECTORY_SEPARATOR
                .
                $item;


            if (
                is_dir($itemPath)
                &&
                !is_link($itemPath)
            ) {
                $this->removeDirectory(
                    $itemPath
                );


                continue;
            }


            @unlink(
                $itemPath
            );
        }


        @rmdir(
            $path
        );
    }
}
