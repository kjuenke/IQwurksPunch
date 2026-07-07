<?php
declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            ALTER TABLE users
            ADD COLUMN email TEXT
        ");

        $this->db->exec("
            ALTER TABLE users
            ADD COLUMN active INTEGER NOT NULL DEFAULT 1
        ");

        $this->db->exec("
            ALTER TABLE users
            ADD COLUMN last_login DATETIME
        ");
    }

    public function down(): void
    {
        // SQLite does not support simple DROP COLUMN
        // A rebuild migration would be required.
    }
};
