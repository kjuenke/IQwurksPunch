<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

class UserRepository
{
    public function countUsers(): int
    {
        $db = Database::connection();

        return (int) $db
            ->query("SELECT COUNT(*) FROM users")
            ->fetchColumn();
    }


    public function create(
        string $username,
        string $email,
        string $passwordHash
    ): int {

        $db = Database::connection();

        $stmt = $db->prepare(
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

        $stmt->execute([
            'username' => $username,
            'email' => $email,
            'password_hash' => $passwordHash
        ]);

        return (int) $db->lastInsertId();
    }


    public function findByUsername(
        string $username
    ): ?array {

        $db = Database::connection();

        $stmt = $db->prepare(
            "
            SELECT *
            FROM users
            WHERE username = :username
            "
        );

        $stmt->execute([
            'username' => $username
        ]);

        $user = $stmt->fetch();

        return $user ?: null;
    }
}
