<?php
declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            ALTER TABLE company_settings
            ADD COLUMN daily_overtime_hours REAL
            DEFAULT 8.0
        ");

        $this->db->exec("
            ALTER TABLE company_settings
            ADD COLUMN weekly_overtime_hours REAL
            DEFAULT 40.0
        ");

        $this->db->exec("
            ALTER TABLE company_settings
            ADD COLUMN rounding_minutes INTEGER
            DEFAULT 15
        ");

        $this->db->exec("
            ALTER TABLE company_settings
            ADD COLUMN rounding_mode TEXT
            DEFAULT 'nearest'
        ");

        $this->db->exec("
            ALTER TABLE company_settings
            ADD COLUMN meal_deduction_enabled INTEGER
            DEFAULT 1
        ");

        $this->db->exec("
            ALTER TABLE company_settings
            ADD COLUMN meal_deduction_minutes INTEGER
            DEFAULT 30
        ");

        $this->db->exec("
            ALTER TABLE company_settings
            ADD COLUMN paid_break_minutes INTEGER
            DEFAULT 20
        ");
    }


    public function down(): void
    {
        // SQLite rebuild migration required.
    }
};
