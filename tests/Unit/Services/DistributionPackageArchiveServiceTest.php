<?php
declare(strict_types=1);

use App\Services\DistributionPackageArchiveService;
use PHPUnit\Framework\TestCase;

final class DistributionPackageArchiveServiceTest extends TestCase
{
    private string $projectRoot;


    protected function setUp(): void
    {
        parent::setUp();


        $this->projectRoot =
            sys_get_temp_dir()
            .
            '/iqwurks-package-archive-'
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


    public function testVerifiedArchiveIsCreatedAndStagingIsRemoved(): void
    {
        $result =
            (
                new DistributionPackageArchiveService(
                    $this->projectRoot
                )
            )->build();


        self::assertTrue(
            $result['successful']
        );


        self::assertTrue(
            $result['changes_made']
        );


        self::assertTrue(
            $result['staging_removed']
        );


        self::assertFileExists(
            $result['archive_path']
        );


        self::assertFileDoesNotExist(
            $this->projectRoot
            .
            '/storage/exports/packages/iqwurkspunch-0.9.0-dev.tar'
        );


        self::assertDirectoryDoesNotExist(
            $result['staging_directory']
        );


        self::assertGreaterThan(
            0,
            $result['archive_size_bytes']
        );


        self::assertMatchesRegularExpression(
            '/^[a-f0-9]{64}$/',
            $result['archive_sha256']
        );


        self::assertTrue(
            $result['verification']['successful']
        );


        self::assertSame(
            0,
            $result['summary']['verification_failures']
        );
    }


    public function testArchiveContainsVersionedRootAndApprovedFiles(): void
    {
        $result =
            (
                new DistributionPackageArchiveService(
                    $this->projectRoot
                )
            )->build();


        $archiveRoot =
            'phar://'
            .
            $result['archive_path']
            .
            '/iqwurkspunch-0.9.0-dev';


        self::assertDirectoryExists(
            $archiveRoot
        );


        self::assertFileExists(
            $archiveRoot
            .
            '/app/Example.php'
        );


        self::assertFileExists(
            $archiveRoot
            .
            '/config/mail.example.php'
        );


        self::assertFileExists(
            $archiveRoot
            .
            '/PACKAGE-MANIFEST.json'
        );


        self::assertDirectoryExists(
            $archiveRoot
            .
            '/database/sqlite'
        );


        self::assertDirectoryExists(
            $archiveRoot
            .
            '/storage/logs'
        );


        $manifest =
            json_decode(
                (string)file_get_contents(
                    $archiveRoot
                    .
                    '/PACKAGE-MANIFEST.json'
                ),
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
    }


    public function testSensitiveAndRuntimeFilesAreNotArchived(): void
    {
        $result =
            (
                new DistributionPackageArchiveService(
                    $this->projectRoot
                )
            )->build();


        $archiveRoot =
            'phar://'
            .
            $result['archive_path']
            .
            '/iqwurkspunch-0.9.0-dev';


        self::assertFileDoesNotExist(
            $archiveRoot
            .
            '/config/mail.php'
        );


        self::assertFileDoesNotExist(
            $archiveRoot
            .
            '/database/sqlite/iqwurks.sqlite'
        );


        self::assertFileDoesNotExist(
            $archiveRoot
            .
            '/storage/logs/application.log'
        );


        self::assertFileDoesNotExist(
            $archiveRoot
            .
            '/firewall-rules.txt'
        );


        self::assertFileDoesNotExist(
            $archiveRoot
            .
            '/installed-packages.txt'
        );
    }


    public function testExistingArchiveIsRejectedWithoutOverwritingIt(): void
    {
        $artifactDirectory =
            $this->projectRoot
            .
            '/storage/exports/packages';


        self::assertTrue(
            mkdir(
                $artifactDirectory,
                0770,
                true
            )
        );


        $archivePath =
            $artifactDirectory
            .
            '/iqwurkspunch-0.9.0-dev.tar.gz';


        self::assertNotFalse(
            file_put_contents(
                $archivePath,
                'existing archive'
            )
        );


        try {

            (
                new DistributionPackageArchiveService(
                    $this->projectRoot
                )
            )->build();


            self::fail(
                'An existing archive should have been rejected.'
            );

        } catch (\RuntimeException $exception) {

            self::assertStringContainsString(
                'already exists',
                $exception->getMessage()
            );
        }


        self::assertSame(
            'existing archive',
            file_get_contents(
                $archivePath
            )
        );


        self::assertDirectoryDoesNotExist(
            $this->projectRoot
            .
            '/storage/cache/packages/iqwurkspunch-0.9.0-dev'
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
