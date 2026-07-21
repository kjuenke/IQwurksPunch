<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Flash;
use App\Repositories\UserRepository;

final class AuthGuardService
{
    private const PUBLIC_PATHS = [
        '/',
        '/login',
        '/logout',
        '/setup',
        '/kiosk',
        '/kiosk/authenticate',
        '/kiosk/verify-pin',
        '/kiosk/punch'
    ];


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


    public function enforce(
        string $requestUri,
        string $requestMethod
    ): void
    {
        $path =
            parse_url(
                $requestUri,
                PHP_URL_PATH
            );


        $path =
            is_string(
                $path
            )
                ? $this->normalizePath(
                    $path
                )
                : '/';


        if (
            in_array(
                $path,
                self::PUBLIC_PATHS,
                true
            )
        ) {
            return;
        }


        $userId =
            (int)(
                $_SESSION['user_id']
                ??
                0
            );


        if ($userId <= 0) {

            $this->redirectToLogin(
                $requestUri,
                $requestMethod,
                'Please log in as a supervisor.'
            );
        }


        $user =
            $this->users->findById(
                $userId
            );


        if (!$user) {

            $this->clearSupervisorSession();


            $this->redirectToLogin(
                $requestUri,
                $requestMethod,
                'Your supervisor account could not be found. Please log in again.'
            );
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
            $this->clearSupervisorSession();


            $this->redirectToLogin(
                $requestUri,
                $requestMethod,
                'Your supervisor account is inactive.'
            );
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
            $this->clearSupervisorSession();


            $this->redirectToLogin(
                $requestUri,
                $requestMethod,
                'Your account is not authorized to access supervisor pages.'
            );
        }


        $_SESSION['user_id'] =
            (int)$user['id'];


        $_SESSION['username'] =
            (string)(
                $user['username']
                ??
                ''
            );


        $_SESSION['user_role'] =
            $role;


        $_SESSION['last_activity'] =
            time();
    }


    private function normalizePath(
        string $path
    ): string
    {
        if ($path === '') {

            return '/';
        }


        if ($path !== '/') {

            $path =
                rtrim(
                    $path,
                    '/'
                );
        }


        return
            $path === ''
                ? '/'
                : $path;
    }


    private function redirectToLogin(
        string $requestUri,
        string $requestMethod,
        string $message
    ): never
    {
        if (
            strtoupper(
                $requestMethod
            )
            ===
            'GET'
            &&
            $this->isSafeInternalUrl(
                $requestUri
            )
        ) {
            $_SESSION['intended_url'] =
                $requestUri;
        }


        Flash::error(
            $message
        );


        header(
            'Location: /login'
        );


        exit;
    }


    private function isSafeInternalUrl(
        string $url
    ): bool
    {
        return
            str_starts_with(
                $url,
                '/'
            )
            &&
            !str_starts_with(
                $url,
                '//'
            );
    }


    private function clearSupervisorSession(): void
    {
        unset(
            $_SESSION['user_id'],
            $_SESSION['username'],
            $_SESSION['user_role'],
            $_SESSION['last_activity']
        );
    }
}
