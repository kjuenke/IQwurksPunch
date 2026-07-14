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
                'SELECT COUNT(*) FROM users'
            )
            ->fetchColumn();
    }


    public function create(
        string $username,
        string $email,
        string $passwordHash
    ): int
    {
        $stmt =
            $this->db->prepare(
                "
                INSERT INTO users
                (
                    username,
                    email,
                    password_hash,
                    role
                )

                VALUES
                (
                    :username,
                    :email,
                    :password_hash,
                    'admin'
                )
                "
            );


        $stmt->execute(
            [
                'username' =>
                    $username,

                'email' =>
                    $email,

                'password_hash' =>
                    $passwordHash
            ]
        );


        return (int)$this->db
            ->lastInsertId();
    }


    public function findByUsername(
        string $username
    ): ?array
    {
        $stmt =
            $this->db->prepare(
                "
                SELECT *
                FROM users
                WHERE username = :username
                "
            );


        $stmt->execute(
            [
                'username' =>
                    $username
            ]
        );


        $user =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        return $user ?: null;
    }
}
