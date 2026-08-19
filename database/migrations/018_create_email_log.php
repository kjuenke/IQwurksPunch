<?php
declare(strict_types=1);

use App\Core\Database;

return new class
{
    public function up(): void
    {
        $database =
            Database::connection();


        $database->exec(
            "
            CREATE TABLE IF NOT EXISTS email_log
            (
                id INTEGER PRIMARY KEY AUTOINCREMENT,

                report_type TEXT NOT NULL,

                recipients TEXT NOT NULL,

                status TEXT NOT NULL,

                sent_at DATETIME
                    DEFAULT CURRENT_TIMESTAMP
            )
            "
        );
    }


    public function down(): void
    {
        /*
         * This repair migration may adopt an email_log table created before
         * the table was added to migration history. It must not remove that
         * table or its delivery history during rollback.
         */
    }
};
