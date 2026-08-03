<?php
declare(strict_types=1);

use App\Console\Commands\DistributionPackageBuildCommand;
use PHPUnit\Framework\TestCase;

final class DistributionPackageBuildCommandTest extends TestCase
{
    public function testCommandIdentity(): void
    {
        $command =
            new DistributionPackageBuildCommand(
                static fn (): array => [],
                static fn (): array => []
            );


        self::assertSame(
            'package:build',
            $command->name()
        );


        self::assertSame(
            'Preview or build a verified IQwurksPunch distribution archive.',
            $command->description()
        );
    }


    public function testDefaultExecutionDisplaysReadOnlyPreview(): void
    {
        $previewCalls =
            0;

        $buildCalls =
            0;


        $command =
            new DistributionPackageBuildCommand(
                static function () use (
                    &$previewCalls
                ): array {
                    $previewCalls++;


                    return
                        self::previewResult();
                },
                static function () use (
                    &$buildCalls
                ): array {
                    $buildCalls++;


                    return
                        self::buildResult();
                }
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
            1,
            $previewCalls
        );


        self::assertSame(
            0,
            $buildCalls
        );


        self::assertStringContainsString(
            'Mode: PREVIEW',
            $output
        );


        self::assertStringContainsString(
            'Can build: yes',
            $output
        );


        self::assertStringContainsString(
            'Changes made: no',
            $output
        );


        self::assertStringContainsString(
            'No staging tree or archive was created.',
            $output
        );
    }


    public function testBlockedPreviewReturnsFailureWithoutBuilding(): void
    {
        $buildCalls =
            0;


        $command =
            new DistributionPackageBuildCommand(
                static function (): array {
                    $preview =
                        self::previewResult();


                    $preview['overall_status'] =
                        'FAIL';


                    $preview['blocked'] =
                        true;


                    $preview['can_build'] =
                        false;


                    return $preview;
                },
                static function () use (
                    &$buildCalls
                ): array {
                    $buildCalls++;


                    return
                        self::buildResult();
                }
            );


        ob_start();


        $exitCode =
            $command->execute();


        $output =
            (string)ob_get_clean();


        self::assertSame(
            1,
            $exitCode
        );


        self::assertSame(
            0,
            $buildCalls
        );


        self::assertStringContainsString(
            'Status: FAIL',
            $output
        );


        self::assertStringContainsString(
            'Can build: no',
            $output
        );


        self::assertStringContainsString(
            'Resolve the package-plan failures',
            $output
        );
    }


    public function testBuildArgumentCreatesVerifiedArchive(): void
    {
        $previewCalls =
            0;

        $buildCalls =
            0;


        $command =
            new DistributionPackageBuildCommand(
                static function () use (
                    &$previewCalls
                ): array {
                    $previewCalls++;


                    return
                        self::previewResult();
                },
                static function () use (
                    &$buildCalls
                ): array {
                    $buildCalls++;


                    return
                        self::buildResult();
                }
            );


        ob_start();


        $exitCode =
            $command->execute(
                [
                    '--build'
                ]
            );


        $output =
            (string)ob_get_clean();


        self::assertSame(
            0,
            $exitCode
        );


        self::assertSame(
            0,
            $previewCalls
        );


        self::assertSame(
            1,
            $buildCalls
        );


        self::assertStringContainsString(
            'Mode: BUILD',
            $output
        );


        self::assertStringContainsString(
            'Successful: yes',
            $output
        );


        self::assertStringContainsString(
            'Verification successful: yes',
            $output
        );


        self::assertStringContainsString(
            'Temporary staging removed: yes',
            $output
        );


        self::assertStringContainsString(
            'The verified distribution archive was created successfully.',
            $output
        );
    }


    public function testUnknownArgumentIsRejectedWithoutPreviewOrBuild(): void
    {
        $previewCalls =
            0;

        $buildCalls =
            0;


        $command =
            new DistributionPackageBuildCommand(
                static function () use (
                    &$previewCalls
                ): array {
                    $previewCalls++;


                    return [];
                },
                static function () use (
                    &$buildCalls
                ): array {
                    $buildCalls++;


                    return [];
                }
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
            $previewCalls
        );


        self::assertSame(
            0,
            $buildCalls
        );
    }


    /**
     * @return array<string,mixed>
     */
    private static function previewResult(): array
    {
        return [
            'overall_status' =>
                'PASS',

            'blocked' =>
                false,

            'can_build' =>
                true,

            'application_version' =>
                '0.9.0-dev',

            'package_file_name' =>
                'iqwurkspunch-0.9.0-dev.tar.gz',

            'artifact_path' =>
                '/var/www/IQwurksPunch/storage/exports/packages/iqwurkspunch-0.9.0-dev.tar.gz',

            'manifest_sha256' =>
                str_repeat(
                    'a',
                    64
                ),

            'summary' => [
                'entries' =>
                    100,

                'files' =>
                    80,

                'directories' =>
                    20,

                'generated_directories' =>
                    6,

                'excluded_entries' =>
                    5,

                'unsafe_entries' =>
                    0,

                'total_bytes' =>
                    123456
            ],

            'changes_made' =>
                false
        ];
    }


    /**
     * @return array<string,mixed>
     */
    private static function buildResult(): array
    {
        return [
            'successful' =>
                true,

            'application_version' =>
                '0.9.0-dev',

            'package_file_name' =>
                'iqwurkspunch-0.9.0-dev.tar.gz',

            'archive_path' =>
                '/var/www/IQwurksPunch/storage/exports/packages/iqwurkspunch-0.9.0-dev.tar.gz',

            'archive_size_bytes' =>
                45678,

            'archive_sha256' =>
                str_repeat(
                    'b',
                    64
                ),

            'source_manifest_sha256' =>
                str_repeat(
                    'a',
                    64
                ),

            'staging_removed' =>
                true,

            'verification' => [
                'successful' =>
                    true,

                'verified_files' =>
                    80,

                'verified_directories' =>
                    20,

                'failures' =>
                    0
            ],

            'summary' => [
                'archived_files' =>
                    80,

                'archived_directories' =>
                    20,

                'verification_failures' =>
                    0
            ],

            'changes_made' =>
                true
        ];
    }
}
