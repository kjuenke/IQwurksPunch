<?php
declare(strict_types=1);

use App\Services\DistributionPackageManifestService;
use PHPUnit\Framework\TestCase;

final class DistributionPackageManifestServiceTest extends TestCase
{
    private string $projectRoot;


    protected function setUp(): void
    {
        parent::setUp();


        $this->projectRoot =
            sys_get_temp_dir()
            .
            '/iqwurks-package-manifest-'
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


    public function testManifestIncludesApprovedSourceAndRuntimeDirectories(): void
    {
        $manifest =
            (
                new DistributionPackageManifestService(
                    $this->projectRoot
                )
            )->manifest();


        $paths =
            array_column(
                $manifest['entries'],
                'path'
            );


        self::assertContains(
            'app/Example.php',
            $paths
        );


        self::assertContains(
            'config/mail.example.php',
            $paths
        );


        self::assertContains(
            'database/sqlite',
            $paths
        );


        self::assertContains(
            'storage/logs',
            $paths
        );


        self::assertSame(
            'PASS',
            $manifest['overall_status']
        );


        self::assertTrue(
            $manifest['can_build']
        );


        self::assertFalse(
            $manifest['changes_made']
        );


        self::assertMatchesRegularExpression(
            '/^[a-f0-9]{64}$/',
            $manifest['manifest_sha256']
        );
    }


    public function testSensitiveAndRuntimeFilesAreExcluded(): void
    {
        $manifest =
            (
                new DistributionPackageManifestService(
                    $this->projectRoot
                )
            )->manifest();


        $paths =
            array_column(
                $manifest['entries'],
                'path'
            );


        self::assertNotContains(
            'config/mail.php',
            $paths
        );


        self::assertNotContains(
            'database/sqlite/iqwurks.sqlite',
            $paths
        );


        self::assertNotContains(
            'storage/logs/application.log',
            $paths
        );


        self::assertNotContains(
            'firewall-rules.txt',
            $paths
        );


        self::assertNotContains(
            'installed-packages.txt',
            $paths
        );


        self::assertContains(
            'config/mail.php',
            $manifest['excluded_patterns']
        );


        self::assertContains(
            'database/sqlite/*.sqlite',
            $manifest['excluded_patterns']
        );


        self::assertContains(
            'storage/logs/*',
            $manifest['excluded_patterns']
        );


        self::assertContains(
            'firewall-rules.txt',
            $manifest['excluded_patterns']
        );


        self::assertContains(
            'installed-packages.txt',
            $manifest['excluded_patterns']
        );


        $excludedPaths =
            array_column(
                $manifest['excluded_entries'],
                'path'
            );


        self::assertContains(
            'config/mail.php',
            $excludedPaths
        );
    }


    public function testRuntimeDirectoryEntriesAreGenerated(): void
    {
        $manifest =
            (
                new DistributionPackageManifestService(
                    $this->projectRoot
                )
            )->manifest();


        $runtimeEntries =
            array_values(
                array_filter(
                    $manifest['entries'],
                    static fn (
                        array $entry
                    ): bool =>
                        $entry['path']
                        ===
                        'storage/logs'
                )
            );


        self::assertCount(
            1,
            $runtimeEntries
        );


        self::assertSame(
            'directory',
            $runtimeEntries[0]['type']
        );


        self::assertTrue(
            $runtimeEntries[0]['generated']
        );


        self::assertNull(
            $runtimeEntries[0]['source_path']
        );


        self::assertSame(
            0,
            $runtimeEntries[0]['size_bytes']
        );
    }


    public function testManifestDigestIsDeterministic(): void
    {
        $service =
            new DistributionPackageManifestService(
                $this->projectRoot
            );


        $first =
            $service->manifest();


        $second =
            $service->manifest();


        self::assertSame(
            $first['manifest_sha256'],
            $second['manifest_sha256']
        );


        self::assertSame(
            $first['entries'],
            $second['entries']
        );


        self::assertSame(
            $first['summary'],
            $second['summary']
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
