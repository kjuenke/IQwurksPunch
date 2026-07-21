<?php
declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (
            !$this->columnExists(
                'punches',
                'source'
            )
        ) {
            $this->db->exec(
                "
                ALTER TABLE punches
                ADD COLUMN source TEXT NOT NULL
                    DEFAULT 'kiosk'
                "
            );
        }


        if (
            !$this->columnExists(
                'punches',
                'notes'
            )
        ) {
            $this->db->exec(
                "
                ALTER TABLE punches
                ADD COLUMN notes TEXT NOT NULL
                    DEFAULT ''
                "
            );
        }


        if (
            !$this->columnExists(
                'punches',
                'corrected_at'
            )
        ) {
            $this->db->exec(
                "
                ALTER TABLE punches
                ADD COLUMN corrected_at DATETIME
                    DEFAULT NULL
                "
            );
        }


        if (
            !$this->columnExists(
                'punches',
                'corrected_by_user_id'
            )
        ) {
            $this->db->exec(
                "
                ALTER TABLE punches
                ADD COLUMN corrected_by_user_id INTEGER
                    DEFAULT NULL
                "
            );
        }


        if (
            !$this->columnExists(
                'punches',
                'correction_reason'
            )
        ) {
            $this->db->exec(
                "
                ALTER TABLE punches
                ADD COLUMN correction_reason TEXT NOT NULL
                    DEFAULT ''
                "
            );
        }


        $this->db->exec(
            "
            CREATE TABLE IF NOT EXISTS punch_correction_history
            (
                id INTEGER PRIMARY KEY AUTOINCREMENT,

                punch_id INTEGER,

                employee_id INTEGER NOT NULL,

                employee_number TEXT NOT NULL
                    DEFAULT '',

                employee_name TEXT NOT NULL
                    DEFAULT '',

                action TEXT NOT NULL
                    CHECK
                    (
                        action IN
                        (
                            'created',
                            'updated',
                            'deleted'
                        )
                    ),

                old_punch_time DATETIME,

                old_punch_type TEXT,

                new_punch_time DATETIME,

                new_punch_type TEXT,

                reason TEXT NOT NULL,

                user_id INTEGER,

                created_at DATETIME NOT NULL
                    DEFAULT CURRENT_TIMESTAMP
            )
            "
        );


        $this->db->exec(
            "
            CREATE INDEX IF NOT EXISTS
                idx_punches_employee_time

            ON punches
            (
                employee_id,
                punch_time
            )
            "
        );


        $this->db->exec(
            "
            CREATE INDEX IF NOT EXISTS
                idx_punch_correction_history_punch

            ON punch_correction_history
            (
                punch_id,
                created_at
            )
            "
        );


        $this->db->exec(
            "
            CREATE INDEX IF NOT EXISTS
                idx_punch_correction_history_employee

            ON punch_correction_history
            (
                employee_id,
                created_at
            )
            "
        );
    }


    public function down(): void
    {
        $this->db->exec(
            "
            DROP TABLE IF EXISTS
                punch_correction_history
            "
        );


        $this->db->exec(
            "
            DROP INDEX IF EXISTS
                idx_punches_employee_time
            "
        );


        if (
            $this->columnExists(
                'punches',
                'correction_reason'
            )
        ) {
            $this->db->exec(
                "
                ALTER TABLE punches
                DROP COLUMN correction_reason
                "
            );
        }


        if (
            $this->columnExists(
                'punches',
                'corrected_by_user_id'
            )
        ) {
            $this->db->exec(
                "
                ALTER TABLE punches
                DROP COLUMN corrected_by_user_id
                "
            );
        }


        if (
            $this->columnExists(
                'punches',
                'corrected_at'
            )
        ) {
            $this->db->exec(
                "
                ALTER TABLE punches
                DROP COLUMN corrected_at
                "
            );
        }


        if (
            $this->columnExists(
                'punches',
                'notes'
            )
        ) {
            $this->db->exec(
                "
                ALTER TABLE punches
                DROP COLUMN notes
                "
            );
        }


        if (
            $this->columnExists(
                'punches',
                'source'
            )
        ) {
            $this->db->exec(
                "
                ALTER TABLE punches
                DROP COLUMN source
                "
            );
        }
    }


    private function columnExists(
        string $table,
        string $column
    ): bool
    {
        $statement =
            $this->db->query(
                "
                PRAGMA table_info(
                    {$table}
                )
                "
            );


        if ($statement === false) {

            return false;
        }


        $columns =
            $statement->fetchAll(
                \PDO::FETCH_ASSOC
            );


        foreach ($columns as $definition) {

            if (
                (
                    $definition['name']
                    ??
                    null
                )
                ===
                $column
            ) {
                return true;
            }
        }


        return false;
    }
};
