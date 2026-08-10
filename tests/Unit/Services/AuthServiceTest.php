<?php
declare(strict_types=1);

use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Services\CsrfService;
use PHPUnit\Framework\TestCase;

final class AuthServiceTest extends TestCase
{
    private PDO $db;

    private UserRepository $users;

    private CsrfService $csrf;

    private AuthService $auth;


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


        $this->createSchema();


        $this->users =
            new UserRepository(
                $this->db
            );


        $this->csrf =
            new CsrfService();


        $this->auth =
            new AuthService(
                $this->users,
                $this->csrf
            );
    }


    protected function tearDown(): void
    {
        $_SESSION = [];


        parent::tearDown();
    }


    public function testCreateAdminHashesPasswordAndCompletesSetup(): void
    {
        self::assertFalse(
            $this->auth
                ->setupComplete()
        );


        $userId =
            $this->auth
                ->createAdmin(
                    'initial-admin',
                    'admin@example.test',
                    'correct horse battery staple'
                );


        self::assertGreaterThan(
            0,
            $userId
        );


        self::assertTrue(
            $this->auth
                ->setupComplete()
        );


        $user =
            $this->users
                ->findById(
                    $userId
                );


        self::assertNotNull(
            $user
        );


        self::assertSame(
            'admin',
            $user['role']
        );


        self::assertSame(
            1,
            (int)$user['active']
        );


        self::assertTrue(
            password_verify(
                'correct horse battery staple',
                (string)$user['password_hash']
            )
        );


        self::assertNotSame(
            'correct horse battery staple',
            $user['password_hash']
        );
    }


    public function testLoginAuthenticatesActiveAuthorizedAccountAndRotatesCsrf(): void
    {
        $userId =
            $this->createUser(
                'ActiveSupervisor',
                'supervisor',
                true,
                'login-password'
            );


        $oldToken =
            $this->csrf
                ->token();


        $before =
            time();


        self::assertTrue(
            $this->auth
                ->attemptLogin(
                    ' activesupervisor ',
                    'login-password'
                )
        );


        self::assertSame(
            $userId,
            $_SESSION['user_id']
        );


        self::assertSame(
            'ActiveSupervisor',
            $_SESSION['username']
        );


        self::assertSame(
            'supervisor',
            $_SESSION['user_role']
        );


        self::assertGreaterThanOrEqual(
            $before,
            $_SESSION['authenticated_at']
        );


        self::assertGreaterThanOrEqual(
            $before,
            $_SESSION['last_activity']
        );


        self::assertNotSame(
            $oldToken,
            $_SESSION['csrf_token']
        );


        self::assertSame(
            64,
            strlen(
                $_SESSION['csrf_token']
            )
        );


        $updated =
            $this->users
                ->findById(
                    $userId
                );


        self::assertNotNull(
            $updated['last_login']
        );
    }


    public function testLoginRejectsBlankUnknownAndIncorrectCredentials(): void
    {
        $this->createUser(
            'administrator',
            'admin',
            true,
            'correct-password'
        );


        $token =
            $this->csrf
                ->token();


        self::assertFalse(
            $this->auth
                ->attemptLogin(
                    '',
                    ''
                )
        );


        self::assertFalse(
            $this->auth
                ->attemptLogin(
                    'missing-user',
                    'correct-password'
                )
        );


        self::assertFalse(
            $this->auth
                ->attemptLogin(
                    'administrator',
                    'incorrect-password'
                )
        );


        self::assertArrayNotHasKey(
            'user_id',
            $_SESSION
        );


        self::assertSame(
            $token,
            $_SESSION['csrf_token']
        );
    }


    public function testLoginRejectsInactiveAndUnauthorizedAccounts(): void
    {
        $this->createUser(
            'inactive-supervisor',
            'supervisor',
            false,
            'testing-password'
        );


        $this->createUser(
            'employee-role',
            'employee',
            true,
            'testing-password'
        );


        self::assertFalse(
            $this->auth
                ->attemptLogin(
                    'inactive-supervisor',
                    'testing-password'
                )
        );


        self::assertFalse(
            $this->auth
                ->attemptLogin(
                    'employee-role',
                    'testing-password'
                )
        );


        self::assertArrayNotHasKey(
            'user_id',
            $_SESSION
        );
    }


    public function testLogoutClearsAuthenticationAndCsrfState(): void
    {
        $_SESSION = [
            'user_id' =>
                10,

            'username' =>
                'administrator',

            'user_role' =>
                'admin',

            'authenticated_at' =>
                time(),

            'last_activity' =>
                time(),

            'csrf_token' =>
                str_repeat(
                    'a',
                    64
                )
        ];


        $previousUseCookies =
            (string)ini_get(
                'session.use_cookies'
            );


        ini_set(
            'session.use_cookies',
            '0'
        );


        try {
            $this->auth
                ->logout();
        } finally {
            ini_set(
                'session.use_cookies',
                $previousUseCookies
            );
        }


        self::assertSame(
            [],
            $_SESSION
        );
    }


    private function createSchema(): void
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
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                last_login TEXT
            )
            '
        );
    }


    private function createUser(
        string $username,
        string $role,
        bool $active,
        string $password
    ): int
    {
        $hash =
            password_hash(
                $password,
                PASSWORD_DEFAULT
            );


        if (!is_string($hash)) {
            throw new RuntimeException(
                'The test password could not be secured.'
            );
        }


        return $this->users
            ->createManaged(
                [
                    'username' =>
                        $username,

                    'email' =>
                        $username
                        .
                        '@example.test',

                    'password_hash' =>
                        $hash,

                    'role' =>
                        $role,

                    'active' =>
                        $active
                            ? 1
                            : 0
                ]
            );
    }
}
