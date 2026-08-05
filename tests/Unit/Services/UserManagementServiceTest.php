<?php
declare(strict_types=1);

use App\Repositories\UserRepository;
use App\Services\UserManagementService;
use PHPUnit\Framework\TestCase;

final class UserManagementServiceTest extends TestCase
{
    private PDO $db;

    private UserRepository $repository;

    private UserManagementService $service;


    protected function setUp(): void
    {
        parent::setUp();


        $this->db =
            new PDO(
                'sqlite::memory:'
            );


        $this->db->setAttribute(
            PDO::ATTR_ERRMODE,
            PDO::ERRMODE_EXCEPTION
        );

        $this->db->setAttribute(
            PDO::ATTR_DEFAULT_FETCH_MODE,
            PDO::FETCH_ASSOC
        );


        $this->createUsersTable();


        $this->repository =
            new UserRepository(
                $this->db
            );

        $this->service =
            new UserManagementService(
                $this->repository
            );
    }


    public function testActiveAdministratorCanListUsers(): void
    {
        $administratorId =
            $this->createUser(
                'administrator',
                'admin',
                true
            );

        $this->createUser(
            'supervisor',
            'supervisor',
            true
        );


        $result =
            $this->service->list(
                $administratorId
            );


        self::assertTrue(
            $result['success']
        );

        self::assertCount(
            2,
            $result['users']
        );

        self::assertSame(
            1,
            $result['active_administrator_count']
        );

        self::assertArrayNotHasKey(
            'password_hash',
            $result['users'][0]
        );
    }


    public function testSupervisorCannotListUsers(): void
    {
        $supervisorId =
            $this->createUser(
                'supervisor',
                'supervisor',
                true
            );


        $result =
            $this->service->list(
                $supervisorId
            );


        self::assertFalse(
            $result['success']
        );

        self::assertSame(
            'Only active administrators can manage user accounts.',
            $result['errors']['authorization']
        );
    }


    public function testInactiveAdministratorCannotManageUsers(): void
    {
        $administratorId =
            $this->createUser(
                'inactive-admin',
                'admin',
                false
            );


        $result =
            $this->service->create(
                $administratorId,
                $this->validCreateData()
            );


        self::assertFalse(
            $result['success']
        );

        self::assertArrayHasKey(
            'authorization',
            $result['errors']
        );
    }


    public function testAdministratorCanCreateActiveSupervisor(): void
    {
        $administratorId =
            $this->createUser(
                'administrator',
                'admin',
                true
            );


        $result =
            $this->service->create(
                $administratorId,
                $this->validCreateData()
            );


        self::assertTrue(
            $result['success']
        );

        self::assertGreaterThan(
            0,
            $result['user_id']
        );


        $user =
            $this->repository->findById(
                $result['user_id']
            );


        self::assertNotNull(
            $user
        );

        self::assertSame(
            'new-supervisor',
            $user['username']
        );

        self::assertSame(
            'supervisor@example.test',
            $user['email']
        );

        self::assertSame(
            'supervisor',
            $user['role']
        );

        self::assertSame(
            1,
            (int)$user['active']
        );

        self::assertTrue(
            password_verify(
                'TestingPassword!123',
                $user['password_hash']
            )
        );
    }


    public function testCreateRejectsDuplicateUsernameCaseInsensitively(): void
    {
        $administratorId =
            $this->createUser(
                'administrator',
                'admin',
                true
            );

        $this->createUser(
            'ExistingSupervisor',
            'supervisor',
            true
        );

        $data =
            $this->validCreateData();

        $data['username'] =
            'existingsupervisor';


        $result =
            $this->service->create(
                $administratorId,
                $data
            );


        self::assertFalse(
            $result['success']
        );

        self::assertSame(
            'Username already exists.',
            $result['errors']['username']
        );
    }


    public function testCreateValidatesAccountAndPasswordFields(): void
    {
        $administratorId =
            $this->createUser(
                'administrator',
                'admin',
                true
            );


        $result =
            $this->service->create(
                $administratorId,
                [
                    'username' =>
                        'invalid username',

                    'email' =>
                        'not-an-email',

                    'role' =>
                        'owner',

                    'password' =>
                        'short',

                    'confirm_password' =>
                        'different'
                ]
            );


        self::assertFalse(
            $result['success']
        );

        self::assertArrayHasKey(
            'username',
            $result['errors']
        );

        self::assertArrayHasKey(
            'email',
            $result['errors']
        );

        self::assertArrayHasKey(
            'role',
            $result['errors']
        );

        self::assertArrayHasKey(
            'password',
            $result['errors']
        );

        self::assertArrayHasKey(
            'confirm_password',
            $result['errors']
        );
    }


