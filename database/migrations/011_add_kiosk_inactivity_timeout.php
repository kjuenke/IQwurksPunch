<?php
declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->db->exec(
            "
            ALTER TABLE company_settings
            ADD COLUMN kiosk_inactivity_timeout_seconds INTEGER
            NOT NULL
            DEFAULT 60
            "
        );
    }


    public function down(): void
    {
        /*
         * Removing a column requires a table-rebuild migration on older
         * SQLite installations. The column is retained during rollback.
         */
    }
};
