<?php
declare(strict_types=1);

namespace App\Core\Migrations;

use PDO;

class MigrationManager
{
    private PDO $db;

    private string $path;


    public function __construct(
        PDO $db,
        string $path
    )
    {
        $this->db = $db;

        $this->path = $path;
    }


    public function ensureMigrationTable(): void
    {
        $this->db->exec(
            "
            CREATE TABLE IF NOT EXISTS migrations
            (
                id INTEGER PRIMARY KEY AUTOINCREMENT,

                migration TEXT NOT NULL UNIQUE,

                batch INTEGER NOT NULL,

                executed_at DATETIME
                    DEFAULT CURRENT_TIMESTAMP
            )
            "
        );
    }


    public function migrationFiles(): array
    {
        $files =
            glob(
                $this->path
                . '/*.php'
            );

        sort(
            $files
        );

        return $files;
    }


    public function executedMigrations(): array
    {
        $stmt =
            $this->db->query(
                "
                SELECT migration
                FROM migrations
                "
            );

        return array_column(
            $stmt->fetchAll(PDO::FETCH_ASSOC),
            'migration'
        );
    }


    public function currentBatch(): int
    {
        $stmt =
            $this->db->query(
                "
                SELECT
                    MAX(batch)
                FROM migrations
                "
            );

        return (int)
            $stmt->fetchColumn();
    }
}
