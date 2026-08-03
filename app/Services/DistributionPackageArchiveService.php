<?php
declare(strict_types=1);

namespace App\Services;

use JsonException;
use Phar;
use PharData;
use RuntimeException;
use Throwable;

final class DistributionPackageArchiveService
{
    private string $projectRoot;

    private DistributionPackageManifestService $manifests;

    private DistributionPackageStagingService $staging;


    public function __construct(
        ?string $projectRoot = null,
        ?DistributionPackageManifestService $manifests = null,
        ?DistributionPackageStagingService $staging = null
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
                'The distribution-archive project root cannot be empty.'
            );
        }


        if (!is_dir($projectRoot)) {
            throw new RuntimeException(
                'The distribution-archive project root does not exist: '
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
                'The distribution-archive project root could not be resolved.'
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


        $this->staging =
            $staging
            ??
            new DistributionPackageStagingService(
                $this->projectRoot,
                $this->manifests
            );
    }


    /**
     * @return array<string,mixed>
     */
    public function build(
        ?string $version = null,
        ?string $stagingDestination = null,
        ?string $artifactDirectory = null
    ): array
    {
        $startedAt =
            microtime(
                true
            );


        if (
            !class_exists(
                PharData::class
            )
        ) {
            throw new RuntimeException(
                'The PHP Phar extension is required to build a distribution archive.'
            );
        }


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
                'The distribution archive cannot be built because its manifest is blocked.'
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


        $packageFileName =
            trim(
                (string)(
                    $manifest['package_file_name']
                    ??
                    ''
                )
            );


        if (
            $packageBaseName === ''
            ||
            $packageFileName === ''
        ) {
            throw new RuntimeException(
                'The distribution manifest did not provide valid package names.'
            );
        }


        $artifactDirectory =
            $this->resolveArtifactDirectory(
                $artifactDirectory
            );


        $archivePath =
            $artifactDirectory
            .
            DIRECTORY_SEPARATOR
            .
            $packageFileName;


        $tarPath =
            $artifactDirectory
            .
            DIRECTORY_SEPARATOR
            .
            $packageBaseName
            .
            '.tar';


        if (
            file_exists(
                $archivePath
            )
            ||
            is_link(
                $archivePath
            )
        ) {
            throw new RuntimeException(
                'The distribution archive already exists: '
                .
                $archivePath
            );
        }


        if (
            file_exists(
                $tarPath
            )
            ||
            is_link(
                $tarPath
            )
        ) {
            throw new RuntimeException(
                'The temporary distribution archive already exists: '
                .
                $tarPath
            );
        }


        $stagingDirectory =
            null;

        $archiveCreated =
            false;

        $tarCreated =
            false;


        try {

            if (
                !is_dir(
                    $artifactDirectory
                )
                &&
                !mkdir(
                    $artifactDirectory,
                    0770,
                    true
                )
                &&
                !is_dir(
                    $artifactDirectory
                )
            ) {
                throw new RuntimeException(
                    'The distribution artifact directory could not be created: '
                    .
                    $artifactDirectory
                );
            }


            chmod(
                $artifactDirectory,
                0770
            );


            $stagingResult =
                $this->staging->stage(
                    $version,
                    $stagingDestination
                );


            $stagingDirectory =
                trim(
                    (string)(
                        $stagingResult['staging_directory']
                        ??
                        ''
                    )
                );


            if (
                $stagingDirectory === ''
                ||
                !is_dir(
                    $stagingDirectory
                )
                ||
                is_link(
                    $stagingDirectory
                )
            ) {
                throw new RuntimeException(
                    'The controlled staging service did not produce a valid staging directory.'
                );
            }


            $stagedEntries =
                $this->collectStagingEntries(
                    $stagingDirectory
                );


            $tar =
                new PharData(
                    $tarPath
                );


            $tarCreated =
                true;


            $tar->addEmptyDir(
                $packageBaseName
            );


            $directoryEntries =
                array_values(
                    array_filter(
                        $stagedEntries,
                        static fn (
                            array $entry
                        ): bool =>
                            (
                                $entry['type']
                                ??
                                ''
                            )
                            ===
                            'directory'
                    )
                );


            usort(
                $directoryEntries,
                static function (
                    array $left,
                    array $right
                ): int {
                    $leftPath =
                        (string)(
                            $left['path']
                            ??
                            ''
                        );


                    $rightPath =
                        (string)(
                            $right['path']
                            ??
                            ''
                        );


                    $depthComparison =
                        substr_count(
                            $leftPath,
                            '/'
                        )
                        <=>
                        substr_count(
                            $rightPath,
                            '/'
                        );


                    return
                        $depthComparison !== 0
                            ? $depthComparison
                            : strcmp(
                                $leftPath,
                                $rightPath
                            );
                }
            );


            foreach ($directoryEntries as $entry) {

                $tar->addEmptyDir(
                    $packageBaseName
                    .
                    '/'
                    .
                    $entry['path']
                );
            }


            foreach ($stagedEntries as $entry) {

                if (
                    (
                        $entry['type']
                        ??
                        ''
                    )
                    !==
                    'file'
                ) {
                    continue;
                }


                $tar->addFile(
                    $entry['absolute_path'],
                    $packageBaseName
                    .
                    '/'
                    .
                    $entry['path']
                );
            }


            unset(
                $tar
            );


            $tarArchive =
                new PharData(
                    $tarPath
                );


            $compressedArchive =
                $tarArchive->compress(
                    Phar::GZ
                );


            unset(
                $compressedArchive,
                $tarArchive
            );


            if (!is_file($archivePath)) {
                throw new RuntimeException(
                    'The compressed distribution archive was not created.'
                );
            }


            $archiveCreated =
                true;


            chmod(
                $archivePath,
                0640
            );


            if (
                is_file(
                    $tarPath
                )
                &&
                !unlink(
                    $tarPath
                )
            ) {
                throw new RuntimeException(
                    'The temporary uncompressed distribution archive could not be removed.'
                );
            }


            $tarCreated =
                false;


            $verification =
                $this->verifyArchive(
                    $archivePath,
                    $packageBaseName,
                    $stagedEntries,
                    (string)(
                        $stagingResult['staged_manifest_sha256']
                        ??
                        ''
                    )
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
                    'The distribution archive failed integrity verification.'
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


            $this->removeDirectory(
                $stagingDirectory
            );


            if (is_dir($stagingDirectory)) {
                throw new RuntimeException(
                    'The temporary distribution staging directory could not be removed.'
                );
            }


            return [
                'mode' =>
                    'build',

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
                    $packageFileName,

                'archive_path' =>
                    $archivePath,

                'archive_size_bytes' =>
                    $archiveSize,

                'archive_sha256' =>
                    $archiveSha256,

                'source_manifest_sha256' =>
                    $manifest['manifest_sha256']
                    ??
                    null,

                'staged_manifest_sha256' =>
                    $stagingResult['staged_manifest_sha256']
                    ??
                    null,

                'staging_directory' =>
                    $stagingDirectory,

                'staging_removed' =>
                    true,

                'verification' =>
                    $verification,

                'summary' => [
                    'archived_files' =>
                        $verification['verified_files']
                        ??
                        0,

                    'archived_directories' =>
                        $verification['verified_directories']
                        ??
                        0,

                    'verification_failures' =>
                        $verification['failures']
                        ??
                        0
                ],

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
                $tarCreated
                &&
                is_file(
                    $tarPath
                )
            ) {
                @unlink(
                    $tarPath
                );
            }


            if (
                $archiveCreated
                &&
                is_file(
                    $archivePath
                )
            ) {
                @unlink(
                    $archivePath
                );
            }


            if (
                $stagingDirectory !== null
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


    private function resolveArtifactDirectory(
        ?string $artifactDirectory
    ): string
    {
        $artifactDirectory =
            $artifactDirectory === null
                ? $this->projectRoot
                    .
                    '/storage/exports/packages'
                : trim(
                    $artifactDirectory
                );


        if ($artifactDirectory === '') {
            throw new RuntimeException(
                'The distribution artifact directory cannot be empty.'
            );
        }


        $normalized =
            str_replace(
                '\\',
                '/',
                $artifactDirectory
            );


        $segments =
            explode(
                '/',
                $normalized
            );


        if (
            in_array(
                '..',
                $segments,
                true
            )
        ) {
            throw new RuntimeException(
                'The distribution artifact directory cannot contain directory traversal.'
            );
        }


        if (!$this->isAbsolutePath($artifactDirectory)) {
            $artifactDirectory =
                $this->projectRoot
                .
                DIRECTORY_SEPARATOR
                .
                $artifactDirectory;
        }


        $artifactDirectory =
            rtrim(
                $artifactDirectory,
                DIRECTORY_SEPARATOR
            );


        if ($artifactDirectory === $this->projectRoot) {
            throw new RuntimeException(
                'The project root cannot be used as the distribution artifact directory.'
            );
        }


        if (
            !str_starts_with(
                $artifactDirectory,
                $this->projectRoot
                .
                DIRECTORY_SEPARATOR
            )
        ) {
            throw new RuntimeException(
                'The distribution artifact directory must remain inside the project root.'
            );
        }


        return $artifactDirectory;
    }


    /**
     * @return array<int,array<string,mixed>>
     */
    private function collectStagingEntries(
        string $stagingDirectory
    ): array
    {
        $entries = [];


        $this->walkStagingDirectory(
            $stagingDirectory,
            '',
            $entries
        );


        usort(
            $entries,
            static fn (
                array $left,
                array $right
            ): int =>
                strcmp(
                    (string)(
                        $left['path']
                        ??
                        ''
                    ),
                    (string)(
                        $right['path']
                        ??
                        ''
                    )
                )
        );


        return $entries;
    }


    /**
     * @param array<int,array<string,mixed>> $entries
     */
    private function walkStagingDirectory(
        string $absoluteDirectory,
        string $relativeDirectory,
        array &$entries
    ): void
    {
        $items =
            scandir(
                $absoluteDirectory
            );


        if ($items === false) {
            throw new RuntimeException(
                'The distribution staging directory could not be read.'
            );
        }


        foreach ($items as $item) {

            if (
                $item === '.'
                ||
                $item === '..'
            ) {
                continue;
            }


            $relativePath =
                $relativeDirectory === ''
                    ? $item
                    : $relativeDirectory
                        .
                        '/'
                        .
                        $item;


            $absolutePath =
                $absoluteDirectory
                .
                DIRECTORY_SEPARATOR
                .
                $item;


            if (is_link($absolutePath)) {
                throw new RuntimeException(
                    'Symbolic links are not permitted in the distribution staging tree: '
                    .
                    $relativePath
                );
            }


            if (is_dir($absolutePath)) {

                $entries[] = [
                    'path' =>
                        $relativePath,

                    'absolute_path' =>
                        $absolutePath,

                    'type' =>
                        'directory',

                    'size_bytes' =>
                        0,

                    'sha256' =>
                        null
                ];


                $this->walkStagingDirectory(
                    $absolutePath,
                    $relativePath,
                    $entries
                );


                continue;
            }


            if (!is_file($absolutePath)) {
                throw new RuntimeException(
                    'An unsupported entry was found in the distribution staging tree: '
                    .
                    $relativePath
                );
            }


            if (!is_readable($absolutePath)) {
                throw new RuntimeException(
                    'A distribution staging file is not readable: '
                    .
                    $relativePath
                );
            }


            $size =
                filesize(
                    $absolutePath
                );


            $sha256 =
                hash_file(
                    'sha256',
                    $absolutePath
                );


            if (
                $size === false
                ||
                $sha256 === false
            ) {
                throw new RuntimeException(
                    'A distribution staging file could not be inspected: '
                    .
                    $relativePath
                );
            }


            $entries[] = [
                'path' =>
                    $relativePath,

                'absolute_path' =>
                    $absolutePath,

                'type' =>
                    'file',

                'size_bytes' =>
                    $size,

                'sha256' =>
                    $sha256
            ];
        }
    }


    /**
     * @param array<int,array<string,mixed>> $stagedEntries
     *
     * @return array<string,mixed>
     */
    private function verifyArchive(
        string $archivePath,
        string $packageBaseName,
        array $stagedEntries,
        string $expectedPortableManifestSha256
    ): array
    {
        $verifiedFiles =
            0;

        $verifiedDirectories =
            0;

        $failures = [];


        $archiveRoot =
            'phar://'
            .
            $archivePath
            .
            '/'
            .
            $packageBaseName;


        if (!is_dir($archiveRoot)) {
            $failures[] = [
                'path' =>
                    $packageBaseName,

                'reason' =>
                    'package_root_missing'
            ];
        }


        foreach ($stagedEntries as $entry) {

            $relativePath =
                (string)(
                    $entry['path']
                    ??
                    ''
                );


            $archiveEntry =
                $archiveRoot
                .
                '/'
                .
                $relativePath;


            if (
                (
                    $entry['type']
                    ??
                    ''
                )
                ===
                'directory'
            ) {
                if (is_dir($archiveEntry)) {
                    $verifiedDirectories++;

                } else {
                    $failures[] = [
                        'path' =>
                            $relativePath,

                        'reason' =>
                            'directory_missing'
                    ];
                }


                continue;
            }


            if (!is_file($archiveEntry)) {
                $failures[] = [
                    'path' =>
                        $relativePath,

                    'reason' =>
                        'file_missing'
                ];


                continue;
            }


            $actualDigest =
                hash_file(
                    'sha256',
                    $archiveEntry
                );


            $expectedDigest =
                (string)(
                    $entry['sha256']
                    ??
                    ''
                );


            if (
                $actualDigest === false
                ||
                $expectedDigest === ''
                ||
                !hash_equals(
                    $expectedDigest,
                    $actualDigest
                )
            ) {
                $failures[] = [
                    'path' =>
                        $relativePath,

                    'reason' =>
                        'digest_mismatch',

                    'expected_sha256' =>
                        $expectedDigest,

                    'actual_sha256' =>
                        $actualDigest === false
                            ? null
                            : $actualDigest
                ];


                continue;
            }


            $verifiedFiles++;
        }


        $portableManifestPath =
            $archiveRoot
            .
            '/PACKAGE-MANIFEST.json';


        $portableManifestSha256 =
            is_file(
                $portableManifestPath
            )
                ? hash_file(
                    'sha256',
                    $portableManifestPath
                )
                : false;


        if (
            $expectedPortableManifestSha256 === ''
            ||
            $portableManifestSha256 === false
            ||
            !hash_equals(
                $expectedPortableManifestSha256,
                $portableManifestSha256
            )
        ) {
            $failures[] = [
                'path' =>
                    'PACKAGE-MANIFEST.json',

                'reason' =>
                    'portable_manifest_digest_mismatch',

                'expected_sha256' =>
                    $expectedPortableManifestSha256,

                'actual_sha256' =>
                    $portableManifestSha256 === false
                        ? null
                        : $portableManifestSha256
            ];
        }


        $portableManifest =
            null;


        if (is_file($portableManifestPath)) {

            $contents =
                file_get_contents(
                    $portableManifestPath
                );


            if ($contents !== false) {

                try {

                    $decoded =
                        json_decode(
                            $contents,
                            true,
                            512,
                            JSON_THROW_ON_ERROR
                        );


                    if (is_array($decoded)) {
                        $portableManifest =
                            $decoded;
                    }

                } catch (JsonException $exception) {

                    $failures[] = [
                        'path' =>
                            'PACKAGE-MANIFEST.json',

                        'reason' =>
                            'portable_manifest_invalid_json',

                        'message' =>
                            $exception->getMessage()
                    ];
                }
            }
        }


        return [
            'successful' =>
                $failures === [],

            'verified_files' =>
                $verifiedFiles,

            'verified_directories' =>
                $verifiedDirectories,

            'failures' =>
                count(
                    $failures
                ),

            'failure_details' =>
                $failures,

            'portable_manifest_sha256' =>
                $portableManifestSha256 === false
                    ? null
                    : $portableManifestSha256,

            'portable_manifest_schema_version' =>
                is_array($portableManifest)
                    ? $portableManifest['schema_version']
                        ??
                        null
                    : null
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
