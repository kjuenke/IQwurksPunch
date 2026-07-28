<?php
declare(strict_types=1);

return new class
{
    public function up(): void
    {
        $db =
            \App\Core\Database::connection();


        $columns =
            $db
                ->query(
                    "
                    PRAGMA table_info(
                        notification_recipients
                    )
                    "
                )
                ->fetchAll(
                    \PDO::FETCH_ASSOC
                );


        foreach ($columns as $column) {

            if (
                (
                    $column['name']
                    ??
                    null
                )
                ===
                'approval_notifications'
            ) {
                return;
            }
        }


        $db->exec(
            "
            ALTER TABLE notification_recipients

            ADD COLUMN approval_notifications
                INTEGER NOT NULL
                DEFAULT 1
            "
        );
    }


    public function down(): void
    {
        $db =
            \App\Core\Database::connection();


        $columns =
            $db
                ->query(
                    "
                    PRAGMA table_info(
                        notification_recipients
                    )
                    "
                )
                ->fetchAll(
                    \PDO::FETCH_ASSOC
                );


        $columnExists =
            false;


        foreach ($columns as $column) {

            if (
                (
                    $column['name']
                    ??
                    null
                )
                ===
                'approval_notifications'
            ) {
                $columnExists =
                    true;


                break;
            }
        }


        if (!$columnExists) {
            return;
        }


        $db->exec(
            "
            ALTER TABLE notification_recipients

            DROP COLUMN approval_notifications
            "
        );
    }
};
