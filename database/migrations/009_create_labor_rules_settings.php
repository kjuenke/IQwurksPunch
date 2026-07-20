<?php
declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->db->exec(
            "
            CREATE TABLE IF NOT EXISTS labor_rules_settings
            (
                id INTEGER PRIMARY KEY CHECK (id = 1),

                daily_overtime_hours REAL NOT NULL
                    DEFAULT 8,

                weekly_overtime_hours REAL NOT NULL
                    DEFAULT 40,

                double_time_hours REAL NOT NULL
                    DEFAULT 12,

                workweek_start_day TEXT NOT NULL
                    DEFAULT 'monday',

                updated_at DATETIME NOT NULL
                    DEFAULT CURRENT_TIMESTAMP
            )
            "
        );


        $this->db->exec(
            "
            INSERT OR IGNORE INTO labor_rules_settings
            (
                id,
                daily_overtime_hours,
                weekly_overtime_hours,
                double_time_hours,
                workweek_start_day
            )

            VALUES
            (
                1,
                8,
                40,
                12,
                'monday'
            )
            "
        );
    }


    public function down(): void
    {
        $this->db->exec(
            "
            DROP TABLE IF EXISTS labor_rules_settings
            "
        );
    }
};
