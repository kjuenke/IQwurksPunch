<?php
declare(strict_types=1);

namespace App\Services;

use JsonException;
use RuntimeException;
use Throwable;

final class DistributionPackageStagingService
{
    private string $projectRoot;

    private DistributionPackageManifestService $manifests;


    public function __construct(
        ?string $projectRoot = null,
        ?DistributionPackageManifestService $manifests = null
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
                'The distribution-staging project root cannot be empty.'
            );
        }


        if (!is_dir($projectRoot)) {
            throw new RuntimeException(
                'The distribution-staging project root does not exist: '
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
                'The distribution-staging project root could not be resolved.'
            );
        }


        $this->projectRoot =
            rtrim(
                $resolvedRoot,
                DIRECTORY_SEPARATOR
            );


        $this->manifests =
            $manifests
            ??
            new DistributionPackageManifestService(
                $this->projectRoot
            );
    }


    /**
     * @return array<string,mixed>
     */
    public function stage(
        ?string $version = null,
        ?string $destination = null
    ): array
    {
        $startedAt =
            microtime(
                true
            );


        $manifest =
            $this->manifests->manifest(
                $version
            );


        if (
            (
                $manifest['can_build']
                ??
                false
            )
            !==
            true
            ||
            (
                $manifest['blocked']
                ??
                true
            )
            ===
            true
        ) {
            throw new RuntimeException(
                'The distribution package cannot be staged because its manifest is blocked.'
            );
        }


        $packageBaseName =
            trim(
                (string)(
                    $manifest['package_base_name']
                    ??
                    ''
                )
            );


        if ($packageBaseName === '') {
            throw new RuntimeException(
                'The distribution manifest did not provide a package base name.'
            );
        }


        $stagingDirectory =
            $this->resolveDestination(
                $destination,
                $packageBaseName
            );


        if (
            file_exists(
                $stagingDirectory
            )
            ||
            is_link(
                $stagingDirectory
            )
        ) {
            throw new RuntimeException(
                'The distribution staging directory already exists: '
                .
                $stagingDirectory
            );
        }


        $entries =
            $manifest['entries']
            ??
            [];


        if (!is_array($entries)) {
            throw new RuntimeException(
                'The distribution manifest did not provide valid entries.'
            );
        }


        $stagingCreated =
            false;


        try {

            $parentDirectory =
                dirname(
                    $stagingDirectory
                );


            if (
                !is_dir(
                    $parentDirectory
                )
                &&
                !mkdir(
                    $parentDirectory,
                    0770,
                    true
                )
                &&
                !is_dir(
                    $parentDirectory
                )
            ) {
                throw new RuntimeException(
                    'The distribution staging parent directory could not be created: '
                    .
                    $parentDirectory
                );
            }


            if (
                !mkdir(
                    $stagingDirectory,
                    0770,
                    true
                )
                &&
                !is_dir(
                    $stagingDirectory
                )
            ) {
                throw new RuntimeException(
                    'The distribution staging directory could not be created: '
                    .
                    $stagingDirectory
                );
            }


            $stagingCreated =
                true;


            chmod(
                $stagingDirectory,
                0770
            );


            $directoryEntries =
                array_values(
                    array_filter(
                        $entries,
                        static fn (
                            mixed $entry
                        ): bool =>
                            is_array(
                                $entry
                            )
                            &&
                            (
                                $entry['type']
                                ??
                                ''
                            )
                            ===
                            'directory'
                    )
                );


            $fileEntries =
                array_values(
                    array_filter(
                        $entries,
                        static fn (
                            mixed $entry
                        ): bool =>
                            is_array(
                                $entry
                            )
                            &&
                            (
                                $entry['type']
                                ??
                                ''
                            )
                            ===
                            'file'
                    )
                );


            usort(
                $directoryEntries,
                static fn (
                    array $left,
                    array $right
                ): int =>
                    substr_count(
                        (string)(
                            $left['path']
                            ??
                            ''
                        ),
                        '/'
                    )
                    <=>
                    substr_count(
                        (string)(
                            $right['path']
                            ??
                            ''
                        ),
                        '/'
                    )
            );


            foreach ($directoryEntries as $entry) {

                $relativePath =
                    $this->validateRelativePath(
                        (string)(
                            $entry['path']
                            ??
                            ''
                        )
                    );


                $targetDirectory =
                    $this->targetPath(
                        $stagingDirectory,
                        $relativePath
                    );


                if (
                    !is_dir(
                        $targetDirectory
                    )
                    &&
                    !mkdir(
                        $targetDirectory,
                        $this->directoryMode(
                            $entry
                        ),
                        true
                    )
                    &&
                    !is_dir(
                        $targetDirectory
                    )
                ) {
                    throw new RuntimeException(
                        'The package directory could not be created: '
                        .
                        $relativePath
                    );
                }


                chmod(
                    $targetDirectory,
                    $this->directoryMode(
                        $entry
                    )
                );
            }


            foreach ($fileEntries as $entry) {

                $this->stageFile(
                    $stagingDirectory,
                    $entry
                );
            }


            $portableManifest =
                $this->portableManifest(
                    $manifest
                );


            $manifestPath =
                $stagingDirectory
                .
                DIRECTORY_SEPARATOR
                .
                'PACKAGE-MANIFEST.json';


            try {

                $manifestJson =
                    json_encode(
                        $portableManifest,
                        JSON_PRETTY_PRINT
                        |
                        JSON_UNESCAPED_SLASHES
                        |
                        JSON_THROW_ON_ERROR
                    );

            } catch (JsonException $exception) {

                throw new RuntimeException(
                    'The portable package manifest could not be encoded: '
                    .
                    $exception->getMessage(),
                    0,
                    $exception
                );
            }


            if (
                file_put_contents(
                    $manifestPath,
                    $manifestJson
                    .
                    PHP_EOL,
                    LOCK_EX
                )
                ===
                false
            ) {
                throw new RuntimeException(
                    'The portable package manifest could not be written.'
                );
            }


            chmod(
                $manifestPath,
                0644
            );


            $verification =
                $this->verifyStaging(
                    $stagingDirectory,
                    $fileEntries
                );


            if (
                (
                    $verification['successful']
                    ??
                    false
                )
                !==
                true
            ) {
                throw new RuntimeException(
                    'The staged package failed file-integrity verification.'
                );
            }


            return [
                'mode' =>
                    'stage',

                'successful' =>
                    true,

                'application_name' =>
                    $manifest['application_name']
                    ??
                    'IQwurksPunch',

                'application_version' =>
                    $manifest['application_version']
                    ??
                    null,

                'package_base_name' =>
                    $packageBaseName,

                'package_file_name' =>
                    $manifest['package_file_name']
                    ??
                    null,

                'staging_directory' =>
                    $stagingDirectory,

                'manifest_path' =>
                    $manifestPath,

                'source_manifest_sha256' =>
                    $manifest['manifest_sha256']
                    ??
                    null,

                'staged_manifest_sha256' =>
                    hash_file(
                        'sha256',
                        $manifestPath
                    ),

                'summary' => [
                    'files' =>
                        count(
                            $fileEntries
                        )
                        +
                        1,

                    'source_files' =>
                        count(
                            $fileEntries
                        ),

                    'directories' =>
                        count(
                            $directoryEntries
                        ),

                    'verified_files' =>
                        $verification['verified_files']
                        ??
                        0,

                    'verification_failures' =>
                        $verification['failures']
                        ??
                        0
                ],

                'verification' =>
                    $verification,

                'changes_made' =>
                    true,

                'completed_at' =>
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

        } catch (Throwable $exception) {

            if (
                $stagingCreated
                &&
                is_dir(
                    $stagingDirectory
                )
            ) {
                $this->removeDirectory(
                    $stagingDirectory
                );
            }


            throw $exception;
        }
    }


    private function resolveDestination(
        ?string $destination,
        string $packageBaseName
    ): string
    {
        $destination =
            $destination === null
                ? $this->projectRoot
                    .
                    '/storage/cache/packages/'
                    .
                    $packageBaseName
                : trim(
                    $destination
                );


        if ($destination === '') {
            throw new RuntimeException(
                'The distribution staging destination cannot be empty.'
            );
        }


        if (
            str_contains(
                str_replace(
                    '\\',
                    '/',
                    $destination
                ),
                '/../'
            )
            ||
            str_ends_with(
                str_replace(
                    '\\',
                    '/',
                    $destination
                ),
                '/..'
            )
        ) {
            throw new RuntimeException(
                'The distribution staging destination cannot contain directory traversal.'
            );
        }


        if (!$this->isAbsolutePath($destination)) {
            $destination =
                $this->projectRoot
                .
                DIRECTORY_SEPARATOR
                .
                $destination;
        }


        $destination =
            rtrim(
                $destination,
                DIRECTORY_SEPARATOR
            );


        if ($destination === $this->projectRoot) {
            throw new RuntimeException(
                'The project root cannot be used as the distribution staging directory.'
            );
        }


        if (
            !str_starts_with(
                $destination,
                $this->projectRoot
                .
                DIRECTORY_SEPARATOR
            )
        ) {
            throw new RuntimeException(
                'The distribution staging directory must remain inside the project root.'
            );
        }


        return $destination;
    }


    /**
     * @param array<string,mixed> $entry
     */
    private function stageFile(
        string $stagingDirectory,
        array $entry
    ): void
    {
        $relativePath =
            $this->validateRelativePath(
                (string)(
                    $entry['path']
                    ??
                    ''
                )
            );


        $sourcePath =
            trim(
                (string)(
                    $entry['source_path']
                    ??
                    ''
                )
            );


        if (
            $sourcePath === ''
            ||
            !is_file(
                $sourcePath
            )
            ||
            is_link(
                $sourcePath
            )
            ||
            !is_readable(
                $sourcePath
            )
        ) {
            throw new RuntimeException(
                'The package source file is missing, unsafe, or unreadable: '
                .
                $relativePath
            );
        }


        $targetPath =
            $this->targetPath(
                $stagingDirectory,
                $relativePath
            );


        $targetDirectory =
            dirname(
                $targetPath
            );


        if (
            !is_dir(
                $targetDirectory
            )
            &&
            !mkdir(
                $targetDirectory,
                0755,
                true
            )
            &&
            !is_dir(
                $targetDirectory
            )
        ) {
            throw new RuntimeException(
                'The package file parent directory could not be created: '
                .
                $relativePath
            );
        }


        if (
            !copy(
                $sourcePath,
                $targetPath
            )
        ) {
            throw new RuntimeException(
                'The package source file could not be copied: '
                .
                $relativePath
            );
        }


        chmod(
            $targetPath,
            $this->fileMode(
                $relativePath
            )
        );


        $expectedDigest =
            trim(
                (string)(
                    $entry['sha256']
                    ??
                    ''
                )
            );


        $actualDigest =
            hash_file(
                'sha256',
                $targetPath
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
            throw new RuntimeException(
                'The staged package file digest does not match its manifest: '
                .
                $relativePath
            );
        }
    }


    /**
     * @param array<string,mixed> $entry
     */
    private function directoryMode(
        array $entry
    ): int
    {
        return
            (
                $entry['generated']
                ??
                false
            )
            ===
            true
                ? 0770
                : 0755;
    }


    private function fileMode(
        string $relativePath
    ): int
    {
        return
            in_array(
                $relativePath,
                [
                    'iqwurks',
                    'migrate.php'
                ],
                true
            )
                ? 0750
                : 0644;
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
                'The package manifest contains an invalid path.'
            );
        }


        $segments =
            explode(
                '/',
                $path
            );


        foreach ($segments as $segment) {

            if (
                $segment === ''
                ||
                $segment === '.'
                ||
                $segment === '..'
            ) {
                throw new RuntimeException(
                    'The package manifest contains an unsafe relative path: '
                    .
                    $path
                );
            }
        }


        return $path;
    }


    private function targetPath(
        string $stagingDirectory,
        string $relativePath
    ): string
    {
        return
            $stagingDirectory
            .
            DIRECTORY_SEPARATOR
            .
            str_replace(
                '/',
                DIRECTORY_SEPARATOR,
                $relativePath
            );
    }


    /**
     * @param array<string,mixed> $manifest
     *
     * @return array<string,mixed>
     */
    private function portableManifest(
        array $manifest
    ): array
    {
        $portableEntries = [];


        foreach (
            $manifest['entries']
            ??
            []
            as
            $entry
        ) {
            if (!is_array($entry)) {
                continue;
            }


            $portableEntries[] = [
                'path' =>
                    $entry['path']
                    ??
                    null,

                'type' =>
                    $entry['type']
                    ??
                    null,

                'generated' =>
                    (
                        $entry['generated']
                        ??
                        false
                    )
                    ===
                    true,

                'size_bytes' =>
                    (int)(
                        $entry['size_bytes']
                        ??
                        0
                    ),

                'sha256' =>
                    $entry['sha256']
                    ??
                    null,

                'package_mode' =>
                    (
                        $entry['type']
                        ??
                        ''
                    )
                    ===
                    'directory'
                        ? sprintf(
                            '%04o',
                            $this->directoryMode(
                                $entry
                            )
                        )
                        : sprintf(
                            '%04o',
                            $this->fileMode(
                                (string)(
                                    $entry['path']
                                    ??
                                    ''
                                )
                            )
                        )
            ];
        }


        return [
            'schema_version' =>
                1,

            'application_name' =>
                $manifest['application_name']
                ??
                'IQwurksPunch',

            'application_version' =>
                $manifest['application_version']
                ??
                null,

            'package_base_name' =>
                $manifest['package_base_name']
                ??
                null,

            'package_file_name' =>
                $manifest['package_file_name']
                ??
                null,

            'source_manifest_sha256' =>
                $manifest['manifest_sha256']
                ??
                null,

            'entries' =>
                $portableEntries,

            'summary' =>
                $manifest['summary']
                ??
                [],

            'created_at' =>
                date(
                    DATE_ATOM
                )
        ];
    }


    /**
     * @param array<int,array<string,mixed>> $fileEntries
     *
     * @return array<string,mixed>
     */
    private function verifyStaging(
        string $stagingDirectory,
        array $fileEntries
    ): array
    {
        $verifiedFiles =
            0;

        $failures =
            0;

        $results = [];


        foreach ($fileEntries as $entry) {

            $relativePath =
                $this->validateRelativePath(
                    (string)(
                        $entry['path']
                        ??
                        ''
                    )
                );


            $targetPath =
                $this->targetPath(
                    $stagingDirectory,
                    $relativePath
                );


            $expectedDigest =
                trim(
                    (string)(
                        $entry['sha256']
                        ??
                        ''
                    )
                );


            $actualDigest =
                is_file(
                    $targetPath
                )
                    ? hash_file(
                        'sha256',
                        $targetPath
                    )
                    : false;


            $successful =
                $expectedDigest !== ''
                &&
                $actualDigest !== false
                &&
                hash_equals(
                    $expectedDigest,
                    $actualDigest
                );


            if ($successful) {
                $verifiedFiles++;

            } else {
                $failures++;
            }


            $results[] = [
                'path' =>
                    $relativePath,

                'successful' =>
                    $successful,

                'expected_sha256' =>
                    $expectedDigest,

                'actual_sha256' =>
                    $actualDigest === false
                        ? null
                        : $actualDigest
            ];
        }


        return [
            'successful' =>
                $failures === 0,

            'verified_files' =>
                $verifiedFiles,

            'failures' =>
                $failures,

            'results' =>
                $results
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
            !is_dir(
                $path
            )
            ||
            is_link(
                $path
            )
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
                is_dir(
                    $itemPath
                )
                &&
                !is_link(
                    $itemPath
                )
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
