<?php
declare(strict_types=1);

use App\Logging\LoggerInterface;
use App\Services\UpgradeExecutionOperationsService;
use PHPUnit\Framework\TestCase;

final class UpgradeExecutionOperationsServiceTest extends TestCase
{
    private string $projectRoot;

    private string $databasePath;

    private string $backupDirectory;

    private string $maintenanceFile;


    protected function setUp(): void
    {
        parent::setUp();


        $this->projectRoot =
            sys_get_temp_dir()
            .
            '/iqwurks-upgrade-operations-'
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


        $this->backupDirectory =
            $this->projectRoot
            .
            '/storage/backups';


        $this->maintenanceFile =
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


    public function testMaintenanceModeCanBeActivatedAndDeactivated(): void
    {
        $operations =
            $this->service();


        self::assertFalse(
            $operations->maintenanceStatus()['active']
        );


        $activated =
            $operations->activateMaintenance(
                'Upgrade operations test'
            );


        self::assertTrue(
            $activated['active']
        );


        self::assertSame(
            'Upgrade operations test',
            $activated['reason']
        );


        self::assertTrue(
            $operations->maintenanceStatus()['active']
        );


        $deactivated =
            $operations->deactivateMaintenance();


        self::assertFalse(
            $deactivated['active']
        );


        self::assertTrue(
            $deactivated['removed']
        );


        self::assertFileDoesNotExist(
            $this->maintenanceFile
        );
    }


    public function testCreatesAndVerifiesUpgradeBackup(): void
    {
        $result =
            $this->service()
                ->createVerifiedBackup();


        self::assertTrue(
            $result['verified']
        );


        self::assertSame(
            'ok',
            $result['integrity']
        );


        self::assertTrue(
            $result['verification']['valid']
        );


        self::assertTrue(
            $result['verification']['integrity_valid']
        );


        self::assertSame(
            0,
            $result['verification']['foreign_key_violation_count']
        );


        self::assertFileExists(
            $result['path']
        );


        self::assertSame(
            $this->backupDirectory
            .
            '/'
            .
            $result['filename'],
            $result['path']
        );
    }


    public function testAppliesRuntimeAndPublicPermissions(): void
    {
        chmod(
            $this->projectRoot
            .
            '/storage/logs/test.log',
            0600
        );


        chmod(
            $this->projectRoot
            .
            '/public/assets/app.css',
            0600
        );


        chmod(
            $this->projectRoot
            .
            '/iqwurks',
            0644
        );


        $result =
            $this->service()
                ->applyPermissions();


        self::assertTrue(
            $result['successful']
        );


        self::assertGreaterThan(
            0,
            $result['directory_count']
        );


        self::assertGreaterThan(
            0,
            $result['file_count']
        );


        self::assertSame(
            02770,
            fileperms(
                $this->projectRoot
                .
                '/storage/logs'
            )
            &
            07777
        );


        self::assertSame(
            0660,
            fileperms(
                $this->projectRoot
                .
                '/storage/logs/test.log'
            )
            &
            07777
        );


        self::assertSame(
            0660,
            fileperms(
                $this->databasePath
            )
            &
            07777
        );


        self::assertSame(
            0640,
            fileperms(
                $this->projectRoot
                .
                '/storage/backups/existing.sqlite'
            )
            &
            07777
        );


        self::assertSame(
            0755,
            fileperms(
                $this->projectRoot
                .
                '/public/assets'
            )
            &
            07777
        );


        self::assertSame(
            0644,
            fileperms(
                $this->projectRoot
                .
                '/public/assets/app.css'
            )
            &
            07777
        );


        self::assertSame(
            0750,
            fileperms(
                $this->projectRoot
                .
                '/iqwurks'
            )
            &
            07777
        );


        self::assertSame(
            0750,
            fileperms(
                $this->projectRoot
                .
                '/migrate.php'
            )
            &
            07777
        );
    }


    public function testMissingRuntimeDirectoryBlocksPermissionApplication(): void
    {
        rmdir(
            $this->projectRoot
            .
            '/storage/exports'
        );


        $this->expectException(
            \RuntimeException::class
        );


        $this->expectExceptionMessage(
            'A required runtime directory is missing'
        );


        $this->service()
            ->applyPermissions();
    }


    private function service(): UpgradeExecutionOperationsService
    {
        return
            new UpgradeExecutionOperationsService(
                $this->projectRoot,
                null,
                $this->logger(),
                null,
                null
            );
    }


    private function logger(): LoggerInterface
    {
        return
            new class implements LoggerInterface
            {
                public function emergency(
                    string $message,
                    array $context = []
                ): void
                {
                }


                public function alert(
                    string $message,
                    array $context = []
                ): void
                {
                }


                public function critical(
                    string $message,
                    array $context = []
                ): void
                {
                }


                public function error(
                    string $message,
                    array $context = []
                ): void
                {
                }


                public function warning(
                    string $message,
                    array $context = []
                ): void
                {
                }


                public function notice(
                    string $message,
                    array $context = []
                ): void
                {
                }


                public function info(
                    string $message,
                    array $context = []
                ): void
                {
                }


                public function debug(
                    string $message,
                    array $context = []
                ): void
                {
                }
            };
    }


    private function createProjectTree(): void
    {
        $directories = [
            'config',
            'database/sqlite',
            'storage/backups',
            'storage/cache',
            'storage/exports',
            'storage/logs',
            'storage/sessions',
            'public/assets'
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
            '/iqwurks',
            '#!/usr/bin/env php'
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
            '/storage/backups/existing.sqlite',
            'existing-backup'
        );


        file_put_contents(
            $this->projectRoot
            .
            '/storage/logs/test.log',
            'log'
        );


        file_put_contents(
            $this->projectRoot
            .
            '/public/assets/app.css',
            'body {}'
        );


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
            '
            PRAGMA foreign_keys = ON;

            CREATE TABLE test_records
            (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL
            );

            INSERT INTO test_records
            (
                name
            )

            VALUES
            (
                "upgrade operations"
            );
            '
        );


        $database =
            null;


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
                        $this->backupDirectory
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
                        $this->maintenanceFile
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
