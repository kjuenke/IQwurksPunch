<?php
declare(strict_types=1);

return new class
{
    public function up(): void
    {
        $db = \App\Core\Database::connection();

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
