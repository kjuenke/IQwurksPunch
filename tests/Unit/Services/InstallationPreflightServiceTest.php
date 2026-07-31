<?php
declare(strict_types=1);

use App\Services\InstallationPreflightService;
use PHPUnit\Framework\TestCase;

final class InstallationPreflightServiceTest extends TestCase
{
    private string $projectRoot;


    protected function setUp(): void
    {
        parent::setUp();


        $this->projectRoot =
            sys_get_temp_dir()
            .
            '/iqwurks-preflight-'
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


    public function testHealthyEnvironmentPassesEveryCheck(): void
    {
        $service =
            $this->service();


        $checks =
            $service->check();


        self::assertCount(
            8,
            $checks
        );


        foreach ($checks as $check) {
            self::assertSame(
                InstallationPreflightService::PASS,
                $check['status'],
                (string)$check['name']
            );
        }
    }


    public function testUnsupportedPhpVersionFails(): void
    {
        $service =
            $this->service(
                [
                    'php_version' =>
                        '8.4.9'
                ]
            );


        $check =
            $this->namedCheck(
                $service->check(),
                'PHP version'
            );


        self::assertSame(
            InstallationPreflightService::FAIL,
            $check['status']
        );


        self::assertStringContainsString(
            'PHP 8.5',
            $check['message']
        );
    }


    public function testMissingExtensionsAreReported(): void
    {
        $service =
            new InstallationPreflightService(
                $this->projectRoot,
                [
                    'json',
                    'pdo_sqlite',
                    'mbstring'
                ],
                $this->requiredCommands(),
                $this->environment(
                    [
                        'loaded_extensions' => [
                            'json',
                            'pdo_sqlite'
                        ]
                    ]
                )
            );


        $check =
            $this->namedCheck(
                $service->check(),
                'PHP extensions'
            );


        self::assertSame(
            InstallationPreflightService::FAIL,
            $check['status']
        );


        self::assertStringContainsString(
            'mbstring',
            implode(
                ' ',
                $check['details']
            )
        );
    }


    public function testMissingSystemCommandIsReported(): void
    {
        $service =
            new InstallationPreflightService(
                $this->projectRoot,
                $this->requiredExtensions(),
                [
                    'PHP CLI' => [
                        'php'
                    ],

                    'SQLite CLI' => [
                        'sqlite3'
                    ],

                    'Nginx' => [
                        'nginx'
                    ]
                ],
                $this->environment(
                    [
                        'command_paths' => [
                            'php' =>
                                '/usr/bin/php',

                            'sqlite3' =>
                                '/usr/bin/sqlite3',

                            'nginx' =>
                                null
                        ]
                    ]
                )
            );


        $check =
            $this->namedCheck(
                $service->check(),
                'System commands'
            );


        self::assertSame(
            InstallationPreflightService::FAIL,
            $check['status']
        );


        self::assertStringContainsString(
            'Nginx',
            implode(
                ' ',
                $check['details']
            )
        );
    }


    public function testIncompleteApplicationSourceFails(): void
    {
        unlink(
            $this->projectRoot
            .
            '/public/index.php'
        );


        $service =
            $this->service();


        $check =
            $this->namedCheck(
                $service->check(),
                'Application source'
            );


        self::assertSame(
            InstallationPreflightService::FAIL,
            $check['status']
        );


        self::assertStringContainsString(
            'public/index.php',
            implode(
                ' ',
                $check['details']
            )
        );
    }


    public function testLimitedDiskCapacityProducesWarning(): void
    {
        $service =
            $this->service(
                [
                    'free_bytes' =>
                        3
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
                        1024
                ]
            );


        $check =
            $this->namedCheck(
                $service->check(),
                'Disk capacity'
            );


        self::assertSame(
            InstallationPreflightService::WARN,
            $check['status']
        );
    }


    public function testCriticallyLowDiskCapacityFails(): void
    {
        $service =
            $this->service(
                [
                    'free_bytes' =>
                        500
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
                        1024
                ]
            );


        $check =
            $this->namedCheck(
                $service->check(),
                'Disk capacity'
            );


        self::assertSame(
            InstallationPreflightService::FAIL,
            $check['status']
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
                $this->requiredExtensions(),
                $this->requiredCommands(),
                $this->environment(
                    $overrides
                )
            );
    }


    /**
     * @return array<int,string>
     */
    private function requiredExtensions(): array
    {
        return [
            'json',
            'pdo_sqlite'
        ];
    }


    /**
     * @return array<string,array<int,string>>
     */
    private function requiredCommands(): array
    {
        return [
            'PHP CLI' => [
                'php'
            ],

            'SQLite CLI' => [
                'sqlite3'
            ]
        ];
    }


    /**
     * @param array<string,mixed> $overrides
     *
     * @return array<string,mixed>
     */
    private function environment(
        array $overrides = []
    ): array
    {
        return
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
            );
    }


    /**
     * @param array<int,array<string,mixed>> $checks
     *
     * @return array<string,mixed>
     */
    private function namedCheck(
        array $checks,
        string $name
    ): array
    {
        foreach ($checks as $check) {

            if (
                (
                    $check['name']
                    ??
                    null
                )
                ===
                $name
            ) {
                return $check;
            }
        }


        self::fail(
            'The expected preflight check was not found: '
            .
            $name
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
