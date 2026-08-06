<?php
declare(strict_types=1);

namespace App\Repositories;

use InvalidArgumentException;
use PDO;

class AuditRepository
{
    private PDO $db;


    public function __construct(
        PDO $db
    )
    {
        $this->db =
            $db;
    }


    public function create(
        string $action,
        string $details,
        ?int $userId = null
    ): bool
    {
        $statement =
            $this->db->prepare(
                '
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
                '
            );


        return $statement->execute(
            [
                'user_id' =>
                    $userId,

                'action' =>
                    $action,

                'details' =>
                    $details
            ]
        );
    }


    /**
     * @return array<int,array<string,mixed>>
     */
    public function recentUserManagementActions(
        int $limit = 100
    ): array
    {
        if (
            $limit < 1
            ||
            $limit > 500
        ) {
            throw new InvalidArgumentException(
                'The user-management activity limit must be between 1 and 500.'
            );
        }


        $statement =
            $this->db->prepare(
                '
                SELECT
                    audit_log.id,
                    audit_log.user_id,
                    audit_log.action,
                    audit_log.details,
                    audit_log.created_at,
                    users.username AS actor_username

                FROM audit_log

                LEFT JOIN users
                    ON users.id =
                        audit_log.user_id

                WHERE audit_log.action
                    LIKE "user.%"

                ORDER BY
                    audit_log.id DESC

                LIMIT :limit
                '
            );


        $statement->bindValue(
            'limit',
            $limit,
            PDO::PARAM_INT
        );


        $statement->execute();


        return $statement->fetchAll(
            PDO::FETCH_ASSOC
        );
    }
}
