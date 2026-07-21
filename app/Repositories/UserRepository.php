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


    public function create(
        string $username,
        string $email,
        string $passwordHash
    ): int
    {
        $statement =
            $this->db->prepare(
                "
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
                    'admin',
                    1
                )
                "
            );


        $statement->execute(
            [
                'username' =>
                    trim(
                        $username
                    ),

                'email' =>
                    trim(
                        $email
                    ),

                'password_hash' =>
                    $passwordHash
            ]
        );


        return (int)$this->db
            ->lastInsertId();
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
}
