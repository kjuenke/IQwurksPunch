<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserRepository;

class UserManagementService
{
    private const ALLOWED_ROLES = [
        'admin',
        'supervisor'
    ];

    private const MINIMUM_PASSWORD_LENGTH = 12;

    private UserRepository $users;


    public function __construct(
        UserRepository $users
    )
    {
        $this->users =
            $users;
    }


    /**
     * @return array<string,mixed>
     */
    public function list(
        int $actorUserId
    ): array
    {
        $authorization =
            $this->authorizeAdministrator(
                $actorUserId
            );


        if (!$authorization['success']) {

            return $authorization;
        }


        $activeAdministratorCount =
            $this->users
                ->countActiveAdministrators();

        $managedUsers = [];


        foreach ($this->users->all() as $user) {
            $managedUsers[] =
                $this->managementView(
                    $user,
                    $actorUserId,
                    $activeAdministratorCount
                );
        }


        return [
            'success' =>
                true,

            'users' =>
                $managedUsers,

            'active_administrator_count' =>
                $activeAdministratorCount,

            'errors' =>
                []
        ];
    }


    /**
     * @return array<string,mixed>
     */
    public function find(
        int $actorUserId,
        int $targetUserId
    ): array
    {
        $authorization =
            $this->authorizeAdministrator(
                $actorUserId
            );


        if (!$authorization['success']) {

            return $authorization;
        }


        $target =
            $this->users->findById(
                $targetUserId
            );


        if (!$target) {

            return $this->failure(
                'user',
                'User account not found.'
            );
        }


        return [
            'success' =>
                true,

            'user' =>
                $this->managementView(
                    $target,
                    $actorUserId,
                    $this->users
                        ->countActiveAdministrators()
                ),

            'errors' =>
                []
        ];
    }


    /**
     * @param array<string,mixed> $data
     *
     * @return array<string,mixed>
     */
    public function create(
        int $actorUserId,
        array $data
    ): array
    {
        $authorization =
            $this->authorizeAdministrator(
                $actorUserId
            );


        if (!$authorization['success']) {

            return $authorization;
        }


        $validation =
            $this->validateAccountDetails(
                $data
            );

        $passwordValidation =
            $this->validatePassword(
                (string)(
                    $data['password']
                    ??
                    ''
                ),
                (string)(
                    $data['confirm_password']
                    ??
                    ''
                )
            );

        $errors =
            array_merge(
                $validation['errors'],
                $passwordValidation
            );


        if ($errors !== []) {

            return [
                'success' =>
                    false,

                'errors' =>
                    $errors
            ];
        }


        $passwordHash =
            password_hash(
                (string)$data['password'],
                PASSWORD_DEFAULT
            );


        if (!is_string($passwordHash)) {

            return $this->failure(
                'password',
                'The password could not be secured.'
            );
        }


        $userId =
            $this->users->createManaged(
                [
                    'username' =>
                        $validation['username'],

                    'email' =>
                        $validation['email'],

                    'role' =>
                        $validation['role'],

                    'active' =>
                        isset(
                            $data['active']
                        )
                            ? (
                                (bool)$data['active']
                                    ? 1
                                    : 0
                            )
                            : 1,

                    'password_hash' =>
                        $passwordHash
                ]
            );


        return [
            'success' =>
                $userId > 0,

            'user_id' =>
                $userId,

            'errors' =>
                $userId > 0
                    ? []
                    : [
                        'user' =>
                            'Unable to create the user account.'
                    ]
        ];
    }


    /**
     * @param array<string,mixed> $data
     *
     * @return array<string,mixed>
     */
    public function update(
        int $actorUserId,
        int $targetUserId,
        array $data
    ): array
    {
        $authorization =
            $this->authorizeAdministrator(
                $actorUserId
            );


        if (!$authorization['success']) {

            return $authorization;
        }


        $target =
            $this->users->findById(
                $targetUserId
            );


        if (!$target) {

            return $this->failure(
                'user',
                'User account not found.'
            );
        }


        $validation =
            $this->validateAccountDetails(
                $data,
                $targetUserId
            );


        if ($validation['errors'] !== []) {

            return [
                'success' =>
                    false,

                'errors' =>
                    $validation['errors']
            ];
        }


        $currentRole =
            $this->normalizedRole(
                $target
            );

        $newRole =
            $validation['role'];


        if (
            $targetUserId === $actorUserId
            &&
            $currentRole === 'admin'
            &&
            $newRole !== 'admin'
        ) {
            return $this->failure(
                'role',
                'You cannot remove your own administrator role while signed in.'
            );
        }


        if (
            (int)(
                $target['active']
                ??
                0
            )
            ===
            1
            &&
            $currentRole === 'admin'
            &&
            $newRole !== 'admin'
            &&
            $this->users
                ->countActiveAdministrators()
            <=
            1
        ) {
            return $this->failure(
                'role',
                'The final active administrator cannot be changed to a supervisor.'
            );
        }


        $updated =
            $this->users->updateDetails(
                $targetUserId,
                [
                    'username' =>
                        $validation['username'],

                    'email' =>
                        $validation['email'],

                    'role' =>
                        $newRole
                ]
            );


        return [
            'success' =>
                $updated,

            'errors' =>
                $updated
                    ? []
                    : [
                        'user' =>
                            'Unable to update the user account.'
                    ]
        ];
    }


