<?php
declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->db->exec(
            "
            CREATE TABLE IF NOT EXISTS payroll_periods
            (
                id INTEGER PRIMARY KEY AUTOINCREMENT,

                period_name TEXT NOT NULL,

                start_date TEXT NOT NULL,

                end_date TEXT NOT NULL,

                status TEXT NOT NULL
                    DEFAULT 'open',

                created_by_user_id INTEGER NOT NULL,

                reviewed_by_user_id INTEGER NULL,

                approved_by_user_id INTEGER NULL,

                locked_by_user_id INTEGER NULL,

                created_at DATETIME NOT NULL
                    DEFAULT CURRENT_TIMESTAMP,

                review_started_at DATETIME NULL,

                approved_at DATETIME NULL,

                locked_at DATETIME NULL,

                updated_at DATETIME NOT NULL
                    DEFAULT CURRENT_TIMESTAMP,

                FOREIGN KEY
                (
                    created_by_user_id
                )
                REFERENCES users(id)
                ON DELETE RESTRICT,

                FOREIGN KEY
                (
                    reviewed_by_user_id
                )
                REFERENCES users(id)
                ON DELETE RESTRICT,

                FOREIGN KEY
                (
                    approved_by_user_id
                )
                REFERENCES users(id)
                ON DELETE RESTRICT,

                FOREIGN KEY
                (
                    locked_by_user_id
                )
                REFERENCES users(id)
                ON DELETE RESTRICT,

                CHECK
                (
                    status IN
                    (
                        'open',
                        'under_review',
                        'approved',
                        'locked'
                    )
                ),

                CHECK
                (
                    start_date <= end_date
                )
            )
            "
        );


        $this->db->exec(
            "
            CREATE INDEX IF NOT EXISTS
                idx_payroll_periods_dates

            ON payroll_periods
            (
                start_date,
                end_date
            )
            "
        );


        $this->db->exec(
            "
            CREATE INDEX IF NOT EXISTS
                idx_payroll_periods_status

            ON payroll_periods
            (
                status
            )
            "
        );


        $this->db->exec(
            "
            CREATE TABLE IF NOT EXISTS payroll_period_history
            (
                id INTEGER PRIMARY KEY AUTOINCREMENT,

                payroll_period_id INTEGER NOT NULL,

                action TEXT NOT NULL,

                previous_status TEXT NULL,

                new_status TEXT NOT NULL,

                reason TEXT NULL,

                user_id INTEGER NOT NULL,

                created_at DATETIME NOT NULL
                    DEFAULT CURRENT_TIMESTAMP,

                FOREIGN KEY
                (
                    payroll_period_id
                )
                REFERENCES payroll_periods(id)
                ON DELETE RESTRICT,

                FOREIGN KEY
                (
                    user_id
                )
                REFERENCES users(id)
                ON DELETE RESTRICT,

                CHECK
                (
                    previous_status IS NULL
                    OR
                    previous_status IN
                    (
                        'open',
                        'under_review',
                        'approved',
                        'locked'
                    )
                ),

                CHECK
                (
                    new_status IN
                    (
                        'open',
                        'under_review',
                        'approved',
                        'locked'
                    )
                )
            )
            "
        );


        $this->db->exec(
            "
            CREATE INDEX IF NOT EXISTS
                idx_payroll_period_history_period

            ON payroll_period_history
            (
                payroll_period_id,
                created_at,
                id
            )
            "
        );


        $this->db->exec(
            "
            CREATE INDEX IF NOT EXISTS
                idx_payroll_period_history_user

            ON payroll_period_history
            (
                user_id,
                created_at
            )
            "
        );


        $this->db->exec(
            "
            CREATE TABLE IF NOT EXISTS payroll_review_notes
            (
                id INTEGER PRIMARY KEY AUTOINCREMENT,

                payroll_period_id INTEGER NOT NULL,

                note TEXT NOT NULL,

                created_by_user_id INTEGER NOT NULL,

                created_at DATETIME NOT NULL
                    DEFAULT CURRENT_TIMESTAMP,

                FOREIGN KEY
                (
                    payroll_period_id
                )
                REFERENCES payroll_periods(id)
                ON DELETE RESTRICT,

                FOREIGN KEY
                (
                    created_by_user_id
                )
                REFERENCES users(id)
                ON DELETE RESTRICT,

                CHECK
                (
                    LENGTH(
                        TRIM(
                            note
                        )
                    )
                    >
                    0
                )
            )
            "
        );


        $this->db->exec(
            "
            CREATE INDEX IF NOT EXISTS
                idx_payroll_review_notes_period

            ON payroll_review_notes
            (
                payroll_period_id,
                created_at,
                id
            )
            "
        );


        $this->db->exec(
            "
            CREATE TABLE IF NOT EXISTS payroll_exception_resolutions
            (
                id INTEGER PRIMARY KEY AUTOINCREMENT,

                payroll_period_id INTEGER NOT NULL,

                employee_id INTEGER NULL,

                exception_key TEXT NOT NULL,

                exception_type TEXT NOT NULL,

                exception_date TEXT NULL,

                description TEXT NOT NULL,

                resolution_status TEXT NOT NULL
                    DEFAULT 'open',

                resolution_note TEXT NULL,

                resolved_by_user_id INTEGER NULL,

                resolved_at DATETIME NULL,

                created_at DATETIME NOT NULL
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at DATETIME NOT NULL
                    DEFAULT CURRENT_TIMESTAMP,

                FOREIGN KEY
                (
                    payroll_period_id
                )
                REFERENCES payroll_periods(id)
                ON DELETE RESTRICT,

                FOREIGN KEY
                (
                    employee_id
                )
                REFERENCES employees(id)
                ON DELETE RESTRICT,

                FOREIGN KEY
                (
                    resolved_by_user_id
                )
                REFERENCES users(id)
                ON DELETE RESTRICT,

                CHECK
                (
                    resolution_status IN
                    (
                        'open',
                        'resolved',
                        'accepted'
                    )
                ),

                CHECK
                (
                    LENGTH(
                        TRIM(
                            exception_key
                        )
                    )
                    >
                    0
                ),

                CHECK
                (
                    LENGTH(
                        TRIM(
                            exception_type
                        )
                    )
                    >
                    0
                ),

                CHECK
                (
                    LENGTH(
                        TRIM(
                            description
                        )
                    )
                    >
                    0
                ),

                UNIQUE
                (
                    payroll_period_id,
                    exception_key
                )
            )
            "
        );


        $this->db->exec(
            "
            CREATE INDEX IF NOT EXISTS
                idx_payroll_exception_period_status

            ON payroll_exception_resolutions
            (
                payroll_period_id,
                resolution_status
            )
            "
        );


        $this->db->exec(
            "
            CREATE INDEX IF NOT EXISTS
                idx_payroll_exception_employee

            ON payroll_exception_resolutions
            (
                employee_id,
                exception_date
            )
            "
        );
    }


    public function down(): void
    {
        $this->db->exec(
            "
            DROP TABLE IF EXISTS
                payroll_exception_resolutions
            "
        );


        $this->db->exec(
            "
            DROP TABLE IF EXISTS
                payroll_review_notes
            "
        );


        $this->db->exec(
            "
            DROP TABLE IF EXISTS
                payroll_period_history
            "
        );


        $this->db->exec(
            "
            DROP TABLE IF EXISTS
                payroll_periods
            "
        );
    }
};
