<?php
declare(strict_types=1);

use App\Services\UpgradePlanService;
use App\Services\UpgradeReadinessService;
use PHPUnit\Framework\TestCase;

final class UpgradePlanServiceTest extends TestCase
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
            '/iqwurks-upgrade-plan-'
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


    public function testPlanDescribesCompleteReadOnlyUpgradeWorkflow(): void
    {
        $plan =
            $this->service()
                ->plan();


        self::assertSame(
            UpgradeReadinessService::WARN,
            $plan['overall_status']
        );


        self::assertFalse(
            $plan['blocked']
        );


        self::assertTrue(
            $plan['can_begin']
        );


        self::assertFalse(
            $plan['maintenance_active']
        );


        self::assertSame(
            0,
            $plan['pending_migration_count']
        );


        self::assertCount(
            9,
            $plan['stages']
        );


        self::assertSame(
            'REQUIRED',
            $this->stage(
                $plan,
                'maintenance'
            )['status']
        );


        self::assertContains(
            './iqwurks backup:create',
            $this->stage(
                $plan,
                'backup'
            )['commands']
        );


        self::assertContains(
            'composer install --no-interaction --prefer-dist --optimize-autoloader',
            $this->stage(
                $plan,
                'dependencies'
            )['commands']
        );


        self::assertContains(
            'php migrate.php',
            $this->stage(
                $plan,
                'migrations'
            )['commands']
        );


        self::assertContains(
            './iqwurks database:check',
            $this->stage(
                $plan,
                'validation'
            )['commands']
        );


        self::assertContains(
            './iqwurks maintenance:off',
            $this->stage(
                $plan,
                'complete'
            )['commands']
        );


        self::assertContains(
            './iqwurks backup:restore PRE_UPGRADE_BACKUP_FILENAME',
            $this->stage(
                $plan,
                'rollback'
            )['commands']
        );
    }


    public function testPendingMigrationIsIncludedInPlan(): void
    {
        file_put_contents(
            $this->projectRoot
            .
            '/database/migrations/003_pending.php',
            '<?php return new stdClass();'
        );


        $plan =
            $this->service()
                ->plan();


        self::assertSame(
            1,
            $plan['pending_migration_count']
        );


        self::assertSame(
            [
                '003_pending.php'
            ],
            $plan['pending_migrations']
        );


        self::assertSame(
            'PENDING',
            $this->stage(
                $plan,
                'migrations'
            )['status']
        );


        self::assertStringContainsString(
            '003_pending.php',
            implode(
                ' ',
                $this->stage(
                    $plan,
                    'migrations'
                )['details']
            )
        );
    }


    public function testReadinessFailureBlocksPlan(): void
    {
        file_put_contents(
            $this->projectRoot
            .
            '/VERSION',
            'invalid-version'
        );


        $plan =
            $this->service()
                ->plan();


        self::assertSame(
            UpgradeReadinessService::FAIL,
            $plan['overall_status']
        );


        self::assertTrue(
            $plan['blocked']
        );


        self::assertFalse(
            $plan['can_begin']
        );


        self::assertSame(
            'BLOCKED',
            $this->stage(
                $plan,
                'preflight'
            )['status']
        );
    }


    public function testActiveMaintenanceModeIsReflectedInPlan(): void
    {
        $this->activateMaintenance();


        $plan =
            $this->service()
                ->plan();


        self::assertTrue(
            $plan['maintenance_active']
        );


        self::assertSame(
            UpgradeReadinessService::PASS,
            $plan['overall_status']
        );


        self::assertSame(
            'READY',
            $this->stage(
                $plan,
                'maintenance'
            )['status']
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


    /**
     * @param array<string,mixed> $plan
     *
     * @return array<string,mixed>
     */
    private function stage(
        array $plan,
        string $id
    ): array
    {
        foreach ($plan['stages'] as $stage) {

            if (
                (
                    $stage['id']
                    ??
                    null
                )
                ===
                $id
            ) {
                return $stage;
            }
        }


        self::fail(
            'The expected upgrade stage was not found: '
            .
            $id
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


    private function activateMaintenance(): void
    {
        file_put_contents(
            $this->maintenancePath,
            json_encode(
                [
                    'active' =>
                        true,

                    'reason' =>
                        'Upgrade plan test',

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
