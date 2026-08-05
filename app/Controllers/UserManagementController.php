<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Container;
use App\Core\Flash;
use App\Services\AuditService;
use App\Services\UserManagementService;

class UserManagementController extends Controller
{
    private UserManagementService $users;

    private AuditService $audit;


    public function __construct()
    {
        $this->users =
            Container::userManagementService();

        $this->audit =
            Container::auditService();
    }


    public function index(): void
    {
        $actorUserId =
            $this->actorUserId();

        $result =
            $this->users->list(
                $actorUserId
            );


        if (!$result['success']) {
            Flash::error(
                $this->errorMessage(
                    $result,
                    'Only active administrators can manage user accounts.'
                )
            );


            $this->redirect(
                '/dashboard'
            );
        }


        $this->render(
            'users/index.twig',
            [
                'title' =>
                    'Supervisor Users',

                'activeMenu' =>
                    'settings',

                'users' =>
                    $result['users']
                    ??
                    [],

                'activeAdministratorCount' =>
                    $result['active_administrator_count']
                    ??
                    0
            ]
        );
    }


    public function create(): void
    {
        $authorization =
            $this->users->list(
                $this->actorUserId()
            );


        if (!$authorization['success']) {
            Flash::error(
                $this->errorMessage(
                    $authorization,
                    'Only active administrators can create user accounts.'
                )
            );


            $this->redirect(
                '/dashboard'
            );
        }


        $this->render(
            'users/create.twig',
            [
                'title' =>
                    'Create Supervisor User',

                'activeMenu' =>
                    'settings',

                'errors' =>
                    [],

                'form' =>
                    $this->defaultForm()
            ]
        );
    }


    public function store(): void
    {
        $actorUserId =
            $this->actorUserId();

        $result =
            $this->users->create(
                $actorUserId,
                $_POST
            );


        if (!$result['success']) {
            $errors =
                $result['errors']
                ??
                [];


            if (
                isset(
                    $errors['authorization']
                )
            ) {
                Flash::error(
                    $this->errorMessage(
                        $result,
                        'Only active administrators can create user accounts.'
                    )
                );


                $this->redirect(
                    '/dashboard'
                );
            }


            $this->render(
                'users/create.twig',
                [
                    'title' =>
                        'Create Supervisor User',

                    'activeMenu' =>
                        'settings',

                    'errors' =>
                        is_array(
                            $errors
                        )
                            ? $errors
                            : [],

                    'form' =>
                        $this->postedForm()
                ]
            );


            return;
        }


        $userId =
            (int)(
                $result['user_id']
                ??
                0
            );

        $username =
            trim(
                (string)(
                    $_POST['username']
                    ??
                    ''
                )
            );

        $role =
            strtolower(
                trim(
                    (string)(
                        $_POST['role']
                        ??
                        'supervisor'
                    )
                )
            );

        $active =
            isset(
                $_POST['active']
            );


        $this->audit->log(
            'user.created',
            'Created '
            .
            (
                $active
                    ? 'active '
                    : 'inactive '
            )
            .
            $role
            .
            ' user ID '
            .
            $userId
            .
            ' ('
            .
            $username
            .
            ').',
            $actorUserId
        );


        Flash::success(
            $role === 'admin'
                ? 'Administrator account created successfully.'
                : 'Supervisor account created successfully.'
        );


        $this->redirect(
            '/admin/users'
        );
    }


    private function actorUserId(): int
    {
        return (int)(
            $_SESSION['user_id']
            ??
            0
        );
    }


    /**
     * @return array<string,mixed>
     */
    private function defaultForm(): array
    {
        return [
            'username' =>
                '',

            'email' =>
                '',

            'role' =>
                'supervisor',

            'active' =>
                true
        ];
    }


    /**
     * @return array<string,mixed>
     */
    private function postedForm(): array
    {
        return [
            'username' =>
                trim(
                    (string)(
                        $_POST['username']
                        ??
                        ''
                    )
                ),

            'email' =>
                trim(
                    (string)(
                        $_POST['email']
                        ??
                        ''
                    )
                ),

            'role' =>
                strtolower(
                    trim(
                        (string)(
                            $_POST['role']
                            ??
                            'supervisor'
                        )
                    )
                ),

            'active' =>
                isset(
                    $_POST['active']
                )
        ];
    }


    /**
     * @param array<string,mixed> $result
     */
    private function errorMessage(
        array $result,
        string $default
    ): string
    {
        $errors =
            $result['errors']
            ??
            [];


        if (!is_array($errors)) {

            return $default;
        }


        foreach ($errors as $error) {
            if (
                is_string(
                    $error
                )
                &&
                $error !== ''
            ) {
                return $error;
            }
        }


        return $default;
    }


    private function redirect(
        string $location
    ): never
    {
        header(
            'Location: '
            .
            $location
        );


        exit;
    }
}
