<?php
declare(strict_types=1);

use App\Core\Database;

return new class
{
    public function up(): void
    {
        $database =
            Database::connection();


        if (
            $this->columnExists(
                $database,
                'notification_recipients',
                'operational_failures'
            )
        ) {
            return;
        }


        $database->exec(
            "
            ALTER TABLE notification_recipients

            ADD COLUMN operational_failures
                INTEGER NOT NULL
                DEFAULT 1
            "
        );
    }


    public function down(): void
    {
        $database =
            Database::connection();


        if (
            !$this->columnExists(
                $database,
                'notification_recipients',
                'operational_failures'
            )
        ) {
            return;
        }


        $database->exec(
            "
            ALTER TABLE notification_recipients

            DROP COLUMN operational_failures
            "
        );
    }


    private function columnExists(
        \PDO $database,
        string $table,
        string $column
    ): bool
    {
        $statement =
            $database->query(
                sprintf(
                    'PRAGMA table_info(%s)',
                    $table
                )
            );


        if ($statement === false) {
            return false;
        }


        $columns =
            $statement->fetchAll(
                \PDO::FETCH_ASSOC
            );


        foreach ($columns as $definition) {

            if (
                isset(
                    $definition['name']
                )
                &&
                (string)$definition['name']
                    ===
                    $column
            ) {
                return true;
            }
        }


        return false;
    }
};
