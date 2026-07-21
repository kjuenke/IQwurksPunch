<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Container;
use App\Core\Flash;
use App\Services\AuthService;

class AuthController extends Controller
{
    private AuthService $auth;


    public function __construct()
    {
        $this->auth =
            Container::authService();
    }


    public function login(): void
    {
        if (
            (int)(
                $_SESSION['user_id']
                ??
                0
            )
            >
            0
        ) {
            header(
                'Location: /dashboard'
            );


            exit;
        }


        $this->render(
            'auth/login.twig',
            [
                'title' =>
                    'Supervisor Login'
            ]
        );
    }


    public function authenticate(): void
    {
        $username =
            trim(
                (string)(
                    $_POST['username']
                    ??
                    ''
                )
            );


        $password =
            (string)(
                $_POST['password']
                ??
                ''
            );


        if (
            !$this->auth->attemptLogin(
                $username,
                $password
            )
        ) {
            Flash::error(
                'Invalid username or password, or the account is inactive.'
            );


            header(
                'Location: /login'
            );


            exit;
        }


        $destination =
            (string)(
                $_SESSION['intended_url']
                ??
                '/dashboard'
            );


        unset(
            $_SESSION['intended_url']
        );


        if (
            !$this->isSafeDestination(
                $destination
            )
        ) {
            $destination =
                '/dashboard';
        }


        header(
            'Location: '
            .
            $destination
        );


        exit;
    }


    public function logout(): void
    {
        $this->auth->logout();


        header(
            'Location: /login'
        );


        exit;
    }


    private function isSafeDestination(
        string $destination
    ): bool
    {
        if (
            !str_starts_with(
                $destination,
                '/'
            )
            ||
            str_starts_with(
                $destination,
                '//'
            )
        ) {
            return false;
        }


        $path =
            parse_url(
                $destination,
                PHP_URL_PATH
            );


        if (!is_string($path)) {

            return false;
        }


        return
            !in_array(
                $path,
                [
                    '/login',
                    '/logout',
                    '/setup'
                ],
                true
            );
    }
}
