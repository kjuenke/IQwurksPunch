<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use Throwable;

final class DistributionPackageArchiveService
{
    private string $projectRoot;

    private DistributionPackageManifestService $manifests;

    private DistributionPackageStagingService $staging;

    private ProcessRunnerInterface $processes;

    private DistributionPackageVerificationService $verifications;


    public function __construct(
        ?string $projectRoot = null,
        ?DistributionPackageManifestService $manifests = null,
        ?DistributionPackageStagingService $staging = null,
        ?ProcessRunnerInterface $processes = null,
        ?DistributionPackageVerificationService $verifications = null
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


        $this->processes =
            $processes
            ??
            new ProcessRunnerService(
                120.0
            );


        $this->verifications =
            $verifications
            ??
            new DistributionPackageVerificationService(
                $this->projectRoot,
                $this->processes
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


        $stagingDirectory =
            null;

        $archiveCreated =
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


            if (
                basename(
                    $stagingDirectory
                )
                !==
                $packageBaseName
            ) {
                throw new RuntimeException(
                    'The controlled staging directory name does not match the package name.'
                );
            }


            $this->normalizeContainerDirectories(
                $stagingDirectory
            );


            $archiveProcess =
                $this->processes->run(
                    [
                        'tar',
                        '--create',
                        '--gzip',
                        '--file',
                        $archivePath,
                        '--directory',
                        dirname(
                            $stagingDirectory
                        ),
                        '--owner=0',
                        '--group=0',
                        '--numeric-owner',
                        '--sort=name',
                        '--mtime=@0',
                        '--format=gnu',
                        $packageBaseName
                    ],
                    $this->projectRoot,
                    [],
                    120.0
                );


            if (
                (
                    $archiveProcess['successful']
                    ??
                    false
                )
                !==
                true
            ) {
                throw new RuntimeException(
                    'GNU tar could not create the distribution archive: '
                    .
                    trim(
                        (string)(
                            $archiveProcess['stderr']
                            ??
                            'Unknown tar failure.'
                        )
                    )
                );
            }


            if (
                !is_file(
                    $archivePath
                )
                ||
                !is_readable(
                    $archivePath
                )
            ) {
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


            $verification =
                $this->verifications->verify(
                    $archivePath,
                    $archiveSha256
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
                $firstFailure =
                    $verification['failures'][0]['message']
                    ??
                    'Unknown verification failure.';


                throw new RuntimeException(
                    'The distribution archive failed independent verification: '
                    .
                    $firstFailure
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

                'archive_process' =>
                    $archiveProcess,

                'verification' =>
                    $verification,

                'summary' => [
                    'archived_files' =>
                        (int)(
                            $verification['verified_files']
                            ??
                            0
                        )
                        +
                        1,

                    'archived_directories' =>
                        (int)(
                            $verification['verified_directories']
                            ??
                            0
                        ),

                    'verification_failures' =>
                        (int)(
                            $verification['failure_count']
                            ??
                            0
                        )
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


    private function normalizeContainerDirectories(
        string $stagingDirectory
    ): void
    {
        $directories = [
            $stagingDirectory,
            $stagingDirectory
            .
            '/database',
            $stagingDirectory
            .
            '/storage'
        ];


        foreach ($directories as $directory) {

            if (!is_dir($directory)) {
                continue;
            }


            if (
                !chmod(
                    $directory,
                    0755
                )
            ) {
                throw new RuntimeException(
                    'A package container directory could not be assigned mode 0755: '
                    .
                    $directory
                );
            }


            $permissions =
                fileperms(
                    $directory
                );


            if (
                $permissions === false
                ||
                (
                    $permissions
                    &
                    07777
                )
                !==
                0755
            ) {
                throw new RuntimeException(
                    'A package container directory did not retain mode 0755: '
                    .
                    $directory
                );
            }
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


        if (
            in_array(
                '..',
                explode(
                    '/',
                    $normalized
                ),
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
