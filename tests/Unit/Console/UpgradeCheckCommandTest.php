<?php
declare(strict_types=1);

use App\Console\Commands\UpgradeCheckCommand;
use App\Services\UpgradeReadinessService;
use PHPUnit\Framework\TestCase;

final class UpgradeCheckCommandTest extends TestCase
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
            '/iqwurks-upgrade-check-'
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


    public function testCommandIdentity(): void
    {
        $command =
            new UpgradeCheckCommand(
                $this->service()
            );


        self::assertSame(
            'upgrade:check',
            $command->name()
        );


        self::assertSame(
            'Check whether the current IQwurksPunch installation is ready to upgrade.',
            $command->description()
        );
    }


    public function testInactiveMaintenanceModeReturnsWarningSuccess(): void
    {
        $command =
            new UpgradeCheckCommand(
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
            'Upgrade Readiness',
            $output
        );


        self::assertStringContainsString(
            '[WARN] Maintenance mode',
            $output
        );


        self::assertStringContainsString(
            'Overall status: WARN',
            $output
        );


        self::assertStringContainsString(
            'Passed: 7',
            $output
        );


        self::assertStringContainsString(
            'Warnings: 1',
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


    public function testFailedReadinessReturnsFailure(): void
    {
        $this->activateMaintenance();


        file_put_contents(
            $this->projectRoot
            .
            '/VERSION',
            'invalid-version'
        );


        $command =
            new UpgradeCheckCommand(
                $this->service()
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
            '[FAIL] Application version',
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


    private function service(): UpgradeReadinessService
    {
        return
            new UpgradeReadinessService(
                $this->projectRoot,
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
                ]
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
                        'Upgrade command test',

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
