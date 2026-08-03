<?php
declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\CommandInterface;
use App\Services\DistributionPackageArchiveService;
use App\Services\DistributionPackageManifestService;
use Closure;
use InvalidArgumentException;
use Throwable;

final class DistributionPackageBuildCommand implements CommandInterface
{
    private Closure $previewer;

    private Closure $builder;


    public function __construct(
        ?callable $previewer = null,
        ?callable $builder = null
    )
    {
        $projectRoot =
            dirname(
                __DIR__,
                3
            );


        $this->previewer =
            $previewer === null
                ? static fn (): array =>
                    (
                        new DistributionPackageManifestService(
                            $projectRoot
                        )
                    )->manifest()
                : Closure::fromCallable(
                    $previewer
                );


        $this->builder =
            $builder === null
                ? static fn (): array =>
                    (
                        new DistributionPackageArchiveService(
                            $projectRoot
                        )
                    )->build()
                : Closure::fromCallable(
                    $builder
                );
    }


    public function name(): string
    {
        return 'package:build';
    }


    public function description(): string
    {
        return 'Preview or build a verified IQwurksPunch distribution archive.';
    }


    public function execute(
        array $arguments = []
    ): int
    {
        try {

            $buildRequested =
                $this->validateArguments(
                    $arguments
                );


            if (!$buildRequested) {

                $preview =
                    ($this->previewer)();


                if (!is_array($preview)) {
                    throw new InvalidArgumentException(
                        'The distribution package preview did not return an array.'
                    );
                }


                $this->displayPreview(
                    $preview
                );


                return
                    (
                        $preview['can_build']
                        ??
                        false
                    )
                    ===
                    true
                    &&
                    (
                        $preview['blocked']
                        ??
                        true
                    )
                    !==
                    true
                        ? 0
                        : 1;
            }


            $result =
                ($this->builder)();


            if (!is_array($result)) {
                throw new InvalidArgumentException(
                    'The distribution package builder did not return an array.'
                );
            }


            $this->displayBuildResult(
                $result
            );


            return
                (
                    $result['successful']
                    ??
                    false
                )
                ===
                true
                &&
                (
                    $result['verification']['successful']
                    ??
                    false
                )
                ===
                true
                    ? 0
                    : 1;

        } catch (Throwable $exception) {

            fwrite(
                STDERR,
                'Distribution package command failed: '
                .
                $exception->getMessage()
                .
                PHP_EOL
            );


            return 1;
        }
    }


    /**
     * @param array<int,mixed> $arguments
     */
    private function validateArguments(
        array $arguments
    ): bool
    {
        if ($arguments === []) {
            return false;
        }


        if (
            count(
                $arguments
            )
            !==
            1
        ) {
            throw new InvalidArgumentException(
                'The package:build command accepts only one optional argument: --build.'
            );
        }


        $argument =
            trim(
                (string)(
                    $arguments[0]
                    ??
                    ''
                )
            );


        if ($argument === '--build') {
            return true;
        }


        if (
            str_starts_with(
                $argument,
                '--'
            )
        ) {
            throw new InvalidArgumentException(
                'Unknown package-build argument: '
                .
                $argument
                .
                '. Supported argument: --build.'
            );
        }


        throw new InvalidArgumentException(
            'The package:build command does not accept positional arguments.'
        );
    }


    /**
     * @param array<string,mixed> $preview
     */
    private function displayPreview(
        array $preview
    ): void
    {
        $summary =
            $preview['summary']
            ??
            [];


        if (!is_array($summary)) {
            $summary = [];
        }


        echo
            'IQwurksPunch Distribution Package'
            .
            PHP_EOL;


        echo
            str_repeat(
                '=',
                72
            )
            .
            PHP_EOL;


        echo
            'Mode: PREVIEW'
            .
            PHP_EOL;


        echo
            'Status: '
            .
            (
                $preview['overall_status']
                ??
                'UNKNOWN'
            )
            .
            PHP_EOL;


        echo
            'Can build: '
            .
            $this->yesNo(
                $preview['can_build']
                ??
                false
            )
            .
            PHP_EOL;


        echo
            'Blocked: '
            .
            $this->yesNo(
                $preview['blocked']
                ??
                true
            )
            .
            PHP_EOL;


        echo
            'Application version: '
            .
            $this->displayValue(
                $preview['application_version']
                ??
                null
            )
            .
            PHP_EOL;


        echo
            'Package file: '
            .
            $this->displayValue(
                $preview['package_file_name']
                ??
                null
            )
            .
            PHP_EOL;


        echo
            'Planned artifact: '
            .
            $this->displayValue(
                $preview['artifact_path']
                ??
                null
            )
            .
            PHP_EOL;


        echo
            'Manifest SHA-256: '
            .
            $this->displayValue(
                $preview['manifest_sha256']
                ??
                null
            )
            .
            PHP_EOL;


        echo
            'Manifest entries: '
            .
            (int)(
                $summary['entries']
                ??
                0
            )
            .
            PHP_EOL;


        echo
            'Source files: '
            .
            (int)(
                $summary['files']
                ??
                0
            )
            .
            PHP_EOL;


        echo
            'Directories: '
            .
            (int)(
                $summary['directories']
                ??
                0
            )
            .
            PHP_EOL;


        echo
            'Generated runtime directories: '
            .
            (int)(
                $summary['generated_directories']
                ??
                0
            )
            .
            PHP_EOL;


        echo
            'Excluded entries: '
            .
            (int)(
                $summary['excluded_entries']
                ??
                0
            )
            .
            PHP_EOL;


        echo
            'Unsafe entries: '
            .
            (int)(
                $summary['unsafe_entries']
                ??
                0
            )
            .
            PHP_EOL;


        echo
            'Source bytes: '
            .
            number_format(
                (int)(
                    $summary['total_bytes']
                    ??
                    0
                )
            )
            .
            PHP_EOL;


        echo
            'Changes made: '
            .
            $this->yesNo(
                $preview['changes_made']
                ??
                false
            )
            .
            PHP_EOL;


        echo
            PHP_EOL
            .
            'Preview only. No staging tree or archive was created.'
            .
            PHP_EOL;


        if (
            (
                $preview['can_build']
                ??
                false
            )
            ===
            true
            &&
            (
                $preview['blocked']
                ??
                true
            )
            !==
            true
        ) {
            echo
                'Run ./iqwurks package:build --build to create the verified archive.'
                .
                PHP_EOL;

        } else {

            echo
                'Resolve the package-plan failures before attempting a build.'
                .
                PHP_EOL;
        }
    }


