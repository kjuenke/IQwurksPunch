<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

class EmailRepository
{
    private PDO $db;


    public function __construct(PDO $db)
    {
        $this->db = $db;
    }



    public function create(
        string $reportType,
        string $recipients,
        string $status
    ): bool
    {
        $stmt =
            $this->db->prepare(
                "
                INSERT INTO email_log
                (
                    report_type,
                    recipients,
                    status
                )

                VALUES
                (
                    :report_type,
                    :recipients,
                    :status
                )
                "
            );


        return $stmt->execute(
            [
                'report_type' => $reportType,
                'recipients'  => $recipients,
                'status'      => $status
            ]
        );
    }



    public function all(): array
    {
        $stmt =
            $this->db->query(
                "
                SELECT *
                FROM email_log
                ORDER BY id DESC
                "
            );


        return $stmt->fetchAll();
    }


}
