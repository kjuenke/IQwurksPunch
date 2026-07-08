<?php
declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            ALTER TABLE employees
            ADD COLUMN department TEXT
        ");

        $this->db->exec("
            ALTER TABLE employees
            ADD COLUMN notes TEXT
        ");

        $this->db->exec("
            ALTER TABLE employees
            ADD COLUMN updated_at DATETIME
            DEFAULT CURRENT_TIMESTAMP
        ");
    }


    public function down(): void
    {
        // SQLite does not support DROP COLUMN easily.
        // Future rebuild migration can handle rollback.
    }
};
