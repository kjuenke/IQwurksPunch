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
            CREATE TABLE IF NOT EXISTS email_delivery_attempts
            (
                id INTEGER PRIMARY KEY AUTOINCREMENT,

                email_log_id INTEGER DEFAULT NULL,

                schedule_id INTEGER DEFAULT NULL,

                notification_type TEXT NOT NULL,

                source TEXT NOT NULL
                    DEFAULT 'manual',

                subject TEXT NOT NULL,

                recipients TEXT NOT NULL,

                status TEXT NOT NULL
                    DEFAULT 'pending',

                attempt_number INTEGER NOT NULL
                    DEFAULT 1,

                max_attempts INTEGER NOT NULL
                    DEFAULT 1,

                retry_of_id INTEGER DEFAULT NULL,

                permanent_failure INTEGER NOT NULL
                    DEFAULT 0,

                error_message TEXT DEFAULT NULL,

                attachment_count INTEGER NOT NULL
                    DEFAULT 0,

                attachment_names TEXT NOT NULL
                    DEFAULT '[]',

                attachment_size_bytes INTEGER NOT NULL
                    DEFAULT 0,

                started_at DATETIME NOT NULL
                    DEFAULT CURRENT_TIMESTAMP,

                completed_at DATETIME DEFAULT NULL,

                created_at DATETIME NOT NULL
                    DEFAULT CURRENT_TIMESTAMP,

                FOREIGN KEY
                (
                    email_log_id
                )
                REFERENCES email_log
                (
                    id
                )
                ON DELETE SET NULL,

                FOREIGN KEY
                (
                    schedule_id
                )
                REFERENCES report_delivery_schedules
                (
                    id
                )
                ON DELETE SET NULL,

                FOREIGN KEY
                (
                    retry_of_id
                )
                REFERENCES email_delivery_attempts
                (
                    id
                )
                ON DELETE SET NULL,

                CHECK
                (
                    source IN
                    (
                        'manual',
                        'scheduled',
                        'system',
                        'retry'
                    )
                ),

                CHECK
                (
                    status IN
                    (
                        'pending',
                        'sent',
                        'failed'
                    )
                ),

                CHECK
                (
                    attempt_number >= 1
                ),

                CHECK
                (
                    max_attempts >= 1
                ),

                CHECK
                (
                    attempt_number <= max_attempts
                ),

                CHECK
                (
                    permanent_failure IN
                    (
                        0,
                        1
                    )
                ),

                CHECK
                (
                    attachment_count >= 0
                ),

                CHECK
                (
                    attachment_size_bytes >= 0
                )
            )
            "
        );


        $database->exec(
            "
            CREATE INDEX IF NOT EXISTS
                idx_email_delivery_attempts_status

            ON email_delivery_attempts
            (
                status,
                permanent_failure,
                created_at
            )
            "
        );


        $database->exec(
            "
            CREATE INDEX IF NOT EXISTS
                idx_email_delivery_attempts_notification

            ON email_delivery_attempts
            (
                notification_type,
                created_at
            )
            "
        );


        $database->exec(
            "
            CREATE INDEX IF NOT EXISTS
                idx_email_delivery_attempts_schedule

            ON email_delivery_attempts
            (
                schedule_id,
                created_at
            )
            "
        );


        $database->exec(
            "
            CREATE INDEX IF NOT EXISTS
                idx_email_delivery_attempts_retry

            ON email_delivery_attempts
            (
                retry_of_id,
                attempt_number
            )
            "
        );
    }


    public function down(): void
    {
        $database =
            Database::connection();


        $database->exec(
            "
            DROP INDEX IF EXISTS
                idx_email_delivery_attempts_retry
            "
        );


        $database->exec(
            "
            DROP INDEX IF EXISTS
                idx_email_delivery_attempts_schedule
            "
        );


        $database->exec(
            "
            DROP INDEX IF EXISTS
                idx_email_delivery_attempts_notification
            "
        );


        $database->exec(
            "
            DROP INDEX IF EXISTS
                idx_email_delivery_attempts_status
            "
        );


        $database->exec(
            "
            DROP TABLE IF EXISTS
                email_delivery_attempts
            "
        );
    }
};
