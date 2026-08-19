<?php
declare(strict_types=1);

use App\Core\Database;
use App\Core\Migrations\MigrationManager;
use PHPUnit\Framework\TestCase;

final class MigrationCompatibilityTest extends TestCase
{
    private \PDO $database;

    private \ReflectionProperty $connectionProperty;


    protected function setUp(): void
    {
        parent::setUp();


        $this->database =
            new \PDO(
                'sqlite::memory:'
            );


        $this->database->setAttribute(
            \PDO::ATTR_ERRMODE,
            \PDO::ERRMODE_EXCEPTION
        );


        $this->database->setAttribute(
            \PDO::ATTR_DEFAULT_FETCH_MODE,
            \PDO::FETCH_ASSOC
        );


        $this->database->exec(
            'PRAGMA foreign_keys = ON'
        );


        $this->connectionProperty =
            new \ReflectionProperty(
                Database::class,
                'connection'
            );


        $this->connectionProperty->setValue(
            null,
            $this->database
        );
    }


    protected function tearDown(): void
    {
        $this->connectionProperty->setValue(
            null,
            null
        );


        parent::tearDown();
    }


    public function testFreshInstallMigrationsCreateCompatibleCoreSchema(): void
    {
        $manager =
            new MigrationManager(
                $this->database,
                $this->migrationDirectory()
            );


        $manager->ensureMigrationTable();


        $this->migration(
            '001_create_core_tables.php'
        )->up();


        $this->migration(
            '004_add_migration_batches.php'
        )->up();


        $this->database->exec(
            "
            CREATE TABLE report_delivery_schedules
            (
                id INTEGER PRIMARY KEY AUTOINCREMENT
            )
            "
        );


        $this->migration(
            '016_create_email_delivery_attempts.php'
        )->up();


        $this->migration(
            '018_create_email_log.php'
        )->up();


        self::assertSame(
            [
                'id',
                'migration',
                'batch',
                'executed_at'
            ],
            $this->columnNames(
                'migrations'
            )
        );


        self::assertSame(
            [
                'id',
                'report_type',
                'recipients',
                'status',
                'sent_at'
            ],
            $this->columnNames(
                'email_log'
            )
        );


        self::assertSame(
            [],
            $this->database
                ->query(
                    'PRAGMA foreign_key_check'
                )
                ->fetchAll()
        );
    }


    public function testBatchRepairSupportsLegacyMigrationTable(): void
    {
        $this->database->exec(
            "
            CREATE TABLE migrations
            (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                migration TEXT NOT NULL UNIQUE,
                executed_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
            "
        );


        $manager =
            new MigrationManager(
                $this->database,
                $this->migrationDirectory()
            );


        $manager->ensureMigrationTable();


        $this->migration(
            '004_add_migration_batches.php'
        )->up();


        self::assertSame(
            1,
            count(
                array_filter(
                    $this->columnNames(
                        'migrations'
                    ),
                    static fn (string $column): bool =>
                        $column === 'batch'
                )
            )
        );
    }


    public function testEmailLogRepairPreservesExistingHistory(): void
    {
        $this->database->exec(
            "
            CREATE TABLE email_log
            (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                report_type TEXT NOT NULL,
                recipients TEXT NOT NULL,
                status TEXT NOT NULL,
                sent_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
            "
        );


        $this->database->exec(
            "
            INSERT INTO email_log
            (
                report_type,
                recipients,
                status
            )
            VALUES
            (
                'Daily Payroll Report',
                'payroll@example.com',
                'sent'
            )
            "
        );


        $this->migration(
            '018_create_email_log.php'
        )->up();


        $this->migration(
            '018_create_email_log.php'
        )->up();


        self::assertSame(
            1,
            (int)$this->database
                ->query(
                    'SELECT COUNT(*) FROM email_log'
                )
                ->fetchColumn()
        );


        self::assertSame(
            'Daily Payroll Report',
            (string)$this->database
                ->query(
                    'SELECT report_type FROM email_log'
                )
                ->fetchColumn()
        );
    }


    private function migration(
        string $name
    ): object
    {
        return require $this->migrationDirectory()
            .
            '/'
            .
            $name;
    }


    private function migrationDirectory(): string
    {
        return dirname(
            __DIR__,
            3
        )
        .
        '/database/migrations';
    }


    /**
     * @return array<int,string>
     */
    private function columnNames(
        string $table
    ): array
    {
        $columns =
            $this->database
                ->query(
                    sprintf(
                        'PRAGMA table_info(%s)',
                        $table
                    )
                )
                ->fetchAll();


        return array_map(
            static fn (array $column): string =>
                (string)$column['name'],
            $columns
        );
    }
}