    public function testAdministratorCanUpdateSupervisorDetailsAndRole(): void
    {
        $administratorId =
            $this->createUser(
                'administrator',
                'admin',
                true
            );

        $supervisorId =
            $this->createUser(
                'old-supervisor',
                'supervisor',
                true
            );


        $result =
            $this->service->update(
                $administratorId,
                $supervisorId,
                [
                    'username' =>
                        'promoted-user',

                    'email' =>
                        'promoted@example.test',

                    'role' =>
                        'admin'
                ]
            );


        self::assertTrue(
            $result['success']
        );


        $updated =
            $this->repository->findById(
                $supervisorId
            );


        self::assertSame(
            'promoted-user',
            $updated['username']
        );

        self::assertSame(
            'promoted@example.test',
            $updated['email']
        );

        self::assertSame(
            'admin',
            $updated['role']
        );
    }


    public function testAdministratorCannotDowngradeOwnRole(): void
    {
        $administratorId =
            $this->createUser(
                'administrator',
                'admin',
                true
            );

        $this->createUser(
            'second-admin',
            'admin',
            true
        );


        $result =
            $this->service->update(
                $administratorId,
                $administratorId,
                [
                    'username' =>
                        'administrator',

                    'email' =>
                        'administrator@example.test',

                    'role' =>
                        'supervisor'
                ]
            );


        self::assertFalse(
            $result['success']
        );

        self::assertSame(
            'You cannot remove your own administrator role while signed in.',
            $result['errors']['role']
        );
    }


    public function testFinalActiveAdministratorCannotBeDowngraded(): void
    {
        $administratorId =
            $this->createUser(
                'administrator',
                'admin',
                true
            );

        $inactiveAdministratorId =
            $this->createUser(
                'inactive-admin',
                'admin',
                false
            );


        $result =
            $this->service->update(
                $administratorId,
                $inactiveAdministratorId,
                [
                    'username' =>
                        'inactive-admin',

                    'email' =>
                        'inactive-admin@example.test',

                    'role' =>
                        'supervisor'
                ]
            );


        self::assertTrue(
            $result['success']
        );


        $secondResult =
            $this->service->update(
                $administratorId,
                $administratorId,
                [
                    'username' =>
                        'administrator',

                    'email' =>
                        'administrator@example.test',

                    'role' =>
                        'supervisor'
                ]
            );


        self::assertFalse(
            $secondResult['success']
        );
    }


    public function testAdministratorCanResetPassword(): void
    {
        $administratorId =
            $this->createUser(
                'administrator',
                'admin',
                true
            );

        $supervisorId =
            $this->createUser(
                'supervisor',
                'supervisor',
                true
            );


        $result =
            $this->service->resetPassword(
                $administratorId,
                $supervisorId,
                'ReplacementPassword!456',
                'ReplacementPassword!456'
            );


        self::assertTrue(
            $result['success']
        );


        $updated =
            $this->repository->findById(
                $supervisorId
            );


        self::assertTrue(
            password_verify(
                'ReplacementPassword!456',
                $updated['password_hash']
            )
        );
    }


    public function testPasswordResetRejectsShortOrMismatchedPassword(): void
    {
        $administratorId =
            $this->createUser(
                'administrator',
                'admin',
                true
            );

        $supervisorId =
            $this->createUser(
                'supervisor',
                'supervisor',
                true
            );


        $result =
            $this->service->resetPassword(
                $administratorId,
                $supervisorId,
                'short',
                'different'
            );


        self::assertFalse(
            $result['success']
        );

        self::assertArrayHasKey(
            'password',
            $result['errors']
        );

        self::assertArrayHasKey(
            'confirm_password',
            $result['errors']
        );
    }


