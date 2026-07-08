<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;

class SetupController extends Controller
{
    private AuthService $auth;


    public function __construct()
    {
        $this->auth = new AuthService();
    }


    public function index(): void
    {
        if ($this->auth->setupComplete()) {
            header('Location: /login');
            exit;
        }

        $this->render(
            'setup/setup.twig',
            [
                'title' => 'Initial Setup'
            ]
        );
    }


    public function create(): void
    {
        if ($this->auth->setupComplete()) {
            header('Location: /login');
            exit;
        }


        $username = $_POST['username'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm'] ?? '';


        if ($password !== $confirm) {
            die('Passwords do not match');
        }


        $this->auth->createAdmin(
            $username,
            $email,
            $password
        );


        header('Location: /login');
        exit;
    }
}
