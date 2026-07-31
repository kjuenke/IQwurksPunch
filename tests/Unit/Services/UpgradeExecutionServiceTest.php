<?php
declare(strict_types=1);

use App\Services\ProcessRunnerInterface;
use App\Services\UpgradeExecutionService;
use App\Services\UpgradePlanService;
use App\Services\UpgradeReadinessService;
use PHPUnit\Framework\TestCase;

final class UpgradeExecutionServiceTest extends TestCase
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
            '/iqwurks-upgrade-execution-'
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


    public function testPreviewDefinesGuardedWorkflowWithoutRunningProcesses(): void
    {
        $runner =
            new class implements ProcessRunnerInterface
            {
                public int $calls = 0;


                public function run(
                    array $command,
                    ?string $workingDirectory = null,
                    array $environment = [],
                    ?float $timeoutSeconds = null
                ): array
                {
                    $this->calls++;


                    return [
                        'exit_code' =>
                            0,

                        'successful' =>
                            true
                    ];
                }
            };


        $preview =
            $this->service(
                $runner
            )->preview();


        self::assertSame(
            0,
            $runner->calls
        );


        self::assertSame(
            'preview',
            $preview['mode']
        );


        self::assertFalse(
            $preview['blocked']
        );


        self::assertTrue(
            $preview['can_apply']
        );


        self::assertSame(
            'UPGRADE 0.9.0-dev',
            $preview['confirmation_phrase']
        );


        self::assertFalse(
            $preview['changes_made']
        );


        self::assertFalse(
            $preview['rollback_available']
        );


        self::assertCount(
            9,
            $preview['stages']
        );


        self::assertSame(
            9,
            $preview['process_command_count']
        );


        self::assertSame(
            'REQUIRED',
            $this->stage(
                $preview,
                'maintenance'
            )['status']
        );


        $composer =
            $this->stage(
                $preview,
                'dependencies'
            )['process_commands'][0];


        self::assertSame(
            [
                '/test/bin/composer',
                'install',
                '--no-interaction',
                '--prefer-dist',
                '--optimize-autoloader'
            ],
            $composer['command']
        );


        self::assertSame(
            [
                'COMPOSER_ALLOW_SUPERUSER' =>
                    '1'
            ],
            $composer['environment']
        );


        self::assertFalse(
            $composer['will_execute_in_preview']
        );


        $rollback =
            $this->stage(
                $preview,
                'rollback'
            )['process_commands'][0];


        self::assertTrue(
            $rollback['template']
        );


        self::assertContains(
            '--confirm=PRE_UPGRADE_BACKUP_FILENAME',
            $rollback['command']
        );
    }


    public function testReadinessFailureBlocksExecutionPreview(): void
    {
        file_put_contents(
            $this->projectRoot
            .
            '/VERSION',
            'invalid-version'
        );


        $preview =
            $this->service()
                ->preview();


        self::assertTrue(
            $preview['blocked']
        );


        self::assertFalse(
            $preview['can_apply']
        );


        self::assertSame(
            UpgradeReadinessService::FAIL,
            $preview['overall_status']
        );


        self::assertSame(
            'BLOCKED',
            $this->stage(
                $preview,
                'preflight'
            )['status']
        );


        self::assertFalse(
            $preview['changes_made']
        );
    }


    public function testPendingMigrationIsIncludedInExecutionPreview(): void
    {
        file_put_contents(
            $this->projectRoot
            .
            '/database/migrations/003_pending.php',
            '<?php return new stdClass();'
        );


        $preview =
            $this->service()
                ->preview();


        self::assertSame(
            1,
            $preview['pending_migration_count']
        );


        self::assertSame(
            [
                '003_pending.php'
            ],
            $preview['pending_migrations']
        );


        $migrationStage =
            $this->stage(
                $preview,
                'migrations'
            );


        self::assertSame(
            'PENDING',
            $migrationStage['status']
        );


        self::assertStringContainsString(
            '003_pending.php',
            implode(
                ' ',
                $migrationStage['details']
            )
        );


        self::assertSame(
            [
                '/test/bin/php',
                $this->projectRoot
                .
                '/migrate.php'
            ],
            $migrationStage['process_commands'][0]['command']
        );
    }


    private function service(
        ?ProcessRunnerInterface $runner = null
    ): UpgradeExecutionService
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


        $plans =
            new UpgradePlanService(
                $this->projectRoot,
                $readiness
            );


        return
            new UpgradeExecutionService(
                $this->projectRoot,
                $plans,
                $runner,
                '/test/bin/php',
                '/test/bin/composer'
            );
    }


    /**
     * @param array<string,mixed> $preview
     *
     * @return array<string,mixed>
     */
    private function stage(
        array $preview,
        string $id
    ): array
    {
        foreach ($preview['stages'] as $stage) {

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
            'The expected execution stage was not found: '
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
            'vendor/bin'
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
            '/iqwurks',
            '#!/usr/bin/env php'
        );


        file_put_contents(
            $this->projectRoot
            .
            '/vendor/autoload.php',
            '<?php'
        );


        file_put_contents(
            $this->projectRoot
            .
            '/vendor/bin/phpunit',
            '#!/usr/bin/env php'
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