    public function testAdministratorCanDeactivateSupervisor(): void
    {
        $administratorId =
            $this->createUser(
                'administrator',
                'admin',
                true
            );

        $supervisorId =
            $this->createUser(
                'supervisor',
                'supervisor',
                true
            );


        $result =
            $this->service->deactivate(
                $administratorId,
                $supervisorId
            );


        self::assertTrue(
            $result['success']
        );

        self::assertTrue(
            $result['changed']
        );

        self::assertSame(
            0,
            (int)$this->repository
                ->findById(
                    $supervisorId
                )['active']
        );
    }


    public function testAdministratorCannotDeactivateSelf(): void
    {
        $administratorId =
            $this->createUser(
                'administrator',
                'admin',
                true
            );

        $this->createUser(
            'second-admin',
            'admin',
            true
        );


        $result =
            $this->service->deactivate(
                $administratorId,
                $administratorId
            );


        self::assertFalse(
            $result['success']
        );

        self::assertSame(
            'You cannot deactivate your own account while signed in.',
            $result['errors']['user']
        );
    }


    public function testFinalActiveAdministratorCannotBeDeactivated(): void
    {
        $administratorId =
            $this->createUser(
                'administrator',
                'admin',
                true
            );

        $supervisorId =
            $this->createUser(
                'supervisor',
                'supervisor',
                true
            );


        $result =
            $this->service->deactivate(
                $supervisorId,
                $administratorId
            );


        self::assertFalse(
            $result['success']
        );


        $authorizedResult =
            $this->service->deactivate(
                $administratorId,
                $supervisorId
            );


        self::assertTrue(
            $authorizedResult['success']
        );

        self::assertSame(
            1,
            $this->repository
                ->countActiveAdministrators()
        );
    }


    public function testOneAdministratorCanDeactivateAnotherWhenTwoAreActive(): void
    {
        $firstAdministratorId =
            $this->createUser(
                'first-admin',
                'admin',
                true
            );

        $secondAdministratorId =
            $this->createUser(
                'second-admin',
                'admin',
                true
            );


        $result =
            $this->service->deactivate(
                $secondAdministratorId,
                $firstAdministratorId
            );


        self::assertTrue(
            $result['success']
        );

        self::assertSame(
            1,
            $this->repository
                ->countActiveAdministrators()
        );
    }


    public function testAdministratorCanActivateInactiveSupervisor(): void
    {
        $administratorId =
            $this->createUser(
                'administrator',
                'admin',
                true
            );

        $supervisorId =
            $this->createUser(
                'inactive-supervisor',
                'supervisor',
                false
            );


        $result =
            $this->service->activate(
                $administratorId,
                $supervisorId
            );


        self::assertTrue(
            $result['success']
        );

        self::assertTrue(
            $result['changed']
        );

        self::assertSame(
            1,
            (int)$this->repository
                ->findById(
                    $supervisorId
                )['active']
        );
    }


    public function testMissingTargetAccountIsRejected(): void
    {
        $administratorId =
            $this->createUser(
                'administrator',
                'admin',
                true
            );


        $result =
            $this->service->resetPassword(
                $administratorId,
                9999,
                'ReplacementPassword!456',
                'ReplacementPassword!456'
            );


        self::assertFalse(
            $result['success']
        );

        self::assertSame(
            'User account not found.',
            $result['errors']['user']
        );
    }


    private function createUsersTable(): void
    {
        $this->db->exec(
            '
            CREATE TABLE users
            (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT "supervisor",
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                email TEXT,
                active INTEGER NOT NULL DEFAULT 1,
                last_login DATETIME
            )
            '
        );
    }


    private function createUser(
        string $username,
        string $role,
        bool $active
    ): int
    {
        return $this->repository->createManaged(
            [
                'username' =>
                    $username,

                'email' =>
                    $username
                    .
                    '@example.test',

                'password_hash' =>
                    password_hash(
                        'OriginalPassword!123',
                        PASSWORD_DEFAULT
                    ),

                'role' =>
                    $role,

                'active' =>
                    $active
                        ? 1
                        : 0
            ]
        );
    }


    /**
     * @return array<string,mixed>
     */
    private function validCreateData(): array
    {
        return [
            'username' =>
                'new-supervisor',

            'email' =>
                'supervisor@example.test',

            'role' =>
                'supervisor',

            'active' =>
                true,

            'password' =>
                'TestingPassword!123',

            'confirm_password' =>
                'TestingPassword!123'
        ];
    }
}
