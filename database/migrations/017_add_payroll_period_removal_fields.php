<?php
declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->db->exec(
            '
            ALTER TABLE payroll_periods
            ADD COLUMN archived_at DATETIME NULL
            '
        );

        $this->db->exec(
            '
            ALTER TABLE payroll_periods
            ADD COLUMN archived_by_user_id INTEGER NULL
                REFERENCES users(id)
                ON DELETE RESTRICT
            '
        );

        $this->db->exec(
            '
            ALTER TABLE payroll_periods
            ADD COLUMN archive_reason TEXT NULL
            '
        );

        $this->db->exec(
            '
            ALTER TABLE payroll_periods
            ADD COLUMN voided_at DATETIME NULL
            '
        );

        $this->db->exec(
            '
            ALTER TABLE payroll_periods
            ADD COLUMN voided_by_user_id INTEGER NULL
                REFERENCES users(id)
                ON DELETE RESTRICT
            '
        );

        $this->db->exec(
            '
            ALTER TABLE payroll_periods
            ADD COLUMN void_reason TEXT NULL
            '
        );

        $this->db->exec(
            '
            CREATE INDEX idx_payroll_periods_archived_at
            ON payroll_periods
            (
                archived_at
            )
            '
        );

        $this->db->exec(
            '
            CREATE INDEX idx_payroll_periods_voided_at
            ON payroll_periods
            (
                voided_at
            )
            '
        );
    }


    public function down(): void
    {
        /*
         * SQLite cannot safely remove these columns with a simple,
         * portable ALTER TABLE statement. A rollback would require
         * rebuilding payroll_periods while preserving all dependent
         * workflow tables and foreign-key relationships.
         */
    }
};
