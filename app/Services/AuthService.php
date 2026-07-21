<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserRepository;

class AuthService
{
    private const AUTHORIZED_ROLES = [
        'admin',
        'supervisor'
    ];


    private UserRepository $users;


    public function __construct(
        UserRepository $users
    )
    {
        $this->users =
            $users;
    }


    public function setupComplete(): bool
    {
        return
            $this->users->countUsers()
            >
            0;
    }


    public function createAdmin(
        string $username,
        string $email,
        string $password
    ): int
    {
        $hash =
            password_hash(
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
    ): bool
    {
        $username =
            trim(
                $username
            );


        if (
            $username === ''
            ||
            $password === ''
        ) {
            return false;
        }


        $user =
            $this->users->findByUsername(
                $username
            );


        if (!$user) {

            return false;
        }


        if (
            (int)(
                $user['active']
                ??
                0
            )
            !==
            1
        ) {
            return false;
        }


        $role =
            strtolower(
                trim(
                    (string)(
                        $user['role']
                        ??
                        ''
                    )
                )
            );


        if (
            !in_array(
                $role,
                self::AUTHORIZED_ROLES,
                true
            )
        ) {
            return false;
        }


        if (
            !password_verify(
                $password,
                (string)$user['password_hash']
            )
        ) {
            return false;
        }


        session_regenerate_id(
            true
        );


        $_SESSION['user_id'] =
            (int)$user['id'];


        $_SESSION['username'] =
            (string)$user['username'];


        $_SESSION['user_role'] =
            $role;


        $_SESSION['authenticated_at'] =
            time();


        $_SESSION['last_activity'] =
            time();


        $this->users->updateLastLogin(
            (int)$user['id']
        );


        return true;
    }


    public function logout(): void
    {
        $_SESSION = [];


        if (
            ini_get(
                'session.use_cookies'
            )
        ) {
            $parameters =
                session_get_cookie_params();


            setcookie(
                session_name(),
                '',
                [
                    'expires' =>
                        time()
                        -
                        42000,

                    'path' =>
                        $parameters['path']
                        ??
                        '/',

                    'domain' =>
                        $parameters['domain']
                        ??
                        '',

                    'secure' =>
                        (bool)(
                            $parameters['secure']
                            ??
                            false
                        ),

                    'httponly' =>
                        (bool)(
                            $parameters['httponly']
                            ??
                            true
                        ),

                    'samesite' =>
                        $parameters['samesite']
                        ??
                        'Lax'
                ]
            );
        }


        if (
            session_status()
            ===
            PHP_SESSION_ACTIVE
        ) {
            session_destroy();
        }
    }
}
