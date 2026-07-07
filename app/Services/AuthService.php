<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserRepository;

class AuthService
{
    private UserRepository $users;


    public function __construct()
    {
        $this->users = new UserRepository();
    }


    public function setupComplete(): bool
    {
        return $this->users->countUsers() > 0;
    }


    public function createAdmin(
        string $username,
        string $email,
        string $password
    ): int {

        $hash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        return $this->users->create(
            $username,
            $email,
            $hash
        );
    }


    public function attemptLogin(
        string $username,
        string $password
    ): bool {

        $user = $this->users
            ->findByUsername($username);

        if (!$user) {
            return false;
        }

        if (!password_verify(
            $password,
            $user['password_hash']
        )) {
            return false;
        }

        $_SESSION['user_id'] = $user['id'];

        return true;
    }


    public function logout(): void
    {
        unset($_SESSION['user_id']);
    }
}
