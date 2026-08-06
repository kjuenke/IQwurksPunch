<?php
declare(strict_types=1);

use App\Repositories\AuditRepository;
use PHPUnit\Framework\TestCase;

final class AuditRepositoryUserManagementTest extends TestCase
{
    private PDO $db;

    private AuditRepository $repository;


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


        $this->createSchema();


        $this->repository =
            new AuditRepository(
                $this->db
            );
    }


    public function testRecentUserActionsExcludeUnrelatedAuditEvents(): void
    {
        $administratorId =
            $this->createUser(
                'administrator'
            );


        $this->repository->create(
            'user.created',
            'Created supervisor user ID 2.',
            $administratorId
        );

        $this->repository->create(
            'employee.created',
            'Created employee ID 5.',
            $administratorId
        );

        $this->repository->create(
            'user.deactivated',
            'Deactivated supervisor user ID 2.',
            $administratorId
        );


        $actions =
            $this->repository
                ->recentUserManagementActions();


        self::assertCount(
            2,
            $actions
        );

        self::assertSame(
            'user.deactivated',
            $actions[0]['action']
        );

        self::assertSame(
            'user.created',
            $actions[1]['action']
        );

        self::assertSame(
            'administrator',
            $actions[0]['actor_username']
        );
    }


    public function testSystemUserActionCanHaveNoActor(): void
    {
        $this->repository->create(
            'user.create_blocked',
            'A system-level account operation was blocked.'
        );


        $actions =
            $this->repository
                ->recentUserManagementActions(
                    1
                );


        self::assertCount(
            1,
            $actions
        );

        self::assertNull(
            $actions[0]['user_id']
        );

        self::assertNull(
            $actions[0]['actor_username']
        );
    }


    public function testActivityLimitIsApplied(): void
    {
        $administratorId =
            $this->createUser(
                'administrator'
            );


        for ($number = 1; $number <= 5; $number++) {
            $this->repository->create(
                'user.updated',
                'Updated user event '
                .
                $number
                .
                '.',
                $administratorId
            );
        }


        $actions =
            $this->repository
                ->recentUserManagementActions(
                    2
                );


        self::assertCount(
            2,
            $actions
        );

        self::assertSame(
            'Updated user event 5.',
            $actions[0]['details']
        );

        self::assertSame(
            'Updated user event 4.',
            $actions[1]['details']
        );
    }


    public function testActivityLimitMustRemainWithinSafeRange(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'The user-management activity limit must be between 1 and 500.'
        );


        $this->repository
            ->recentUserManagementActions(
                0
            );
    }


    private function createSchema(): void
    {
        $this->db->exec(
            '
            CREATE TABLE users
            (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE
            )
            '
        );


        $this->db->exec(
            '
            CREATE TABLE audit_log
            (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                action TEXT NOT NULL,
                details TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
            '
        );
    }


    private function createUser(
        string $username
    ): int
    {
        $statement =
            $this->db->prepare(
                '
                INSERT INTO users
                (
                    username
                )

                VALUES
                (
                    :username
                )
                '
            );


        $statement->execute(
            [
                'username' =>
                    $username
            ]
        );


        return (int)$this->db
            ->lastInsertId();
    }
}
