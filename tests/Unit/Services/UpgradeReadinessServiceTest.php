<?php
declare(strict_types=1);

use App\Services\UpgradeReadinessService;
use PHPUnit\Framework\TestCase;

final class UpgradeReadinessServiceTest extends TestCase
{
    private string $projectRoot;

    private string $databasePath;

    private string $backupPath;

    private string $maintenancePath;

    private int $now;


    protected function setUp(): void
    {
        parent::setUp();


        $this->now =
            time();


        $this->projectRoot =
            sys_get_temp_dir()
            .
            '/iqwurks-upgrade-readiness-'
            .
            bin2hex(
                random_bytes(
                    8
                )
            );


        $this->databasePath =
            $this->projectRoot
            .
            '/database/sqlite/iqwurks.sqlite';


        $this->backupPath =
            $this->projectRoot
            .
            '/storage/backups/iqwurks-test.sqlite';


        $this->maintenancePath =
            $this->projectRoot
            .
            '/storage/cache/maintenance.json';


        $this->createProjectTree();
    }


    protected function tearDown(): void
    {
        $this->removeDirectory(
            $this->projectRoot
        );


        parent::tearDown();
    }


    public function testReadyUpgradePassesEveryCheck(): void
    {
        $this->activateMaintenance();


        $checks =
            $this->service()
                ->check();


        self::assertCount(
            8,
            $checks
        );


        foreach ($checks as $check) {

            self::assertSame(
                UpgradeReadinessService::PASS,
                $check['status'],
                (string)$check['name']
            );
        }
    }


    public function testInvalidApplicationVersionFails(): void
    {
        file_put_contents(
            $this->projectRoot
            .
            '/VERSION',
            'development'
        );


        $check =
            $this->namedCheck(
                $this->service()
                    ->check(),
                'Application version'
            );


        self::assertSame(
            UpgradeReadinessService::FAIL,
            $check['status']
        );
    }


    public function testPendingMigrationProducesWarning(): void
    {
        file_put_contents(
            $this->projectRoot
            .
            '/database/migrations/003_pending.php',
            '<?php return new stdClass();'
        );


        $check =
            $this->namedCheck(
                $this->service()
                    ->check(),
                'Migration history'
            );


        self::assertSame(
            UpgradeReadinessService::WARN,
            $check['status']
        );


        self::assertStringContainsString(
            '003_pending.php',
            implode(
                ' ',
                $check['details']
            )
        );
    }


    public function testUnknownRecordedMigrationFails(): void
    {
        $database =
            new PDO(
                'sqlite:'
                .
                $this->databasePath
            );


        $database->exec(
            "
            INSERT INTO migrations
            (
                migration,
                batch
            )

            VALUES
            (
                '999_unknown.php',
                99
            )
            "
        );


        $database =
            null;


        $check =
            $this->namedCheck(
                $this->service()
                    ->check(),
                'Migration history'
            );


        self::assertSame(
            UpgradeReadinessService::FAIL,
            $check['status']
        );


        self::assertStringContainsString(
            '999_unknown.php',
            implode(
                ' ',
                $check['details']
            )
        );
    }


    public function testDamagedDatabaseFailsHealthCheck(): void
    {
        file_put_contents(
            $this->databasePath,
            'not a sqlite database'
        );


        $check =
            $this->namedCheck(
                $this->service()
                    ->check(),
                'Database health'
            );


        self::assertSame(
            UpgradeReadinessService::FAIL,
            $check['status']
        );
    }


    public function testMissingBackupFails(): void
    {
        unlink(
            $this->backupPath
        );


        $check =
            $this->namedCheck(
                $this->service()
                    ->check(),
                'Verified backup'
            );


        self::assertSame(
            UpgradeReadinessService::FAIL,
            $check['status']
        );
    }


    public function testInactiveMaintenanceModeProducesWarning(): void
    {
        $check =
            $this->namedCheck(
                $this->service()
                    ->check(),
                'Maintenance mode'
            );


        self::assertSame(
            UpgradeReadinessService::WARN,
            $check['status']
        );


        self::assertStringContainsString(
            'inactive',
            strtolower(
                $check['message']
            )
        );
    }