    /**
     * @param array<string,mixed> $result
     */
    private function displayBuildResult(
        array $result
    ): void
    {
        $verification =
            $result['verification']
            ??
            [];


        if (!is_array($verification)) {
            $verification = [];
        }


        $summary =
            $result['summary']
            ??
            [];


        if (!is_array($summary)) {
            $summary = [];
        }


        echo
            'IQwurksPunch Distribution Package'
            .
            PHP_EOL;


        echo
            str_repeat(
                '=',
                72
            )
            .
            PHP_EOL;


        echo
            'Mode: BUILD'
            .
            PHP_EOL;


        echo
            'Successful: '
            .
            $this->yesNo(
                $result['successful']
                ??
                false
            )
            .
            PHP_EOL;


        echo
            'Application version: '
            .
            $this->displayValue(
                $result['application_version']
                ??
                null
            )
            .
            PHP_EOL;


        echo
            'Package file: '
            .
            $this->displayValue(
                $result['package_file_name']
                ??
                null
            )
            .
            PHP_EOL;


        echo
            'Archive path: '
            .
            $this->displayValue(
                $result['archive_path']
                ??
                null
            )
            .
            PHP_EOL;


        echo
            'Archive size: '
            .
            number_format(
                (int)(
                    $result['archive_size_bytes']
                    ??
                    0
                )
            )
            .
            ' bytes'
            .
            PHP_EOL;


        echo
            'Archive SHA-256: '
            .
            $this->displayValue(
                $result['archive_sha256']
                ??
                null
            )
            .
            PHP_EOL;


        echo
            'Source manifest SHA-256: '
            .
            $this->displayValue(
                $result['source_manifest_sha256']
                ??
                null
            )
            .
            PHP_EOL;


        echo
            'Verification successful: '
            .
            $this->yesNo(
                $verification['successful']
                ??
                false
            )
            .
            PHP_EOL;


        echo
            'Verified files: '
            .
            (int)(
                $verification['verified_files']
                ??
                0
            )
            .
            PHP_EOL;


        echo
            'Verified directories: '
            .
            (int)(
                $verification['verified_directories']
                ??
                0
            )
            .
            PHP_EOL;


        echo
            'Verification failures: '
            .
            (int)(
                $summary['verification_failures']
                ??
                $verification['failures']
                ??
                0
            )
            .
            PHP_EOL;


        echo
            'Temporary staging removed: '
            .
            $this->yesNo(
                $result['staging_removed']
                ??
                false
            )
            .
            PHP_EOL;


        echo
            'Changes made: '
            .
            $this->yesNo(
                $result['changes_made']
                ??
                false
            )
            .
            PHP_EOL;


        if (
            (
                $result['successful']
                ??
                false
            )
            ===
            true
            &&
            (
                $verification['successful']
                ??
                false
            )
            ===
            true
        ) {
            echo
                'The verified distribution archive was created successfully.'
                .
                PHP_EOL;

        } else {

            echo
                'The distribution archive was not completed successfully.'
                .
                PHP_EOL;
        }
    }


    private function yesNo(
        mixed $value
    ): string
    {
        return
            $value === true
                ? 'yes'
                : 'no';
    }


    private function displayValue(
        mixed $value
    ): string
    {
        if ($value === null) {
            return 'Not recorded';
        }


        $value =
            trim(
                (string)$value
            );


        return
            $value === ''
                ? 'Not recorded'
                : $value;
    }
}
