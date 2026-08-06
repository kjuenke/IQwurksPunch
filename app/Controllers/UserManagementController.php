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
        $result =
            $this->users->list(
                $this->actorUserId()
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


    public function activity(): void
    {
        $actorUserId =
            $this->actorUserId();

        $authorization =
            $this->users->list(
                $actorUserId
            );


        if (!$authorization['success']) {
            Flash::error(
                $this->errorMessage(
                    $authorization,
                    'Only active administrators can review user-management activity.'
                )
            );


            $this->redirect(
                '/dashboard'
            );
        }


        $this->render(
            'users/activity.twig',
            [
                'title' =>
                    'User-Management Activity',

                'activeMenu' =>
                    'settings',

                'actions' =>
                    $this->audit
                        ->recentUserManagementActions(
                            100
                        )
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
            if (
                $this->hasAuthorizationError(
                    $result
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


            $this->audit->log(
                'user.create_blocked',
                'Blocked creation of '
                .
                $this->auditValue(
                    (string)(
                        $_POST['role']
                        ??
                        'supervisor'
                    )
                )
                .
                ' account with username '
                .
                $this->auditValue(
                    (string)(
                        $_POST['username']
                        ??
                        ''
                    )
                )
                .
                '. Reason: '
                .
                $this->errorMessage(
                    $result,
                    'Account validation failed.'
                ),
                $actorUserId
            );


            $this->render(
                'users/create.twig',
                [
                    'title' =>
                        'Create Supervisor User',

                    'activeMenu' =>
                        'settings',

                    'errors' =>
                        $this->resultErrors(
                            $result
                        ),

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
            $this->auditValue(
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


    public function edit(
        int|string $id
    ): void
    {
        $userId =
            $this->routeUserId(
                $id
            );


        if ($userId <= 0) {
            Flash::error(
                'Invalid user account.'
            );


            $this->redirect(
                '/admin/users'
            );
        }


        $result =
            $this->users->find(
                $this->actorUserId(),
                $userId
            );


        if (!$result['success']) {
            $this->handleFindFailure(
                $result
            );
        }


        $user =
            $result['user'];


        $this->render(
            'users/edit.twig',
            [
                'title' =>
                    'Edit User',

                'activeMenu' =>
                    'settings',

                'user' =>
                    $user,

                'form' =>
                    $this->formFromUser(
                        $user
                    ),

                'errors' =>
                    []
            ]
        );
    }


    public function update(
        int|string $id
    ): void
    {
        $actorUserId =
            $this->actorUserId();

        $userId =
            $this->routeUserId(
                $id
            );


        if ($userId <= 0) {
            Flash::error(
                'Invalid user account.'
            );


            $this->redirect(
                '/admin/users'
            );
        }


        $targetResult =
            $this->users->find(
                $actorUserId,
                $userId
            );


        if (!$targetResult['success']) {
            $this->handleFindFailure(
                $targetResult
            );
        }


        $target =
            $targetResult['user'];

        $result =
            $this->users->update(
                $actorUserId,
                $userId,
                $_POST
            );


        if (!$result['success']) {
            if (
                $this->hasAuthorizationError(
                    $result
                )
            ) {
                Flash::error(
                    $this->errorMessage(
                        $result,
                        'Only active administrators can update user accounts.'
                    )
                );


                $this->redirect(
                    '/dashboard'
                );
            }


            $this->audit->log(
                'user.update_blocked',
                'Blocked update of user ID '
                .
                $userId
                .
                ' ('
                .
                $this->auditValue(
                    (string)$target['username']
                )
                .
                '). Reason: '
                .
                $this->errorMessage(
                    $result,
                    'Account validation failed.'
                ),
                $actorUserId
            );


            $this->render(
                'users/edit.twig',
                [
                    'title' =>
                        'Edit User',

                    'activeMenu' =>
                        'settings',

                    'user' =>
                        $target,

                    'form' =>
                        $this->postedForm(),

                    'errors' =>
                        $this->resultErrors(
                            $result
                        )
                ]
            );


            return;
        }


        $newUsername =
            $this->auditValue(
                (string)(
                    $_POST['username']
                    ??
                    ''
                )
            );

        $newRole =
            strtolower(
                trim(
                    (string)(
                        $_POST['role']
                        ??
                        'supervisor'
                    )
                )
            );


        $this->audit->log(
            'user.updated',
            'Updated user ID '
            .
            $userId
            .
            ' from username '
            .
            $this->auditValue(
                (string)$target['username']
            )
            .
            ' to '
            .
            $newUsername
            .
            ' with role '
            .
            $newRole
            .
            '.',
            $actorUserId
        );


        Flash::success(
            'User account updated successfully.'
        );


        $this->redirect(
            '/admin/users/edit/'
            .
            $userId
        );
    }


    public function password(
        int|string $id
    ): void
    {
        $userId =
            $this->routeUserId(
                $id
            );


        if ($userId <= 0) {
            Flash::error(
                'Invalid user account.'
            );


            $this->redirect(
                '/admin/users'
            );
        }


        $result =
            $this->users->find(
                $this->actorUserId(),
                $userId
            );


        if (!$result['success']) {
            $this->handleFindFailure(
                $result
            );
        }


        $this->render(
            'users/password.twig',
            [
                'title' =>
                    'Reset User Password',

                'activeMenu' =>
                    'settings',

                'user' =>
                    $result['user'],

                'errors' =>
                    []
            ]
        );
    }


    public function resetPassword(
        int|string $id
    ): void
    {
        $actorUserId =
            $this->actorUserId();

        $userId =
            $this->routeUserId(
                $id
            );


        if ($userId <= 0) {
            Flash::error(
                'Invalid user account.'
            );


            $this->redirect(
                '/admin/users'
            );
        }


        $targetResult =
            $this->users->find(
                $actorUserId,
                $userId
            );


        if (!$targetResult['success']) {
            $this->handleFindFailure(
                $targetResult
            );
        }


        $target =
            $targetResult['user'];

        $result =
            $this->users->resetPassword(
                $actorUserId,
                $userId,
                (string)(
                    $_POST['password']
                    ??
                    ''
                ),
                (string)(
                    $_POST['confirm_password']
                    ??
                    ''
                )
            );


        if (!$result['success']) {
            if (
                $this->hasAuthorizationError(
                    $result
                )
            ) {
                Flash::error(
                    $this->errorMessage(
                        $result,
                        'Only active administrators can reset passwords.'
                    )
                );


                $this->redirect(
                    '/dashboard'
                );
            }


            $this->audit->log(
                'user.password_reset_blocked',
                'Blocked password reset for user ID '
                .
                $userId
                .
                ' ('
                .
                $this->auditValue(
                    (string)$target['username']
                )
                .
                '). Reason: '
                .
                $this->errorMessage(
                    $result,
                    'Password validation failed.'
                ),
                $actorUserId
            );


            $this->render(
                'users/password.twig',
                [
                    'title' =>
                        'Reset User Password',

                    'activeMenu' =>
                        'settings',

                    'user' =>
                        $target,

                    'errors' =>
                        $this->resultErrors(
                            $result
                        )
                ]
            );


            return;
        }


        $this->audit->log(
            'user.password_reset',
            'Reset password for user ID '
            .
            $userId
            .
            ' ('
            .
            $this->auditValue(
                (string)$target['username']
            )
            .
            ').',
            $actorUserId
        );


        Flash::success(
            'User password reset successfully.'
        );


        $this->redirect(
            '/admin/users'
        );
    }


    public function activate(): void
    {
        $this->changeAccountStatus(
            true
        );
    }


    public function deactivate(): void
    {
        $this->changeAccountStatus(
            false
        );
    }


    private function changeAccountStatus(
        bool $activate
    ): never
    {
        $actorUserId =
            $this->actorUserId();

        $userId =
            $this->postedUserId();


        if ($userId <= 0) {
            Flash::error(
                'Invalid user account.'
            );


            $this->redirect(
                '/admin/users'
            );
        }


        $targetResult =
            $this->users->find(
                $actorUserId,
                $userId
            );


        if (!$targetResult['success']) {
            $this->handleFindFailure(
                $targetResult
            );
        }


        $target =
            $targetResult['user'];

        $result =
            $activate
                ? $this->users->activate(
                    $actorUserId,
                    $userId
                )
                : $this->users->deactivate(
                    $actorUserId,
                    $userId
                );


        if (!$result['success']) {
            if (
                $this->hasAuthorizationError(
                    $result
                )
            ) {
                Flash::error(
                    $this->errorMessage(
                        $result,
                        'Only active administrators can change account status.'
                    )
                );


                $this->redirect(
                    '/dashboard'
                );
            }


            $action =
                $activate
                    ? 'activation'
                    : 'deactivation';


            $this->audit->log(
                'user.'
                .
                $action
                .
                '_blocked',
                'Blocked '
                .
                $action
                .
                ' of user ID '
                .
                $userId
                .
                ' ('
                .
                $this->auditValue(
                    (string)$target['username']
                )
                .
                '). Reason: '
                .
                $this->errorMessage(
                    $result,
                    'Account status change was rejected.'
                ),
                $actorUserId
            );


            Flash::error(
                $this->errorMessage(
                    $result,
                    'Unable to change the account status.'
                )
            );


            $this->redirect(
                '/admin/users'
            );
        }


        $changed =
            (bool)(
                $result['changed']
                ??
                false
            );


        if ($changed) {
            $this->audit->log(
                $activate
                    ? 'user.activated'
                    : 'user.deactivated',
                (
                    $activate
                        ? 'Activated'
                        : 'Deactivated'
                )
                .
                ' user ID '
                .
                $userId
                .
                ' ('
                .
                $this->auditValue(
                    (string)$target['username']
                )
                .
                ').',
                $actorUserId
            );


            Flash::success(
                $activate
                    ? 'User account activated successfully.'
                    : 'User account deactivated successfully.'
            );
        } else {
            Flash::success(
                $activate
                    ? 'The user account was already active.'
                    : 'The user account was already inactive.'
            );
        }


        $this->redirect(
            '/admin/users'
        );
    }


    private function handleFindFailure(
        array $result
    ): never
    {
        if (
            $this->hasAuthorizationError(
                $result
            )
        ) {
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


        Flash::error(
            $this->errorMessage(
                $result,
                'User account not found.'
            )
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


    private function postedUserId(): int
    {
        return (int)(
            $_POST['id']
            ??
            0
        );
    }


    private function routeUserId(
        int|string $id
    ): int
    {
        return (int)$id;
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
     * @param array<string,mixed> $user
     *
     * @return array<string,mixed>
     */
    private function formFromUser(
        array $user
    ): array
    {
        return [
            'username' =>
                (string)(
                    $user['username']
                    ??
                    ''
                ),

            'email' =>
                (string)(
                    $user['email']
                    ??
                    ''
                ),

            'role' =>
                (string)(
                    $user['role']
                    ??
                    'supervisor'
                )
        ];
    }


    /**
     * @param array<string,mixed> $result
     *
     * @return array<string,mixed>
     */
    private function resultErrors(
        array $result
    ): array
    {
        $errors =
            $result['errors']
            ??
            [];


        return is_array(
            $errors
        )
            ? $errors
            : [];
    }


    /**
     * @param array<string,mixed> $result
     */
    private function hasAuthorizationError(
        array $result
    ): bool
    {
        $errors =
            $result['errors']
            ??
            [];


        return
            is_array(
                $errors
            )
            &&
            isset(
                $errors['authorization']
            );
    }


    /**
     * @param array<string,mixed> $result
     */
    private function errorMessage(
        array $result,
        string $default
    ): string
    {
        foreach (
            $this->resultErrors(
                $result
            )
            as $error
        ) {
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


    private function auditValue(
        string $value
    ): string
    {
        $cleaned =
            preg_replace(
                '/[\x00-\x1F\x7F]+/',
                ' ',
                trim(
                    $value
                )
            );


        if (!is_string($cleaned)) {
            return '';
        }


        return substr(
            $cleaned,
            0,
            120
        );
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
