<?php
declare(strict_types=1);

use App\Services\DistributionPackageStagingService;
use PHPUnit\Framework\TestCase;

final class DistributionPackageStagingServiceTest extends TestCase
{
    private string $projectRoot;


    protected function setUp(): void
    {
        parent::setUp();


        $this->projectRoot =
            sys_get_temp_dir()
            .
            '/iqwurks-package-staging-'
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
                'releases',
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
                'README.md',
                'releases/0.9.0-dev.md',
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


        self::assertNotFalse(
            file_put_contents(
                $this->projectRoot
                .
                '/storage/logs/application.log',
                'private log'
            )
        );


        self::assertNotFalse(
            file_put_contents(
                $this->projectRoot
                .
                '/firewall-rules.txt',
                'local firewall'
            )
        );


        self::assertNotFalse(
            file_put_contents(
                $this->projectRoot
                .
                '/installed-packages.txt',
                'local packages'
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


    public function testApprovedFilesAndRuntimeDirectoriesAreStaged(): void
    {
        $destination =
            $this->projectRoot
            .
            '/package-output';


        $result =
            (
                new DistributionPackageStagingService(
                    $this->projectRoot
                )
            )->stage(
                null,
                $destination
            );


        self::assertTrue(
            $result['successful']
        );


        self::assertTrue(
            $result['changes_made']
        );


        self::assertFileExists(
            $destination
            .
            '/app/Example.php'
        );


        self::assertFileExists(
            $destination
            .
            '/config/mail.example.php'
        );


        self::assertDirectoryExists(
            $destination
            .
            '/database/sqlite'
        );


        self::assertDirectoryExists(
            $destination
            .
            '/storage/logs'
        );


        self::assertFileExists(
            $destination
            .
            '/PACKAGE-MANIFEST.json'
        );


        self::assertSame(
            0,
            $result['summary']['verification_failures']
        );


        self::assertSame(
            $result['summary']['source_files'],
            $result['summary']['verified_files']
        );
    }


    public function testSensitiveAndRuntimeFilesAreNotStaged(): void
    {
        $destination =
            $this->projectRoot
            .
            '/package-output';


        (
            new DistributionPackageStagingService(
                $this->projectRoot
            )
        )->stage(
            null,
            $destination
        );


        self::assertFileDoesNotExist(
            $destination
            .
            '/config/mail.php'
        );


        self::assertFileDoesNotExist(
            $destination
            .
            '/database/sqlite/iqwurks.sqlite'
        );


        self::assertFileDoesNotExist(
            $destination
            .
            '/storage/logs/application.log'
        );


        self::assertFileDoesNotExist(
            $destination
            .
            '/firewall-rules.txt'
        );


        self::assertFileDoesNotExist(
            $destination
            .
            '/installed-packages.txt'
        );
    }


    public function testPortableManifestDoesNotExposeLocalSourcePaths(): void
    {
        $destination =
            $this->projectRoot
            .
            '/package-output';


        (
            new DistributionPackageStagingService(
                $this->projectRoot
            )
        )->stage(
            null,
            $destination
        );


        $manifestContents =
            file_get_contents(
                $destination
                .
                '/PACKAGE-MANIFEST.json'
            );


        self::assertNotFalse(
            $manifestContents
        );


        self::assertStringNotContainsString(
            $this->projectRoot,
            $manifestContents
        );


        self::assertStringNotContainsString(
            'source_path',
            $manifestContents
        );


        $manifest =
            json_decode(
                $manifestContents,
                true,
                512,
                JSON_THROW_ON_ERROR
            );


        self::assertSame(
            1,
            $manifest['schema_version']
        );


        self::assertSame(
            '0.9.0-dev',
            $manifest['application_version']
        );


        self::assertSame(
            'iqwurkspunch-0.9.0-dev',
            $manifest['package_base_name']
        );
    }


    public function testExistingDestinationIsRejectedWithoutDeletingIt(): void
    {
        $destination =
            $this->projectRoot
            .
            '/package-output';


        self::assertTrue(
            mkdir(
                $destination,
                0770,
                true
            )
        );


        $sentinel =
            $destination
            .
            '/keep-me.txt';


        self::assertNotFalse(
            file_put_contents(
                $sentinel,
                'existing data'
            )
        );


        try {

            (
                new DistributionPackageStagingService(
                    $this->projectRoot
                )
            )->stage(
                null,
                $destination
            );


            self::fail(
                'An existing staging directory should have been rejected.'
            );

        } catch (\RuntimeException $exception) {

            self::assertStringContainsString(
                'already exists',
                $exception->getMessage()
            );
        }


        self::assertFileExists(
            $sentinel
        );


        self::assertSame(
            'existing data',
            file_get_contents(
                $sentinel
            )
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