    /**
     * @return array<string,mixed>
     */
    public function resetPassword(
        int $actorUserId,
        int $targetUserId,
        string $password,
        string $confirmation
    ): array
    {
        $authorization =
            $this->authorizeAdministrator(
                $actorUserId
            );


        if (!$authorization['success']) {

            return $authorization;
        }


        if (
            !$this->users->findById(
                $targetUserId
            )
        ) {
            return $this->failure(
                'user',
                'User account not found.'
            );
        }


        $errors =
            $this->validatePassword(
                $password,
                $confirmation
            );


        if ($errors !== []) {

            return [
                'success' =>
                    false,

                'errors' =>
                    $errors
            ];
        }


        $passwordHash =
            password_hash(
                $password,
                PASSWORD_DEFAULT
            );


        if (!is_string($passwordHash)) {

            return $this->failure(
                'password',
                'The password could not be secured.'
            );
        }


        $updated =
            $this->users->updatePassword(
                $targetUserId,
                $passwordHash
            );


        return [
            'success' =>
                $updated,

            'errors' =>
                $updated
                    ? []
                    : [
                        'password' =>
                            'Unable to reset the account password.'
                    ]
        ];
    }


    /**
     * @return array<string,mixed>
     */
    public function activate(
        int $actorUserId,
        int $targetUserId
    ): array
    {
        $authorization =
            $this->authorizeAdministrator(
                $actorUserId
            );


        if (!$authorization['success']) {

            return $authorization;
        }


        $target =
            $this->users->findById(
                $targetUserId
            );


        if (!$target) {

            return $this->failure(
                'user',
                'User account not found.'
            );
        }


        if (
            (int)(
                $target['active']
                ??
                0
            )
            ===
            1
        ) {
            return [
                'success' =>
                    true,

                'changed' =>
                    false,

                'errors' =>
                    []
            ];
        }


        $updated =
            $this->users->activate(
                $targetUserId
            );


        return [
            'success' =>
                $updated,

            'changed' =>
                $updated,

            'errors' =>
                $updated
                    ? []
                    : [
                        'user' =>
                            'Unable to activate the user account.'
                    ]
        ];
    }


    /**
     * @return array<string,mixed>
     */
    public function deactivate(
        int $actorUserId,
        int $targetUserId
    ): array
    {
        $authorization =
            $this->authorizeAdministrator(
                $actorUserId
            );


        if (!$authorization['success']) {

            return $authorization;
        }


        $target =
            $this->users->findById(
                $targetUserId
            );


        if (!$target) {

            return $this->failure(
                'user',
                'User account not found.'
            );
        }


        if ($targetUserId === $actorUserId) {

            return $this->failure(
                'user',
                'You cannot deactivate your own account while signed in.'
            );
        }


        if (
            (int)(
                $target['active']
                ??
                0
            )
            !==
            1
        ) {
            return [
                'success' =>
                    true,

                'changed' =>
                    false,

                'errors' =>
                    []
            ];
        }


        if (
            $this->normalizedRole(
                $target
            )
            ===
            'admin'
            &&
            $this->users
                ->countActiveAdministrators()
            <=
            1
        ) {
            return $this->failure(
                'user',
                'The final active administrator cannot be deactivated.'
            );
        }


        $updated =
            $this->users->deactivate(
                $targetUserId
            );


        return [
            'success' =>
                $updated,

            'changed' =>
                $updated,

            'errors' =>
                $updated
                    ? []
                    : [
                        'user' =>
                            'Unable to deactivate the user account.'
                    ]
        ];
    }


