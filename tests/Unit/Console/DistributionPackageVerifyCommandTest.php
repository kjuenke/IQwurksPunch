<?php
declare(strict_types=1);

use App\Console\Commands\DistributionPackageVerifyCommand;
use PHPUnit\Framework\TestCase;

final class DistributionPackageVerifyCommandTest extends TestCase
{
    private string $projectRoot;


    protected function setUp(): void
    {
        parent::setUp();


        $this->projectRoot =
            sys_get_temp_dir()
            .
            '/iqwurks-package-verify-command-'
            .
            bin2hex(
                random_bytes(
                    8
                )
            );


        self::assertTrue(
            mkdir(
                $this->projectRoot,
                0770,
                true
            )
        );


        self::assertNotFalse(
            file_put_contents(
                $this->projectRoot
                .
                '/VERSION',
                "0.9.0-dev\n"
            )
        );
    }


    protected function tearDown(): void
    {
        $this->removeDirectory(
            $this->projectRoot
        );


        parent::tearDown();
    }


    public function testCommandIdentity(): void
    {
        $command =
            new DistributionPackageVerifyCommand(
                static fn (
                    string $archivePath,
                    ?string $expectedSha256
                ): array => [],
                $this->projectRoot
            );


        self::assertSame(
            'package:verify',
            $command->name()
        );


        self::assertSame(
            'Independently verify an IQwurksPunch distribution archive.',
            $command->description()
        );
    }


    public function testDefaultExecutionVerifiesCurrentVersionArchive(): void
    {
        $receivedArchive =
            null;

        $receivedSha256 =
            'not-called';


        $command =
            new DistributionPackageVerifyCommand(
                static function (
                    string $archivePath,
                    ?string $expectedSha256
                ) use (
                    &$receivedArchive,
                    &$receivedSha256
                ): array {
                    $receivedArchive =
                        $archivePath;

                    $receivedSha256 =
                        $expectedSha256;


                    return
                        self::successfulResult(
                            $archivePath,
                            null
                        );
                },
                $this->projectRoot
            );


        ob_start();


        $exitCode =
            $command->execute();


        $output =
            (string)ob_get_clean();


        self::assertSame(
            0,
            $exitCode
        );


        self::assertSame(
            $this->projectRoot
            .
            '/storage/exports/packages/iqwurkspunch-0.9.0-dev.tar.gz',
            $receivedArchive
        );


        self::assertNull(
            $receivedSha256
        );


        self::assertStringContainsString(
            'Mode: VERIFY',
            $output
        );


        self::assertStringContainsString(
            'Status: PASS',
            $output
        );


        self::assertStringContainsString(
            'Successful: yes',
            $output
        );


        self::assertStringContainsString(
            'Changes made: no',
            $output
        );


        self::assertStringContainsString(
            'passed independent verification',
            $output
        );
    }


    public function testExplicitArchiveAndChecksumArePassedToVerifier(): void
    {
        $archivePath =
            '/tmp/custom-package.tar.gz';

        $expectedSha256 =
            str_repeat(
                'a',
                64
            );

        $receivedArchive =
            null;

        $receivedSha256 =
            null;


        $command =
            new DistributionPackageVerifyCommand(
                static function (
                    string $archive,
                    ?string $sha256
                ) use (
                    &$receivedArchive,
                    &$receivedSha256
                ): array {
                    $receivedArchive =
                        $archive;

                    $receivedSha256 =
                        $sha256;


                    return
                        self::successfulResult(
                            $archive,
                            $sha256
                        );
                },
                $this->projectRoot
            );


        ob_start();


        $exitCode =
            $command->execute(
                [
                    '--sha256='
                    .
                    $expectedSha256,

                    '--archive='
                    .
                    $archivePath
                ]
            );


        $output =
            (string)ob_get_clean();


        self::assertSame(
            0,
            $exitCode
        );


        self::assertSame(
            $archivePath,
            $receivedArchive
        );


        self::assertSame(
            $expectedSha256,
            $receivedSha256
        );


        self::assertStringContainsString(
            'Expected SHA-256: '
            .
            $expectedSha256,
            $output
        );


        self::assertStringContainsString(
            'Checksum matches: yes',
            $output
        );
    }


    public function testFailedVerificationReturnsFailureAndDisplaysDetails(): void
    {
        $command =
            new DistributionPackageVerifyCommand(
                static fn (
                    string $archivePath,
                    ?string $expectedSha256
                ): array =>
                    self::failedResult(
                        $archivePath,
                        $expectedSha256
                    ),
                $this->projectRoot
            );


        ob_start();


        $exitCode =
            $command->execute(
                [
                    '--archive=/tmp/bad-package.tar.gz'
                ]
            );


        $output =
            (string)ob_get_clean();


        self::assertSame(
            1,
            $exitCode
        );


        self::assertStringContainsString(
            'Status: FAIL',
            $output
        );


        self::assertStringContainsString(
            'Verification failures: 1',
            $output
        );


        self::assertStringContainsString(
            '[file_checksum_mismatch] app/Example.php',
            $output
        );


        self::assertStringContainsString(
            'failed independent verification',
            $output
        );
    }


