<?php
declare(strict_types=1);

use App\Console\Commands\InstallCheckCommand;
use App\Services\InstallationPreflightService;
use PHPUnit\Framework\TestCase;

final class InstallCheckCommandTest extends TestCase
{
    private string $projectRoot;


    protected function setUp(): void
    {
        parent::setUp();


        $this->projectRoot =
            sys_get_temp_dir()
            .
            '/iqwurks-install-check-'
            .
            bin2hex(
                random_bytes(
                    8
                )
            );


        $this->createProjectTree();
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
            new InstallCheckCommand(
                $this->service()
            );


        self::assertSame(
            'install:check',
            $command->name()
        );


        self::assertSame(
            'Check whether the current machine is ready to install IQwurksPunch.',
            $command->description()
        );
    }


    public function testHealthyPreflightReturnsSuccess(): void
    {
        $command =
            new InstallCheckCommand(
                $this->service()
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


        self::assertStringContainsString(
            'Installation Preflight',
            $output
        );


        self::assertStringContainsString(
            '[PASS] Operating system',
            $output
        );


        self::assertStringContainsString(
            '[PASS] PHP version',
            $output
        );


        self::assertStringContainsString(
            '[PASS] PHP extensions',
            $output
        );


        self::assertStringContainsString(
            '[PASS] System commands',
            $output
        );


        self::assertStringContainsString(
            'Overall status: PASS',
            $output
        );


        self::assertStringContainsString(
            'Passed: 8',
            $output
        );


        self::assertStringContainsString(
            'Warnings: 0',
            $output
        );


        self::assertStringContainsString(
            'Failures: 0',
            $output
        );


        self::assertStringContainsString(
            'Total checks: 8',
            $output
        );
    }


    public function testFailedPreflightReturnsFailure(): void
    {
        $command =
            new InstallCheckCommand(
                $this->service(
                    [
                        'php_version' =>
                            '8.4.9'
                    ]
                )
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


        self::assertStringContainsString(
            '[FAIL] PHP version',
            $output
        );


        self::assertStringContainsString(
            'PHP 8.5 or newer is required.',
            $output
        );


        self::assertStringContainsString(
            'Overall status: FAIL',
            $output
        );


        self::assertStringContainsString(
            'Failures: 1',
            $output
        );
    }


    /**
     * @param array<string,mixed> $overrides
     */
    private function service(
        array $overrides = []
    ): InstallationPreflightService
    {
        return
            new InstallationPreflightService(
                $this->projectRoot,
                [
                    'json',
                    'pdo_sqlite'
                ],
                [
                    'PHP CLI' => [
                        'php'
                    ],

                    'SQLite CLI' => [
                        'sqlite3'
                    ]
                ],
                array_replace(
                    [
                        'php_version' =>
                            '8.5.4',

                        'loaded_extensions' => [
                            'json',
                            'pdo_sqlite'
                        ],

                        'command_paths' => [
                            'php' =>
                                '/usr/bin/php',

                            'sqlite3' =>
                                '/usr/bin/sqlite3'
                        ],

                        'free_bytes' =>
                            20
                            *
                            1024
                            *
                            1024
                            *
                            1024,

                        'total_bytes' =>
                            100
                            *
                            1024
                            *
                            1024
                            *
                            1024,

                        'os_release' => [
                            'ID' =>
                                'ubuntu',

                            'VERSION_ID' =>
                                '26.04',

                            'PRETTY_NAME' =>
                                'Ubuntu 26.04 LTS'
                        ]
                    ],
                    $overrides
                )
            );
    }


    private function createProjectTree(): void
    {
        $directories = [
            'app',
            'bootstrap',
            'config',
            'database/migrations',
            'database/sqlite',
            'public',
            'storage/backups',
            'storage/cache',
            'storage/exports',
            'storage/logs',
            'storage/sessions',
            'vendor'
        ];


        foreach ($directories as $directory) {

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


        $files = [
            'VERSION',
            'composer.json',
            'composer.lock',
            'iqwurks',
            'migrate.php',
            'bootstrap/app.php',
            'public/index.php',
            'vendor/autoload.php'
        ];


        foreach ($files as $file) {

            self::assertNotFalse(
                file_put_contents(
                    $this->projectRoot
                    .
                    '/'
                    .
                    $file,
                    'test'
                )
            );
        }
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
                '/'
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
