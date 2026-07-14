<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\Controller;
use App\Services\AuthService;
use App\Core\Container;

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
        $this->render(
            'auth/login.twig',
            [
                'title' => 'Supervisor Login'
            ]
        );
    }


    public function authenticate(): void
    {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';


        if ($this->auth->attemptLogin(
            $username,
            $password
        )) {

            header('Location: /dashboard');
            exit;

        }


        die('Invalid username or password');
    }


    public function logout(): void
    {
        $this->auth->logout();

        header('Location: /login');
        exit;
    }
}
