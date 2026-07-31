<?php
declare(strict_types=1);

use App\Console\Commands\UpgradePlanCommand;
use App\Services\UpgradePlanService;
use App\Services\UpgradeReadinessService;
use PHPUnit\Framework\TestCase;

final class UpgradePlanCommandTest extends TestCase
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
            '/iqwurks-upgrade-plan-command-'
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
            new UpgradePlanCommand(
                $this->service()
            );


        self::assertSame(
            'upgrade:plan',
            $command->name()
        );


        self::assertSame(
            'Display the proposed IQwurksPunch upgrade workflow without making changes.',
            $command->description()
        );
    }


    public function testWarningPlanReturnsSuccessAndDisplaysWorkflow(): void
    {
        $command =
            new UpgradePlanCommand(
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
            'Upgrade Plan',
            $output
        );


        self::assertStringContainsString(
            'Overall status: WARN',
            $output
        );


        self::assertStringContainsString(
            'Can begin: yes',
            $output
        );


        self::assertStringContainsString(
            'Maintenance active: no',
            $output
        );


        self::assertStringContainsString(
            'Pending migrations: 0',
            $output
        );


        self::assertStringContainsString(
            '1. [READY] Pre-upgrade validation',
            $output
        );


        self::assertStringContainsString(
            '2. [REQUIRED] Maintenance protection',
            $output
        );


        self::assertStringContainsString(
            'Command: ./iqwurks backup:create',
            $output
        );


        self::assertStringContainsString(
            'Command: composer install --no-interaction --prefer-dist --optimize-autoloader',
            $output
        );


        self::assertStringContainsString(
            'Command: php migrate.php',
            $output
        );


        self::assertStringContainsString(
            'Command: ./iqwurks maintenance:off',
            $output
        );


        self::assertStringContainsString(
            'Plan stages: 9',
            $output
        );


        self::assertStringContainsString(
            'This command made no changes.',
            $output
        );
    }


    public function testBlockedPlanReturnsFailure(): void
    {
        file_put_contents(
            $this->projectRoot
            .
            '/VERSION',
            'invalid-version'
        );


        $command =
            new UpgradePlanCommand(
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
            'Overall status: FAIL',
            $output
        );


        self::assertStringContainsString(
            'Can begin: no',
            $output
        );


        self::assertStringContainsString(
            '1. [BLOCKED] Pre-upgrade validation',
            $output
        );


        self::assertStringContainsString(
            'This command made no changes.',
            $output
        );
    }


    private function service(): UpgradePlanService
    {
        $readiness =
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


        return
            new UpgradePlanService(
                $this->projectRoot,
                $readiness
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
            "0.9.0-dev\n"
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
            new \PDO(
                'sqlite:'
                .
                $this->databasePath
            );


        $database->setAttribute(
            \PDO::ATTR_ERRMODE,
            \PDO::ERRMODE_EXCEPTION
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
