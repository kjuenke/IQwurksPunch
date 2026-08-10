<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Flash;
use App\Repositories\UserRepository;
use RuntimeException;

final class AuthGuardService
{
    public const SESSION_IDLE_TIMEOUT_SECONDS =
        28800;


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


        $this->requireAuthorizedUserId(
            $requestUri,
            $requestMethod
        );
    }


    public function requireAuthorizedUserId(
        string $requestUri,
        string $requestMethod
    ): int
    {
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


        try {

            $this->requireActiveSession();


            $user =
                $this->authorizedUserForId(
                    $userId
                );

        } catch (RuntimeException $exception) {

            $this->clearSupervisorSession();


            $this->redirectToLogin(
                $requestUri,
                $requestMethod,
                $exception->getMessage()
            );
        }


        $role =
            (string)$user['role'];


        $this->synchronizeSession(
            $user,
            $role
        );


        return (int)$user['id'];
    }


    /**
     * Validate an account without redirecting or terminating execution.
     *
     * @return array<string,mixed>
     */
    public function authorizedUserForId(
        int $userId
    ): array
    {
        if ($userId <= 0) {

            throw new RuntimeException(
                'No authenticated supervisor account was provided.'
            );
        }


        $user =
            $this->users
                ->findById(
                    $userId
                );


        if (!$user) {

            throw new RuntimeException(
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

            throw new RuntimeException(
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

            throw new RuntimeException(
                'Your account is not authorized to access supervisor pages.'
            );
        }


        $verifiedUserId =
            (int)(
                $user['id']
                ??
                0
            );


        if ($verifiedUserId <= 0) {

            throw new RuntimeException(
                'Your supervisor account could not be verified. Please log in again.'
            );
        }


        $user['id'] =
            $verifiedUserId;


        $user['role'] =
            $role;


        return $user;
    }


    public function requireActiveSession(
        ?int $currentTime = null
    ): void
    {
        $lastActivity =
            (int)(
                $_SESSION['last_activity']
                ??
                0
            );


        $now =
            $currentTime
            ??
            time();


        if (
            $lastActivity <= 0
            ||
            $now < $lastActivity
            ||
            (
                $now
                -
                $lastActivity
            )
            >=
            self::SESSION_IDLE_TIMEOUT_SECONDS
        ) {
            throw new RuntimeException(
                'Your supervisor session has expired. Please log in again.'
            );
        }
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


    /**
     * @param array<string,mixed> $user
     */
    private function synchronizeSession(
        array $user,
        string $role
    ): void
    {
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
            $_SESSION['authenticated_at'],
            $_SESSION['last_activity']
        );
    }
}
