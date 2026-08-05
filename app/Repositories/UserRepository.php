<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

class UserRepository
{
    private PDO $db;


    public function __construct(
        PDO $db
    )
    {
        $this->db =
            $db;
    }


    public function countUsers(): int
    {
        return (int)$this->db
            ->query(
                '
                SELECT COUNT(*)
                FROM users
                '
            )
            ->fetchColumn();
    }


    /**
     * Used by the initial administrator setup workflow.
     */
    public function create(
        string $username,
        string $email,
        string $passwordHash
    ): int
    {
        return $this->createManaged(
            [
                'username' =>
                    $username,

                'email' =>
                    $email,

                'password_hash' =>
                    $passwordHash,

                'role' =>
                    'admin',

                'active' =>
                    1
            ]
        );
    }


    /**
     * @param array<string,mixed> $data
     */
    public function createManaged(
        array $data
    ): int
    {
        $statement =
            $this->db->prepare(
                '
                INSERT INTO users
                (
                    username,
                    email,
                    password_hash,
                    role,
                    active
                )

                VALUES
                (
                    :username,
                    :email,
                    :password_hash,
                    :role,
                    :active
                )
                '
            );


        $statement->execute(
            [
                'username' =>
                    trim(
                        (string)$data['username']
                    ),

                'email' =>
                    trim(
                        (string)$data['email']
                    ),

                'password_hash' =>
                    (string)$data['password_hash'],

                'role' =>
                    strtolower(
                        trim(
                            (string)$data['role']
                        )
                    ),

                'active' =>
                    (int)$data['active']
            ]
        );


        return (int)$this->db
            ->lastInsertId();
    }


    /**
     * The password hash is intentionally excluded.
     *
     * @return array<int,array<string,mixed>>
     */
    public function all(): array
    {
        $statement =
            $this->db->query(
                '
                SELECT
                    id,
                    username,
                    email,
                    role,
                    active,
                    created_at,
                    last_login

                FROM users

                ORDER BY
                    active DESC,
                    CASE
                        WHEN LOWER(role) = "admin"
                        THEN 0
                        ELSE 1
                    END ASC,
                    username COLLATE NOCASE ASC
                '
            );


        return $statement->fetchAll(
            PDO::FETCH_ASSOC
        );
    }


    public function findById(
        int $id
    ): ?array
    {
        $statement =
            $this->db->prepare(
                '
                SELECT *
                FROM users

                WHERE id = :id

                LIMIT 1
                '
            );


        $statement->execute(
            [
                'id' =>
                    $id
            ]
        );


        $user =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );


        return $user ?: null;
    }


    public function findByUsername(
        string $username
    ): ?array
    {
        $statement =
            $this->db->prepare(
                '
                SELECT *
                FROM users

                WHERE LOWER(username) =
                    LOWER(:username)

                LIMIT 1
                '
            );


        $statement->execute(
            [
                'username' =>
                    trim(
                        $username
                    )
            ]
        );


        $user =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );


        return $user ?: null;
    }


    public function usernameExists(
        string $username,
        ?int $excludeId = null
    ): bool
    {
        $sql =
            '
            SELECT COUNT(*)
            FROM users

            WHERE LOWER(username) =
                LOWER(:username)
            ';

        $parameters = [
            'username' =>
                trim(
                    $username
                )
        ];


        if ($excludeId !== null) {
            $sql .=
                '
                AND id <> :exclude_id
                ';

            $parameters['exclude_id'] =
                $excludeId;
        }


        $statement =
            $this->db->prepare(
                $sql
            );


        $statement->execute(
            $parameters
        );


        return (int)$statement
            ->fetchColumn() > 0;
    }


    /**
     * @param array<string,mixed> $data
     */
    public function updateDetails(
        int $id,
        array $data
    ): bool
    {
        $statement =
            $this->db->prepare(
                '
                UPDATE users

                SET
                    username = :username,
                    email = :email,
                    role = :role

                WHERE id = :id
                '
            );


        $statement->execute(
            [
                'username' =>
                    trim(
                        (string)$data['username']
                    ),

                'email' =>
                    trim(
                        (string)$data['email']
                    ),

                'role' =>
                    strtolower(
                        trim(
                            (string)$data['role']
                        )
                    ),

                'id' =>
                    $id
            ]
        );


        return $statement->rowCount() > 0;
    }


    public function updatePassword(
        int $id,
        string $passwordHash
    ): bool
    {
        $statement =
            $this->db->prepare(
                '
                UPDATE users

                SET password_hash = :password_hash

                WHERE id = :id
                '
            );


        $statement->execute(
            [
                'password_hash' =>
                    $passwordHash,

                'id' =>
                    $id
            ]
        );


        return $statement->rowCount() > 0;
    }


    public function activate(
        int $id
    ): bool
    {
        return $this->setActive(
            $id,
            true
        );
    }


    public function deactivate(
        int $id
    ): bool
    {
        return $this->setActive(
            $id,
            false
        );
    }


    public function countActiveAdministrators(): int
    {
        $statement =
            $this->db->query(
                '
                SELECT COUNT(*)
                FROM users

                WHERE active = 1
                  AND LOWER(TRIM(role)) = "admin"
                '
            );


        return (int)$statement
            ->fetchColumn();
    }


    public function updateLastLogin(
        int $id
    ): bool
    {
        $statement =
            $this->db->prepare(
                '
                UPDATE users

                SET last_login =
                    CURRENT_TIMESTAMP

                WHERE id = :id
                '
            );


        return $statement->execute(
            [
                'id' =>
                    $id
            ]
        );
    }


    private function setActive(
        int $id,
        bool $active
    ): bool
    {
        $statement =
            $this->db->prepare(
                '
                UPDATE users

                SET active = :active

                WHERE id = :id
                '
            );


        $statement->execute(
            [
                'active' =>
                    $active
                        ? 1
                        : 0,

                'id' =>
                    $id
            ]
        );


        return $statement->rowCount() > 0;
    }
}
