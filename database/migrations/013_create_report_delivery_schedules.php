<?php
declare(strict_types=1);

return new class
{
    public function up(): void
    {
        $db =
            \App\Core\Database::connection();


        $db->exec(
            "
            CREATE TABLE report_delivery_schedules
            (
                id INTEGER PRIMARY KEY AUTOINCREMENT,

                report_type TEXT NOT NULL,

                enabled INTEGER NOT NULL DEFAULT 0,

                send_time TEXT NOT NULL DEFAULT '18:00',

                weekdays_only INTEGER NOT NULL DEFAULT 0,

                send_day_of_week INTEGER DEFAULT NULL,

                last_sent_at DATETIME DEFAULT NULL,

                last_result TEXT DEFAULT NULL,

                last_error TEXT DEFAULT NULL,

                created_at DATETIME
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at DATETIME
                    DEFAULT CURRENT_TIMESTAMP,

                CHECK
                (
                    report_type IN
                    (
                        'daily_payroll',
                        'weekly_payroll',
                        'exception_report'
                    )
                ),

                CHECK
                (
                    enabled IN
                    (
                        0,
                        1
                    )
                ),

                CHECK
                (
                    weekdays_only IN
                    (
                        0,
                        1
                    )
                ),

                CHECK
                (
                    send_day_of_week IS NULL
                    OR
                    send_day_of_week BETWEEN 1 AND 7
                )
            )
            "
        );


        $db->exec(
            "
            CREATE INDEX
                idx_report_delivery_schedules_type

            ON report_delivery_schedules
            (
                report_type
            )
            "
        );


        $db->exec(
            "
            CREATE INDEX
                idx_report_delivery_schedules_due

            ON report_delivery_schedules
            (
                enabled,
                report_type,
                send_time,
                send_day_of_week
            )
            "
        );


        /*
         * Preserve the existing automatic daily-payroll schedule.
         */
        $db->exec(
            "
            INSERT INTO report_delivery_schedules
            (
                report_type,
                enabled,
                send_time,
                weekdays_only,
                send_day_of_week,
                last_sent_at,
                last_result,
                created_at,
                updated_at
            )

            SELECT
                'daily_payroll',
                enabled,
                send_time,
                weekdays_only,
                NULL,
                last_sent_at,
                CASE
                    WHEN last_sent_at IS NULL
                        THEN NULL
                    ELSE 'sent'
                END,
                created_at,
                updated_at

            FROM report_schedule_settings

            ORDER BY id ASC

            LIMIT 1
            "
        );


        /*
         * Create a disabled weekly schedule ready for configuration.
         *
         * ISO day number 1 is Monday. Scheduled weekly reports will
         * initially report the previous completed company workweek.
         */
        $db->exec(
            "
            INSERT INTO report_delivery_schedules
            (
                report_type,
                enabled,
                send_time,
                weekdays_only,
                send_day_of_week
            )

            VALUES
            (
                'weekly_payroll',
                0,
                '08:00',
                0,
                1
            )
            "
        );


        /*
         * Reserve an independent exception-report schedule for later
         * Version 0.8 work without enabling delivery yet.
         */
        $db->exec(
            "
            INSERT INTO report_delivery_schedules
            (
                report_type,
                enabled,
                send_time,
                weekdays_only,
                send_day_of_week
            )

            VALUES
            (
                'exception_report',
                0,
                '08:00',
                1,
                NULL
            )
            "
        );
    }


    public function down(): void
    {
        $db =
            \App\Core\Database::connection();


        $db->exec(
            "
            DROP INDEX IF EXISTS
                idx_report_delivery_schedules_due
            "
        );


        $db->exec(
            "
            DROP INDEX IF EXISTS
                idx_report_delivery_schedules_type
            "
        );


        $db->exec(
            "
            DROP TABLE IF EXISTS
                report_delivery_schedules
            "
        );
    }
};