    /**
     * @param array<string,mixed> $data
     *
     * @return array<string,mixed>
     */
    private function validateAccountDetails(
        array $data,
        ?int $excludeUserId = null
    ): array
    {
        $username =
            trim(
                (string)(
                    $data['username']
                    ??
                    ''
                )
            );

        $email =
            trim(
                (string)(
                    $data['email']
                    ??
                    ''
                )
            );

        $role =
            strtolower(
                trim(
                    (string)(
                        $data['role']
                        ??
                        'supervisor'
                    )
                )
            );

        $errors = [];


        if ($username === '') {
            $errors['username'] =
                'Username is required.';
        } elseif (
            strlen(
                $username
            )
            <
            3
            ||
            strlen(
                $username
            )
            >
            64
        ) {
            $errors['username'] =
                'Username must contain between 3 and 64 characters.';
        } elseif (
            !preg_match(
                '/^[A-Za-z0-9._-]+$/',
                $username
            )
        ) {
            $errors['username'] =
                'Username may contain letters, numbers, periods, underscores, and hyphens only.';
        } elseif (
            $this->users->usernameExists(
                $username,
                $excludeUserId
            )
        ) {
            $errors['username'] =
                'Username already exists.';
        }


        if ($email === '') {
            $errors['email'] =
                'Email address is required.';
        } elseif (
            strlen(
                $email
            )
            >
            254
            ||
            filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
            ===
            false
        ) {
            $errors['email'] =
                'Enter a valid email address.';
        }


        if (
            !in_array(
                $role,
                self::ALLOWED_ROLES,
                true
            )
        ) {
            $errors['role'] =
                'Role must be administrator or supervisor.';
        }


        return [
            'username' =>
                $username,

            'email' =>
                $email,

            'role' =>
                $role,

            'errors' =>
                $errors
        ];
    }


    /**
     * @return array<string,string>
     */
    private function validatePassword(
        string $password,
        string $confirmation
    ): array
    {
        $errors = [];


        if ($password === '') {
            $errors['password'] =
                'Password is required.';
        } elseif (
            strlen(
                $password
            )
            <
            self::MINIMUM_PASSWORD_LENGTH
        ) {
            $errors['password'] =
                'Password must contain at least 12 characters.';
        }


        if ($password !== $confirmation) {
            $errors['confirm_password'] =
                'Password confirmation does not match.';
        }


        return $errors;
    }


    /**
     * @return array<string,mixed>
     */
    private function authorizeAdministrator(
        int $actorUserId
    ): array
    {
        if ($actorUserId <= 0) {

            return $this->failure(
                'authorization',
                'An authenticated administrator account is required.'
            );
        }


        $actor =
            $this->users->findById(
                $actorUserId
            );


        if (
            !$actor
            ||
            (int)(
                $actor['active']
                ??
                0
            )
            !==
            1
            ||
            $this->normalizedRole(
                $actor
            )
            !==
            'admin'
        ) {
            return $this->failure(
                'authorization',
                'Only active administrators can manage user accounts.'
            );
        }


        return [
            'success' =>
                true,

            'user' =>
                $actor,

            'errors' =>
                []
        ];
    }


    /**
     * @param array<string,mixed> $user
     *
     * @return array<string,mixed>
     */
    private function managementView(
        array $user,
        int $actorUserId,
        int $activeAdministratorCount
    ): array
    {
        $id =
            (int)(
                $user['id']
                ??
                0
            );

        $active =
            (int)(
                $user['active']
                ??
                0
            )
            ===
            1;

        $role =
            $this->normalizedRole(
                $user
            );

        $isSelf =
            $id === $actorUserId;

        $isFinalActiveAdministrator =
            $active
            &&
            $role === 'admin'
            &&
            $activeAdministratorCount <= 1;


        return [
            'id' =>
                $id,

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
                $role,

            'active' =>
                $active,

            'created_at' =>
                $user['created_at']
                ??
                null,

            'last_login' =>
                $user['last_login']
                ??
                null,

            'is_self' =>
                $isSelf,

            'is_final_active_administrator' =>
                $isFinalActiveAdministrator,

            'can_activate' =>
                !$active,

            'can_deactivate' =>
                $active
                &&
                !$isSelf
                &&
                !$isFinalActiveAdministrator
        ];
    }


    /**
     * @param array<string,mixed> $user
     */
    private function normalizedRole(
        array $user
    ): string
    {
        return strtolower(
            trim(
                (string)(
                    $user['role']
                    ??
                    ''
                )
            )
        );
    }


    /**
     * @return array<string,mixed>
     */
    private function failure(
        string $key,
        string $message
    ): array
    {
        return [
            'success' =>
                false,

            'errors' => [
                $key =>
                    $message
            ]
        ];
    }
}
