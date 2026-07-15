<?php
declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->db->exec(
            "
            CREATE TABLE IF NOT EXISTS notification_recipients
            (
                id INTEGER PRIMARY KEY AUTOINCREMENT,

                name TEXT NOT NULL DEFAULT '',

                email TEXT NOT NULL UNIQUE,

                daily_payroll INTEGER NOT NULL DEFAULT 1,

                weekly_payroll INTEGER NOT NULL DEFAULT 1,

                exception_reports INTEGER NOT NULL DEFAULT 1,

                active INTEGER NOT NULL DEFAULT 1,

                created_at DATETIME NOT NULL
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at DATETIME NOT NULL
                    DEFAULT CURRENT_TIMESTAMP
            )
            "
        );


        $mailConfig =
            require __DIR__
            .
            '/../../config/mail.php';


        $recipients =
            $mailConfig['recipients']
            ??
            [];


        if (!is_array($recipients)) {

            return;
        }


        $stmt =
            $this->db->prepare(
                "
                INSERT OR IGNORE INTO notification_recipients
                (
                    name,
                    email,
                    daily_payroll,
                    weekly_payroll,
                    exception_reports,
                    active
                )

                VALUES
                (
                    :name,
                    :email,
                    1,
                    1,
                    1,
                    1
                )
                "
            );


        foreach ($recipients as $email) {

            $email =
                trim(
                    (string)$email
                );


            if (
                $email === ''
                ||
                !filter_var(
                    $email,
                    FILTER_VALIDATE_EMAIL
                )
            ) {
                continue;
            }


            $stmt->execute(
                [
                    'name' =>
                        '',

                    'email' =>
                        strtolower(
                            $email
                        )
                ]
            );
        }
    }


    public function down(): void
    {
        $this->db->exec(
            "
            DROP TABLE IF EXISTS notification_recipients
            "
        );
    }
};
