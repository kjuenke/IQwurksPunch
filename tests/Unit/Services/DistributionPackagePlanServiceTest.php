<?php
declare(strict_types=1);

use App\Services\DistributionPackagePlanService;
use PHPUnit\Framework\TestCase;

final class DistributionPackagePlanServiceTest extends TestCase
{
    private string $projectRoot;


    protected function setUp(): void
    {
        parent::setUp();


        $this->projectRoot =
            sys_get_temp_dir()
            .
            '/iqwurks-package-plan-'
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
                'deployment',
                'docs',
                'plugins',
                'public',
                'routes',
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
                'iqwurks',
                'LICENSE',
                'migrate.php',
                'phpunit.xml',
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
                        : "test\n"
                )
            );
        }
    }


    protected function tearDown(): void
    {
        $this->removeDirectory(
            $this->projectRoot
        );


        parent::tearDown();
    }


    public function testCompleteSourceTreeCanBePackaged(): void
    {
        $plan =
            (
                new DistributionPackagePlanService(
                    $this->projectRoot
                )
            )->plan();


        self::assertSame(
            '0.9.0-dev',
            $plan['application_version']
        );


        self::assertSame(
            'iqwurkspunch-0.9.0-dev.tar.gz',
            $plan['package_file_name']
        );


        self::assertSame(
            'PASS',
            $plan['overall_status']
        );


        self::assertFalse(
            $plan['blocked']
        );


        self::assertTrue(
            $plan['can_build']
        );


        self::assertSame(
            0,
            $plan['summary']['failures']
        );


        self::assertContains(
            'deployment',
            $plan['include_paths']
        );


        self::assertContains(
            'deployment',
            $plan['required_directories']
        );


        self::assertFalse(
            $plan['changes_made']
        );
    }


    public function testMissingRequiredFileBlocksPackaging(): void
    {
        self::assertTrue(
            unlink(
                $this->projectRoot
                .
                '/composer.lock'
            )
        );


        $plan =
            (
                new DistributionPackagePlanService(
                    $this->projectRoot
                )
            )->plan();


        self::assertSame(
            'FAIL',
            $plan['overall_status']
        );


        self::assertTrue(
            $plan['blocked']
        );


        self::assertFalse(
            $plan['can_build']
        );


        self::assertSame(
            1,
            $plan['summary']['failures']
        );


        $composerLockCheck =
            array_values(
                array_filter(
                    $plan['required_path_checks'],
                    static fn (
                        array $check
                    ): bool =>
                        $check['path']
                        ===
                        'composer.lock'
                )
            );


        self::assertCount(
            1,
            $composerLockCheck
        );


        self::assertSame(
            'FAIL',
            $composerLockCheck[0]['status']
        );
    }


    public function testMissingDeploymentDirectoryBlocksPackaging(): void
    {
        self::assertTrue(
            rmdir(
                $this->projectRoot
                .
                '/deployment'
            )
        );


        $plan =
            (
                new DistributionPackagePlanService(
                    $this->projectRoot
                )
            )->plan();


        self::assertSame(
            'FAIL',
            $plan['overall_status']
        );


        self::assertTrue(
            $plan['blocked']
        );


        self::assertFalse(
            $plan['can_build']
        );


        self::assertSame(
            1,
            $plan['summary']['failures']
        );


        $deploymentCheck =
            array_values(
                array_filter(
                    $plan['required_path_checks'],
                    static fn (
                        array $check
                    ): bool =>
                        $check['path']
                        ===
                        'deployment'
                )
            );


        self::assertCount(
            1,
            $deploymentCheck
        );


        self::assertSame(
            'directory',
            $deploymentCheck[0]['type']
        );


        self::assertFalse(
            $deploymentCheck[0]['exists']
        );


        self::assertSame(
            'FAIL',
            $deploymentCheck[0]['status']
        );
    }


    public function testPlanIdentifiesSensitiveAndRuntimeExclusions(): void
    {
        $plan =
            (
                new DistributionPackagePlanService(
                    $this->projectRoot
                )
            )->plan();


        self::assertContains(
            'config/mail.php',
            $plan['excluded_paths']
        );


        self::assertContains(
            'database/sqlite/*.sqlite',
            $plan['excluded_paths']
        );


        self::assertContains(
            'storage/backups/*',
            $plan['excluded_paths']
        );


        self::assertContains(
            'firewall-rules.txt',
            $plan['excluded_paths']
        );


        self::assertContains(
            'installed-packages.txt',
            $plan['excluded_paths']
        );


        self::assertContains(
            'database/sqlite',
            $plan['runtime_directories']
        );


        self::assertContains(
            'storage/logs',
            $plan['runtime_directories']
        );
    }


    public function testInvalidVersionIsRejected(): void
    {
        $this->expectException(
            \RuntimeException::class
        );


        $this->expectExceptionMessage(
            'distribution package version is invalid'
        );


        (
            new DistributionPackagePlanService(
                $this->projectRoot
            )
        )->plan(
            'version nine'
        );
    }


    private function removeDirectory(
        string $path
    ): void
    {
        if (!is_dir($path)) {
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


            if (is_dir($itemPath)) {

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