    public function testCriticallyLowDiskCapacityFails(): void
    {
        $check =
            $this->namedCheck(
                $this->service(
                    [
                        'free_bytes' =>
                            500
                            *
                            1024
                            *
                            1024
                    ]
                )->check(),
                'Disk capacity'
            );


        self::assertSame(
            UpgradeReadinessService::FAIL,
            $check['status']
        );
    }


    /**
     * @param array<string,mixed> $overrides
     */
    private function service(
        array $overrides = []
    ): UpgradeReadinessService
    {
        return
            new UpgradeReadinessService(
                $this->projectRoot,
                array_replace(
                    [
                        'now' =>
                            $this->now,

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
                            1024
                    ],
                    $overrides
                )
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
            'The expected upgrade-readiness check was not found: '
            .
            $name
        );
    }


    private function createProjectTree(): void
    {
        $directories = [
            'config',
            'database/migrations',
            'database/sqlite',
            'storage/backups',
            'storage/cache',
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


        file_put_contents(
            $this->projectRoot
            .
            '/VERSION',
            "0.8.0\n"
        );


        file_put_contents(
            $this->projectRoot
            .
            '/composer.json',
            json_encode(
                [
                    'name' =>
                        'iqwurks/iqwurkspunch'
                ],
                JSON_THROW_ON_ERROR
            )
        );


        file_put_contents(
            $this->projectRoot
            .
            '/composer.lock',
            json_encode(
                [
                    'packages' =>
                        []
                ],
                JSON_THROW_ON_ERROR
            )
        );


        file_put_contents(
            $this->projectRoot
            .
            '/migrate.php',
            '<?php'
        );


        file_put_contents(
            $this->projectRoot
            .
            '/vendor/autoload.php',
            '<?php'
        );


        $migrations = [
            '001_create_core_tables.php',
            '002_add_fields.php'
        ];


        foreach ($migrations as $migration) {

            file_put_contents(
                $this->projectRoot
                .
                '/database/migrations/'
                .
                $migration,
                '<?php return new stdClass();'
            );
        }


        $database =
            new PDO(
                'sqlite:'
                .
                $this->databasePath
            );


        $database->setAttribute(
            PDO::ATTR_ERRMODE,
            PDO::ERRMODE_EXCEPTION
        );


        $database->exec(
            "
            PRAGMA foreign_keys = ON;

            CREATE TABLE migrations
            (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                migration TEXT NOT NULL UNIQUE,
                executed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                batch INTEGER DEFAULT 1
            );
            "
        );


        $statement =
            $database->prepare(
                "
                INSERT INTO migrations
                (
                    migration,
                    batch
                )

                VALUES
                (
                    :migration,
                    1
                )
                "
            );


        foreach ($migrations as $migration) {

            $statement->execute(
                [
                    'migration' =>
                        $migration
                ]
            );
        }


        $database =
            null;


        self::assertTrue(
            copy(
                $this->databasePath,
                $this->backupPath
            )
        );


        touch(
            $this->backupPath,
            $this->now
            -
            60
            *
            60
        );


        file_put_contents(
            $this->projectRoot
            .
            '/config/database.php',
            '<?php return '
            .
            var_export(
                [
                    'driver' =>
                        'sqlite',

                    'database' =>
                        $this->databasePath
                ],
                true
            )
            .
            ';'
        );


        file_put_contents(
            $this->projectRoot
            .
            '/config/backup.php',
            '<?php return '
            .
            var_export(
                [
                    'directory' =>
                        dirname(
                            $this->backupPath
                        )
                ],
                true
            )
            .
            ';'
        );


        file_put_contents(
            $this->projectRoot
            .
            '/config/maintenance.php',
            '<?php return '
            .
            var_export(
                [
                    'file' =>
                        $this->maintenancePath
                ],
                true
            )
            .
            ';'
        );
    }


    private function activateMaintenance(): void
    {
        file_put_contents(
            $this->maintenancePath,
            json_encode(
                [
                    'active' =>
                        true,

                    'reason' =>
                        'Upgrade readiness test',

                    'started_at' =>
                        date(
                            DATE_ATOM,
                            $this->now
                        ),

                    'started_at_display' =>
                        date(
                            'Y-m-d H:i:s T',
                            $this->now
                        )
                ],
                JSON_PRETTY_PRINT
                |
                JSON_THROW_ON_ERROR
            )
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