    public function testUnknownArgumentIsRejectedWithoutVerification(): void
    {
        $verificationCalls =
            0;


        $command =
            new DistributionPackageVerifyCommand(
                static function (
                    string $archivePath,
                    ?string $expectedSha256
                ) use (
                    &$verificationCalls
                ): array {
                    $verificationCalls++;


                    return [];
                },
                $this->projectRoot
            );


        $exitCode =
            $command->execute(
                [
                    '--unknown'
                ]
            );


        self::assertSame(
            1,
            $exitCode
        );


        self::assertSame(
            0,
            $verificationCalls
        );
    }


    public function testDuplicateArchiveOptionIsRejected(): void
    {
        $verificationCalls =
            0;


        $command =
            new DistributionPackageVerifyCommand(
                static function (
                    string $archivePath,
                    ?string $expectedSha256
                ) use (
                    &$verificationCalls
                ): array {
                    $verificationCalls++;


                    return [];
                },
                $this->projectRoot
            );


        $exitCode =
            $command->execute(
                [
                    '--archive=/tmp/one.tar.gz',
                    '--archive=/tmp/two.tar.gz'
                ]
            );


        self::assertSame(
            1,
            $exitCode
        );


        self::assertSame(
            0,
            $verificationCalls
        );
    }


    public function testInvalidChecksumIsRejected(): void
    {
        $verificationCalls =
            0;


        $command =
            new DistributionPackageVerifyCommand(
                static function (
                    string $archivePath,
                    ?string $expectedSha256
                ) use (
                    &$verificationCalls
                ): array {
                    $verificationCalls++;


                    return [];
                },
                $this->projectRoot
            );


        $exitCode =
            $command->execute(
                [
                    '--sha256=invalid'
                ]
            );


        self::assertSame(
            1,
            $exitCode
        );


        self::assertSame(
            0,
            $verificationCalls
        );
    }


    public function testPositionalArgumentIsRejected(): void
    {
        $verificationCalls =
            0;


        $command =
            new DistributionPackageVerifyCommand(
                static function (
                    string $archivePath,
                    ?string $expectedSha256
                ) use (
                    &$verificationCalls
                ): array {
                    $verificationCalls++;


                    return [];
                },
                $this->projectRoot
            );


        $exitCode =
            $command->execute(
                [
                    '/tmp/package.tar.gz'
                ]
            );


        self::assertSame(
            1,
            $exitCode
        );


        self::assertSame(
            0,
            $verificationCalls
        );
    }


    /**
     * @return array<string,mixed>
     */
    private static function successfulResult(
        string $archivePath,
        ?string $expectedSha256
    ): array
    {
        $archiveSha256 =
            $expectedSha256
            ??
            str_repeat(
                'b',
                64
            );


        return [
            'successful' =>
                true,

            'overall_status' =>
                'PASS',

            'archive_path' =>
                $archivePath,

            'archive_file_name' =>
                basename(
                    $archivePath
                ),

            'archive_size_bytes' =>
                831219,

            'archive_sha256' =>
                $archiveSha256,

            'expected_sha256' =>
                $expectedSha256,

            'checksum_matches' =>
                true,

            'package_root' =>
                'iqwurkspunch-0.9.0-dev',

            'application_version' =>
                '0.9.0-dev',

            'manifest_schema_version' =>
                1,

            'manifest_entries' =>
                364,

            'verified_files' =>
                302,

            'verified_directories' =>
                64,

            'failure_count' =>
                0,

            'failures' =>
                [],

            'temporary_workspace_removed' =>
                true,

            'changes_made' =>
                false
        ];
    }


    /**
     * @return array<string,mixed>
     */
    private static function failedResult(
        string $archivePath,
        ?string $expectedSha256
    ): array
    {
        return [
            'successful' =>
                false,

            'overall_status' =>
                'FAIL',

            'archive_path' =>
                $archivePath,

            'archive_file_name' =>
                basename(
                    $archivePath
                ),

            'archive_size_bytes' =>
                100,

            'archive_sha256' =>
                str_repeat(
                    'c',
                    64
                ),

            'expected_sha256' =>
                $expectedSha256,

            'checksum_matches' =>
                true,

            'package_root' =>
                'iqwurkspunch-0.9.0-dev',

            'application_version' =>
                '0.9.0-dev',

            'manifest_schema_version' =>
                1,

            'manifest_entries' =>
                1,

            'verified_files' =>
                0,

            'verified_directories' =>
                0,

            'failure_count' =>
                1,

            'failures' => [
                [
                    'code' =>
                        'file_checksum_mismatch',

                    'path' =>
                        'app/Example.php',

                    'message' =>
                        'A packaged file digest does not match the manifest.',

                    'expected' =>
                        str_repeat(
                            'd',
                            64
                        ),

                    'actual' =>
                        str_repeat(
                            'e',
                            64
                        )
                ]
            ],

            'temporary_workspace_removed' =>
                true,

            'changes_made' =>
                false
        ];
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


            unlink(
                $itemPath
            );
        }


        rmdir(
            $path
        );
    }
}
