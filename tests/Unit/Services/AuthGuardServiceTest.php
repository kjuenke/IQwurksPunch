<?php
declare(strict_types=1);

use App\Repositories\UserRepository;
use App\Services\AuthGuardService;
use PHPUnit\Framework\TestCase;

final class AuthGuardServiceTest extends TestCase
{
    private PDO $db;

    private AuthGuardService $guard;


    protected function setUp(): void
    {
        parent::setUp();


        $_SESSION = [];


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


        $this->guard =
            new AuthGuardService(
                new UserRepository(
                    $this->db
                )
            );
    }


    protected function tearDown(): void
    {
        $_SESSION = [];


        parent::tearDown();
    }


    public function testActiveAdministratorIsAuthorized(): void
    {
        $userId =
            $this->createUser(
                'admin-user',
                'admin',
                true
            );


        $user =
            $this->guard
                ->authorizedUserForId(
                    $userId
                );


        self::assertSame(
            $userId,
            (int)$user['id']
        );


        self::assertSame(
            'admin-user',
            $user['username']
        );


        self::assertSame(
            'admin',
            $user['role']
        );


        self::assertSame(
            1,
            (int)$user['active']
        );
    }


    public function testActiveSupervisorIsAuthorized(): void
    {
        $userId =
            $this->createUser(
                'supervisor-user',
                'supervisor',
                true
            );


        $user =
            $this->guard
                ->authorizedUserForId(
                    $userId
                );


        self::assertSame(
            $userId,
            (int)$user['id']
        );


        self::assertSame(
            'supervisor-user',
            $user['username']
        );


        self::assertSame(
            'supervisor',
            $user['role']
        );


        self::assertSame(
            1,
            (int)$user['active']
        );
    }


    public function testInactiveUserIsRejected(): void
    {
        $userId =
            $this->createUser(
                'inactive-supervisor',
                'supervisor',
                false
            );


        $this->expectException(
            RuntimeException::class
        );


        $this->expectExceptionMessage(
            'Your supervisor account is inactive.'
        );


        $this->guard
            ->authorizedUserForId(
                $userId
            );
    }


    public function testUnauthorizedRoleIsRejected(): void
    {
        $userId =
            $this->createUser(
                'employee-user',
                'employee',
                true
            );


        $this->expectException(
            RuntimeException::class
        );


        $this->expectExceptionMessage(
            'Your account is not authorized to access supervisor pages.'
        );


        $this->guard
            ->authorizedUserForId(
                $userId
            );
    }


    public function testMissingUserIsRejected(): void
    {
        $this->expectException(
            RuntimeException::class
        );


        $this->expectExceptionMessage(
            'Your supervisor account could not be found. Please log in again.'
        );


        $this->guard
            ->authorizedUserForId(
                9999
            );
    }


    public function testInvalidUserIdIsRejected(): void
    {
        $this->expectException(
            RuntimeException::class
        );


        $this->expectExceptionMessage(
            'No authenticated supervisor account was provided.'
        );


        $this->guard
            ->authorizedUserForId(
                0
            );
    }


    public function testAuthorizedSessionIsSynchronizedFromDatabase(): void
    {
        $userId =
            $this->createUser(
                'MixedCaseSupervisor',
                ' SUPERVISOR ',
                true
            );


        $_SESSION['user_id'] =
            $userId;


        $_SESSION['username'] =
            'stale-username';


        $_SESSION['user_role'] =
            'admin';


        $verifiedUserId =
            $this->guard
                ->requireAuthorizedUserId(
                    '/payroll-periods',
                    'GET'
                );


        self::assertSame(
            $userId,
            $verifiedUserId
        );


        self::assertSame(
            $userId,
            $_SESSION['user_id']
        );


        self::assertSame(
            'MixedCaseSupervisor',
            $_SESSION['username']
        );


        self::assertSame(
            'supervisor',
            $_SESSION['user_role']
        );


        self::assertIsInt(
            $_SESSION['last_activity']
        );


        self::assertGreaterThan(
            0,
            $_SESSION['last_activity']
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
                email TEXT,
                password_hash TEXT NOT NULL,
                role TEXT NOT NULL,
                active INTEGER NOT NULL DEFAULT 1,
                created_at TEXT,
                last_login TEXT
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
        $statement =
            $this->db->prepare(
                '
                INSERT INTO users
                (
                    username,
                    email,
                    password_hash,
                    role,
                    active,
                    created_at
                )

                VALUES
                (
                    :username,
                    :email,
                    :password_hash,
                    :role,
                    :active,
                    CURRENT_TIMESTAMP
                )
                '
            );


        $statement->execute(
            [
                'username' =>
                    $username,

                'email' =>
                    $username
                    .
                    '@example.test',

                'password_hash' =>
                    password_hash(
                        'testing-password',
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


        return (int)$this->db
            ->lastInsertId();
    }
}
