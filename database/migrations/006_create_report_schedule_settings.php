<?php
declare(strict_types=1);

return new class
{
    public function up(): void
    {
        $db = \App\Core\Database::connection();

        $db->exec(
            "
            CREATE TABLE report_schedule_settings
            (
                id INTEGER PRIMARY KEY AUTOINCREMENT,

                enabled INTEGER NOT NULL DEFAULT 0,

                send_time TEXT NOT NULL DEFAULT '18:00',

                weekdays_only INTEGER NOT NULL DEFAULT 1,

                last_sent_at DATETIME DEFAULT NULL,

                created_at DATETIME
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at DATETIME
                    DEFAULT CURRENT_TIMESTAMP
            )
            "
        );

        $db->exec(
            "
            INSERT INTO report_schedule_settings
            (
                enabled,
                send_time,
                weekdays_only
            )
            VALUES
            (
                0,
                '18:00',
                1
            )
            "
        );
    }


    public function down(): void
    {
        $db = \App\Core\Database::connection();

        $db->exec(
            "
            DROP TABLE IF EXISTS
            report_schedule_settings
            "
        );
    }
};
