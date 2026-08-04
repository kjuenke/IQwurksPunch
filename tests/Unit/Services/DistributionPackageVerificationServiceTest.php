<?php
declare(strict_types=1);

use App\Services\DistributionPackageArchiveService;
use App\Services\DistributionPackageVerificationService;
use App\Services\ProcessRunnerService;
use PHPUnit\Framework\TestCase;

final class DistributionPackageVerificationServiceTest extends TestCase
{
    private string $projectRoot;


    protected function setUp(): void
    {
        parent::setUp();


        $this->projectRoot =
            sys_get_temp_dir()
            .
            '/iqwurks-package-verification-'
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


        foreach (
            [
                'app',
                'bootstrap',
                'config',
                'database/migrations',
                'database/seeds',
                'database/sqlite',
                'deployment',
                'docs',
                'plugins',
                'public',
                'routes',
                'storage/backups',
                'storage/cache',
                'storage/exports',
                'storage/logs',
                'storage/sessions',
                'tests'
            ]
            as
            $directory
        ) {
            self::assertTrue(
                mkdir(
                    $this->projectRoot
                    .
                    '/'
                    .
                    $directory,
                    0770,
                    true
                )
            );
        }


        foreach (
            [
                '.gitignore',
                'CHANGELOG.md',
                'composer.json',
                'composer.lock',
                'config/mail.example.php',
                'deployment/README.md',
                'iqwurks',
                'LICENSE',
                'migrate.php',
                'phpunit.xml',
                'public/index.php',
                'README.md',
                'ROADMAP.md',
                'VERSION'
            ]
            as
            $file
        ) {
            self::assertNotFalse(
                file_put_contents(
                    $this->projectRoot
                    .
                    '/'
                    .
                    $file,
                    $file === 'VERSION'
                        ? "0.9.0-dev\n"
                        : $file
                        .
                        "\n"
                )
            );
        }


        chmod(
            $this->projectRoot
            .
            '/iqwurks',
            0750
        );


        chmod(
            $this->projectRoot
            .
            '/migrate.php',
            0750
        );


        self::assertNotFalse(
            file_put_contents(
                $this->projectRoot
                .
                '/app/Example.php',
                "<?php\n"
                .
                "declare(strict_types=1);\n"
            )
        );


        self::assertNotFalse(
            file_put_contents(
                $this->projectRoot
                .
                '/config/mail.php',
                "<?php return ['password' => 'secret'];\n"
            )
        );


        self::assertNotFalse(
            file_put_contents(
                $this->projectRoot
                .
                '/database/sqlite/iqwurks.sqlite',
                'private database'
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


    public function testValidDistributionArchivePassesVerification(): void
    {
        $build =
            (
                new DistributionPackageArchiveService(
                    $this->projectRoot
                )
            )->build();


        $result =
            (
                new DistributionPackageVerificationService(
                    $this->projectRoot
                )
            )->verify(
                $build['archive_path'],
                $build['archive_sha256']
            );


        self::assertTrue(
            $result['successful']
        );


        self::assertSame(
            'PASS',
            $result['overall_status']
        );


        self::assertTrue(
            $result['checksum_matches']
        );


        self::assertSame(
            'iqwurkspunch-0.9.0-dev',
            $result['package_root']
        );


        self::assertSame(
            '0.9.0-dev',
            $result['application_version']
        );


        self::assertSame(
            1,
            $result['manifest_schema_version']
        );


        self::assertGreaterThan(
            0,
            $result['verified_files']
        );


        self::assertGreaterThan(
            0,
            $result['verified_directories']
        );


        self::assertSame(
            0,
            $result['failure_count']
        );


        self::assertTrue(
            $result['temporary_workspace_removed']
        );


        self::assertFalse(
            $result['changes_made']
        );
    }


    public function testIncorrectExpectedChecksumFailsVerification(): void
    {
        $build =
            (
                new DistributionPackageArchiveService(
                    $this->projectRoot
                )
            )->build();


        $result =
            (
                new DistributionPackageVerificationService(
                    $this->projectRoot
                )
            )->verify(
                $build['archive_path'],
                str_repeat(
                    '0',
                    64
                )
            );


        self::assertFalse(
            $result['successful']
        );


        self::assertSame(
            'FAIL',
            $result['overall_status']
        );


        self::assertFalse(
            $result['checksum_matches']
        );


        self::assertSame(
            1,
            $result['failure_count']
        );


        self::assertSame(
            'archive_checksum_mismatch',
            $result['failures'][0]['code']
        );


        self::assertTrue(
            $result['temporary_workspace_removed']
        );
    }


    public function testRenamedArchiveFailsFilenameValidation(): void
    {
        $build =
            (
                new DistributionPackageArchiveService(
                    $this->projectRoot
                )
            )->build();


        $renamedPath =
            dirname(
                $build['archive_path']
            )
            .
            '/renamed-package.tar.gz';


        self::assertTrue(
            rename(
                $build['archive_path'],
                $renamedPath
            )
        );


        $result =
            (
                new DistributionPackageVerificationService(
                    $this->projectRoot
                )
            )->verify(
                $renamedPath
            );


        self::assertFalse(
            $result['successful']
        );


        $failureCodes =
            array_column(
                $result['failures'],
                'code'
            );


        self::assertContains(
            'package_filename_mismatch',
            $failureCodes
        );


        self::assertTrue(
            $result['temporary_workspace_removed']
        );
    }


    public function testMissingDeploymentReadmeFailsVerification(): void
    {
        $build =
            (
                new DistributionPackageArchiveService(
                    $this->projectRoot
                )
            )->build();


        $tamperRoot =
            $this->projectRoot
            .
            '/tampered-package';


        self::assertTrue(
            mkdir(
                $tamperRoot,
                0770,
                true
            )
        );


        $processes =
            new ProcessRunnerService();


        $extract =
            $processes->run(
                [
                    'tar',
                    '--extract',
                    '--gzip',
                    '--file',
                    $build['archive_path'],
                    '--directory',
                    $tamperRoot,
                    '--no-same-owner',
                    '--same-permissions'
                ],
                $this->projectRoot,
                [],
                120.0
            );


        self::assertTrue(
            (
                $extract['successful']
                ??
                false
            )
            ===
            true
        );


        $packageRoot =
            $tamperRoot
            .
            '/iqwurkspunch-0.9.0-dev';


        self::assertTrue(
            unlink(
                $packageRoot
                .
                '/deployment/README.md'
            )
        );


        self::assertTrue(
            unlink(
                $build['archive_path']
            )
        );


        $create =
            $processes->run(
                [
                    'tar',
                    '--create',
                    '--gzip',
                    '--file',
                    $build['archive_path'],
                    '--directory',
                    $tamperRoot,
                    'iqwurkspunch-0.9.0-dev'
                ],
                $this->projectRoot,
                [],
                120.0
            );


        self::assertTrue(
            (
                $create['successful']
                ??
                false
            )
            ===
            true
        );


        $result =
            (
                new DistributionPackageVerificationService(
                    $this->projectRoot
                )
            )->verify(
                $build['archive_path']
            );


        self::assertFalse(
            $result['successful']
        );


        self::assertSame(
            'FAIL',
            $result['overall_status']
        );


        $deploymentFailures =
            array_values(
                array_filter(
                    $result['failures'],
                    static fn (
                        array $failure
                    ): bool =>
                        (
                            $failure['code']
                            ??
                            ''
                        )
                        ===
                        'required_path_missing'
                        &&
                        (
                            $failure['path']
                            ??
                            ''
                        )
                        ===
                        'deployment/README.md'
                )
            );


        self::assertCount(
            1,
            $deploymentFailures
        );


        self::assertSame(
            'deployment/README.md',
            $deploymentFailures[0]['path']
        );


        self::assertTrue(
            $result['temporary_workspace_removed']
        );
    }


    public function testMissingArchiveIsRejected(): void
    {
        $this->expectException(
            \RuntimeException::class
        );


        $this->expectExceptionMessage(
            'missing or unreadable'
        );


        (
            new DistributionPackageVerificationService(
                $this->projectRoot
            )
        )->verify(
            'storage/exports/packages/missing.tar.gz'
        );
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
