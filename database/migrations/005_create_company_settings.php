<?php
declare(strict_types=1);

return new class
{
    public function up(): void
    {
        $db = \App\Core\Database::connection();

        $db->exec(
            "
            CREATE TABLE company_settings
            (
                id INTEGER PRIMARY KEY AUTOINCREMENT,

                company_name TEXT NOT NULL,

                address TEXT DEFAULT '',

                city TEXT DEFAULT '',

                state TEXT DEFAULT '',

                zip TEXT DEFAULT '',

                phone TEXT DEFAULT '',

                email TEXT DEFAULT '',

                timezone TEXT DEFAULT 'America/Los_Angeles',

                pay_period_start TEXT DEFAULT 'monday',

                created_at DATETIME
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at DATETIME
                    DEFAULT CURRENT_TIMESTAMP
            )
            "
        );


        $stmt =
            $db->prepare(
                "
                INSERT INTO company_settings
                (
                    company_name
                )

                VALUES
                (
                    :name
                )
                "
            );


        $stmt->execute(
            [
                'name' =>
                    'IQwurksPunch'
            ]
        );
    }


    public function down(): void
    {
        $db = \App\Core\Database::connection();

        $db->exec(
            "
            DROP TABLE company_settings
            "
        );
    }
};
