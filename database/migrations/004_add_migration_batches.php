<?php
declare(strict_types=1);

return new class
{
    public function up(): void
    {
        $db = \App\Core\Database::connection();


        $columns =
            $db
                ->query(
                    "
                    PRAGMA table_info(
                        migrations
                    )
                    "
                )
                ->fetchAll(
                    \PDO::FETCH_ASSOC
                );


        foreach ($columns as $column) {

            if (
                (
                    $column['name']
                    ??
                    null
                )
                ===
                'batch'
            ) {
                return;
            }
        }

        $db->exec(
            "
            ALTER TABLE migrations
            ADD COLUMN batch INTEGER DEFAULT 1
            "
        );
    }


    public function down(): void
    {
        // SQLite rollback will be handled later
    }
};
