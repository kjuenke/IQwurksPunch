<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

class AuditRepository
{
    private PDO $db;


    public function __construct(PDO $db)
    {
        $this->db = $db;
    }


    public function create(
        string $action,
        string $details,
        ?int $userId = null
    ): bool
    {
        $stmt = $this->db->prepare(
            "
            INSERT INTO audit_log
            (
                user_id,
                action,
                details
            )

            VALUES
            (
                :user_id,
                :action,
                :details
            )
            "
        );


        return $stmt->execute(
            [
                'user_id' => $userId,
                'action'  => $action,
                'details' => $details
            ]
        );
    }
}
