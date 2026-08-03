<?php
declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\CommandInterface;
use App\Services\DistributionPackageVerificationService;
use Closure;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class DistributionPackageVerifyCommand implements CommandInterface
{
    private string $projectRoot;

    private Closure $verifier;


    public function __construct(
        ?callable $verifier = null,
        ?string $projectRoot = null
    )
    {
        $projectRoot =
            $projectRoot
            ??
            dirname(
                __DIR__,
                3
            );


        $projectRoot =
            rtrim(
                trim(
                    $projectRoot
                ),
                DIRECTORY_SEPARATOR
            );


        if (
            $projectRoot === ''
            ||
            !is_dir(
                $projectRoot
            )
        ) {
            throw new InvalidArgumentException(
                'The package-verification project root is invalid.'
            );
        }


        $resolvedRoot =
            realpath(
                $projectRoot
            );


        if ($resolvedRoot === false) {
            throw new InvalidArgumentException(
                'The package-verification project root could not be resolved.'
            );
        }


        $this->projectRoot =
            rtrim(
                $resolvedRoot,
                DIRECTORY_SEPARATOR
            );


        $this->verifier =
            $verifier === null
                ? static fn (
                    string $archivePath,
                    ?string $expectedSha256
                ): array =>
                    (
                        new DistributionPackageVerificationService(
                            $resolvedRoot
                        )
                    )->verify(
                        $archivePath,
                        $expectedSha256
                    )
                : Closure::fromCallable(
                    $verifier
                );
    }


    public function name(): string
    {
        return 'package:verify';
    }


    public function description(): string
    {
        return 'Independently verify an IQwurksPunch distribution archive.';
    }


    public function execute(
        array $arguments = []
    ): int
    {
        try {

            $options =
                $this->validateArguments(
                    $arguments
                );


            $archivePath =
                $options['archive']
                ??
                $this->defaultArchivePath();


            $result =
                ($this->verifier)(
                    $archivePath,
                    $options['sha256']
                );


            if (!is_array($result)) {
                throw new RuntimeException(
                    'The distribution package verifier did not return an array.'
                );
            }


            $this->displayResult(
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
                    $result['failure_count']
                    ??
                    1
                )
                ===
                0
                    ? 0
                    : 1;

        } catch (Throwable $exception) {

            fwrite(
                STDERR,
                'Distribution package verification failed: '
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
     *
     * @return array{
     *     archive:?string,
     *     sha256:?string
     * }
     */
    private function validateArguments(
        array $arguments
    ): array
    {
        $archivePath =
            null;

        $expectedSha256 =
            null;


        foreach ($arguments as $argument) {

            $argument =
                trim(
                    (string)$argument
                );


            if (
                str_starts_with(
                    $argument,
                    '--archive='
                )
            ) {
                if ($archivePath !== null) {
                    throw new InvalidArgumentException(
                        'The --archive option may be provided only once.'
                    );
                }


                $archivePath =
                    trim(
                        substr(
                            $argument,
                            strlen(
                                '--archive='
                            )
                        )
                    );


                if ($archivePath === '') {
                    throw new InvalidArgumentException(
                        'The --archive option requires a file path.'
                    );
                }


                continue;
            }


            if (
                str_starts_with(
                    $argument,
                    '--sha256='
                )
            ) {
                if ($expectedSha256 !== null) {
                    throw new InvalidArgumentException(
                        'The --sha256 option may be provided only once.'
                    );
                }


                $expectedSha256 =
                    strtolower(
                        trim(
                            substr(
                                $argument,
                                strlen(
                                    '--sha256='
                                )
                            )
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
                    throw new InvalidArgumentException(
                        'The --sha256 option requires a 64-character hexadecimal digest.'
                    );
                }


                continue;
            }


            if (
                str_starts_with(
                    $argument,
                    '--'
                )
            ) {
                throw new InvalidArgumentException(
                    'Unknown package-verification argument: '
                    .
                    $argument
                    .
                    '. Supported options are --archive=PATH and --sha256=DIGEST.'
                );
            }


            throw new InvalidArgumentException(
                'The package:verify command does not accept positional arguments.'
            );
        }


        return [
            'archive' =>
                $archivePath,

            'sha256' =>
                $expectedSha256
        ];
    }


    private function defaultArchivePath(): string
    {
        $versionPath =
            $this->projectRoot
            .
            DIRECTORY_SEPARATOR
            .
            'VERSION';


        if (
            !is_file(
                $versionPath
            )
            ||
            !is_readable(
                $versionPath
            )
        ) {
            throw new RuntimeException(
                'The application VERSION file is missing or unreadable.'
            );
        }


        $version =
            trim(
                (string)file_get_contents(
                    $versionPath
                )
            );


        if (
            $version === ''
            ||
            preg_match(
                '/^[A-Za-z0-9][A-Za-z0-9._-]*$/',
                $version
            )
            !==
            1
        ) {
            throw new RuntimeException(
                'The application VERSION file contains an invalid version.'
            );
        }


        return
            $this->projectRoot
            .
            '/storage/exports/packages/iqwurkspunch-'
            .
            $version
            .
            '.tar.gz';
    }


    /**
     * @param array<string,mixed> $result
     */
    private function displayResult(
        array $result
    ): void
    {
        $failures =
            $result['failures']
            ??
            [];


        if (!is_array($failures)) {
            $failures = [];
        }


        echo
            'IQwurksPunch Distribution Package Verification'
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
            'Mode: VERIFY'
            .
            PHP_EOL;


        echo
            'Status: '
            .
            $this->displayValue(
                $result['overall_status']
                ??
                null
            )
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
            'Archive file: '
            .
            $this->displayValue(
                $result['archive_file_name']
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
            'Expected SHA-256: '
            .
            $this->displayValue(
                $result['expected_sha256']
                ??
                null
            )
            .
            PHP_EOL;


        echo
            'Checksum matches: '
            .
            $this->yesNo(
                $result['checksum_matches']
                ??
                false
            )
            .
            PHP_EOL;


        echo
            'Package root: '
            .
            $this->displayValue(
                $result['package_root']
                ??
                null
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
            'Manifest schema: '
            .
            $this->displayValue(
                $result['manifest_schema_version']
                ??
                null
            )
            .
            PHP_EOL;


        echo
            'Manifest entries: '
            .
            (int)(
                $result['manifest_entries']
                ??
                0
            )
            .
            PHP_EOL;


        echo
            'Verified files: '
            .
            (int)(
                $result['verified_files']
                ??
                0
            )
            .
            PHP_EOL;


        echo
            'Verified directories: '
            .
            (int)(
                $result['verified_directories']
                ??
                0
            )
            .
            PHP_EOL;


        echo
            'Verification failures: '
            .
            (int)(
                $result['failure_count']
                ??
                count(
                    $failures
                )
            )
            .
            PHP_EOL;


        echo
            'Temporary workspace removed: '
            .
            $this->yesNo(
                $result['temporary_workspace_removed']
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


        if ($failures !== []) {

            echo
                PHP_EOL
                .
                'Failures:'
                .
                PHP_EOL;


            foreach ($failures as $failure) {

                if (!is_array($failure)) {
                    continue;
                }


                echo
                    '- ['
                    .
                    $this->displayValue(
                        $failure['code']
                        ??
                        null
                    )
                    .
                    '] '
                    .
                    $this->displayValue(
                        $failure['path']
                        ??
                        null
                    )
                    .
                    ': '
                    .
                    $this->displayValue(
                        $failure['message']
                        ??
                        null
                    )
                    .
                    PHP_EOL;


                if (
                    array_key_exists(
                        'expected',
                        $failure
                    )
                    &&
                    $failure['expected'] !== null
                ) {
                    echo
                        '  Expected: '
                        .
                        $this->displayValue(
                            $failure['expected']
                        )
                        .
                        PHP_EOL;
                }


                if (
                    array_key_exists(
                        'actual',
                        $failure
                    )
                    &&
                    $failure['actual'] !== null
                ) {
                    echo
                        '  Actual:   '
                        .
                        $this->displayValue(
                            $failure['actual']
                        )
                        .
                        PHP_EOL;
                }
            }
        }


        echo PHP_EOL;


        if (
            (
                $result['successful']
                ??
                false
            )
            ===
            true
        ) {
            echo
                'The distribution archive passed independent verification.'
                .
                PHP_EOL;

        } else {

            echo
                'The distribution archive failed independent verification.'
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
            return 'Not provided';
        }


        $value =
            trim(
                (string)$value
            );


        return
            $value === ''
                ? 'Not provided'
                : $value;
    }
}
