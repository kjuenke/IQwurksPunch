<?php
declare(strict_types=1);

use App\Repositories\AuditRepository;
use App\Repositories\PayrollPeriodHistoryRepository;
use App\Repositories\PayrollPeriodRemovalRepository;
use App\Repositories\UserRepository;
use App\Services\PayrollPeriodRemovalService;
use PHPUnit\Framework\TestCase;

final class PayrollPeriodRemovalServiceTest extends TestCase
{
    private PDO $db;

    private PayrollPeriodRemovalRepository $periods;

    private PayrollPeriodRemovalService $service;

    private int $administratorId;

    private int $supervisorId;


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

        $this->db->exec(
            'PRAGMA foreign_keys = ON'
        );


        $this->createSchema();


        $userRepository =
            new UserRepository(
                $this->db
            );

        $this->administratorId =
            $this->createUser(
                $userRepository,
                'test-admin',
                'admin',
                true
            );

        $this->supervisorId =
            $this->createUser(
                $userRepository,
                'test-supervisor',
                'supervisor',
                true
            );


        $this->periods =
            new PayrollPeriodRemovalRepository(
                $this->db
            );


        $this->service =
            new PayrollPeriodRemovalService(
                $this->db,
                $this->periods,
                new PayrollPeriodHistoryRepository(
                    $this->db
                ),
                $userRepository,
                new AuditRepository(
                    $this->db
                )
            );
    }


    public function testAdministratorCanAnalyzeUntouchedOpenDraft(): void
    {
        $periodId =
            $this->createPeriod(
                'Draft Payroll',
                'open'
            );


        $analysis =
            $this->service->analyze(
                $periodId,
                $this->administratorId
            );


        self::assertTrue(
            $analysis['can_delete_draft']
        );

        self::assertTrue(
            $analysis['can_archive']
        );

        self::assertFalse(
            $analysis['can_void']
        );

        self::assertFalse(
            $analysis['is_archived']
        );

        self::assertFalse(
            $analysis['is_voided']
        );

        self::assertSame(
            1,
            $analysis['dependencies']['history_count']
        );
    }


    public function testSupervisorCannotAnalyzeRemovalOptions(): void
    {
        $periodId =
            $this->createPeriod(
                'Supervisor Test',
                'open'
            );


        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Only active administrators'
        );


        $this->service->analyze(
            $periodId,
            $this->supervisorId
        );
    }


    public function testUntouchedOpenDraftCanBePermanentlyDeleted(): void
    {
        $periodId =
            $this->createPeriod(
                'Disposable Draft',
                'open'
            );


        $deleted =
            $this->service->deleteDraft(
                $periodId,
                $this->administratorId,
                'DELETE'
            );


        self::assertSame(
            $periodId,
            (int)$deleted['id']
        );

        self::assertNull(
            $this->periods->find(
                $periodId
            )
        );

        self::assertSame(
            0,
            $this->tableCount(
                'payroll_period_history'
            )
        );

        self::assertSame(
            1,
            $this->tableCount(
                'audit_log'
            )
        );

        self::assertSame(
            'payroll_period.deleted',
            $this->latestAuditAction()
        );
    }


    public function testDraftDeletionRequiresExactConfirmation(): void
    {
        $periodId =
            $this->createPeriod(
                'Confirmation Draft',
                'open'
            );


        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Enter DELETE exactly'
        );


        $this->service->deleteDraft(
            $periodId,
            $this->administratorId,
            'delete'
        );
    }


    public function testPeriodThatEnteredWorkflowCannotBeDeleted(): void
    {
        $periodId =
            $this->createPeriod(
                'Reviewed Payroll',
                'under_review'
            );

        $this->addHistory(
            $periodId,
            'review_started',
            'open',
            'under_review'
        );


        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Only an untouched open payroll period'
        );


        $this->service->deleteDraft(
            $periodId,
            $this->administratorId,
            'DELETE'
        );
    }


    public function testOpenPeriodWithReviewNoteCannotBeDeleted(): void
    {
        $periodId =
            $this->createPeriod(
                'Noted Draft',
                'open'
            );


        $statement =
            $this->db->prepare(
                '
                INSERT INTO payroll_review_notes
                (
                    payroll_period_id,
                    note,
                    created_by_user_id
                )

                VALUES
                (
                    :payroll_period_id,
                    :note,
                    :created_by_user_id
                )
                '
            );


        $statement->execute(
            [
                'payroll_period_id' =>
                    $periodId,

                'note' =>
                    'This draft has operational history.',

                'created_by_user_id' =>
                    $this->supervisorId
            ]
        );


        $analysis =
            $this->service->analyze(
                $periodId,
                $this->administratorId
            );


        self::assertFalse(
            $analysis['can_delete_draft']
        );

        self::assertSame(
            1,
            $analysis['dependencies']['review_note_count']
        );
    }


    public function testOpenPeriodWithExceptionRecordCannotBeDeleted(): void
    {
        $periodId =
            $this->createPeriod(
                'Exception Draft',
                'open'
            );


        $statement =
            $this->db->prepare(
                '
                INSERT INTO payroll_exception_resolutions
                (
                    payroll_period_id,
                    resolution_status
                )

                VALUES
                (
                    :payroll_period_id,
                    "open"
                )
                '
            );


        $statement->execute(
            [
                'payroll_period_id' =>
                    $periodId
            ]
        );


        $analysis =
            $this->service->analyze(
                $periodId,
                $this->administratorId
            );


        self::assertFalse(
            $analysis['can_delete_draft']
        );

        self::assertSame(
            1,
            $analysis['dependencies']['exception_count']
        );

        self::assertSame(
            0,
            $analysis['dependencies']['resolution_count']
        );
    }


    public function testAdministratorCanArchivePeriodWithReason(): void
    {
        $periodId =
            $this->createPeriod(
                'Archived Payroll',
                'under_review'
            );


        $archived =
            $this->service->archive(
                $periodId,
                $this->administratorId,
                'This payroll period is no longer part of active operations.'
            );


        self::assertNotEmpty(
            $archived['archived_at']
        );

        self::assertSame(
            $this->administratorId,
            (int)$archived['archived_by_user_id']
        );

        self::assertSame(
            'This payroll period is no longer part of active operations.',
            $archived['archive_reason']
        );

        self::assertSame(
            'archived',
            $this->latestHistoryAction(
                $periodId
            )
        );

        self::assertSame(
            'payroll_period.archived',
            $this->latestAuditAction()
        );
    }


    public function testArchiveRequiresMeaningfulReason(): void
    {
        $periodId =
            $this->createPeriod(
                'Archive Reason Test',
                'open'
            );


        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Archive reason must contain at least 10 characters.'
        );


        $this->service->archive(
            $periodId,
            $this->administratorId,
            'Too short'
        );
    }


    public function testApprovedPeriodCanBeVoided(): void
    {
        $periodId =
            $this->createPeriod(
                'Approved Payroll',
                'approved'
            );


        $voided =
            $this->service->void(
                $periodId,
                $this->administratorId,
                'The approved payroll period was created for the wrong dates.',
                'VOID'
            );


        self::assertNotEmpty(
            $voided['voided_at']
        );

        self::assertSame(
            $this->administratorId,
            (int)$voided['voided_by_user_id']
        );

        self::assertSame(
            'The approved payroll period was created for the wrong dates.',
            $voided['void_reason']
        );

        self::assertSame(
            'voided',
            $this->latestHistoryAction(
                $periodId
            )
        );

        self::assertSame(
            'payroll_period.voided',
            $this->latestAuditAction()
        );
    }


    public function testVoidRequiresExactConfirmation(): void
    {
        $periodId =
            $this->createPeriod(
                'Locked Payroll',
                'locked'
            );


        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Enter VOID exactly'
        );


        $this->service->void(
            $periodId,
            $this->administratorId,
            'This locked payroll period must be invalidated.',
            'void'
        );
    }


    public function testOpenPeriodCannotBeVoided(): void
    {
        $periodId =
            $this->createPeriod(
                'Open Payroll',
                'open'
            );


        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Only an approved or locked payroll period can be voided.'
        );


        $this->service->void(
            $periodId,
            $this->administratorId,
            'This open period should not be eligible for voiding.',
            'VOID'
        );
    }


    public function testArchiveRollsBackWhenHistoryCannotBeRecorded(): void
    {
        $periodId =
            $this->createPeriod(
                'Rollback Archive',
                'open'
            );


        $this->db->exec(
            '
            CREATE TRIGGER fail_removal_history_insert

            BEFORE INSERT
            ON payroll_period_history

            WHEN NEW.action = "archived"

            BEGIN
                SELECT RAISE(
                    ABORT,
                    "forced removal history failure"
                );
            END
            '
        );


        try {
            $this->service->archive(
                $periodId,
                $this->administratorId,
                'This archive attempt must roll back completely.'
            );

            self::fail(
                'The archive did not fail when history insertion failed.'
            );
        } catch (Throwable $exception) {
            self::assertStringContainsString(
                'forced removal history failure',
                $exception->getMessage()
            );
        }


        $period =
            $this->periods->find(
                $periodId
            );


        self::assertNull(
            $period['archived_at']
        );

        self::assertSame(
            0,
            $this->tableCount(
                'audit_log'
            )
        );

        self::assertFalse(
            $this->db->inTransaction()
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
                password_hash TEXT NOT NULL,
                role TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                email TEXT,
                active INTEGER NOT NULL DEFAULT 1,
                last_login DATETIME
            )
            '
        );


        $this->db->exec(
            '
            CREATE TABLE payroll_periods
            (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                period_name TEXT NOT NULL,
                start_date TEXT NOT NULL,
                end_date TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT "open",
                created_by_user_id INTEGER NOT NULL,
                reviewed_by_user_id INTEGER NULL,
                approved_by_user_id INTEGER NULL,
                locked_by_user_id INTEGER NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                review_started_at DATETIME NULL,
                approved_at DATETIME NULL,
                locked_at DATETIME NULL,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                archived_at DATETIME NULL,
                archived_by_user_id INTEGER NULL,
                archive_reason TEXT NULL,
                voided_at DATETIME NULL,
                voided_by_user_id INTEGER NULL,
                void_reason TEXT NULL
            )
            '
        );


        $this->db->exec(
            '
            CREATE TABLE payroll_period_history
            (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                payroll_period_id INTEGER NOT NULL,
                action TEXT NOT NULL,
                previous_status TEXT NULL,
                new_status TEXT NOT NULL,
                reason TEXT NULL,
                user_id INTEGER NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

                FOREIGN KEY(payroll_period_id)
                REFERENCES payroll_periods(id)
                ON DELETE RESTRICT
            )
            '
        );


        $this->db->exec(
            '
            CREATE TABLE payroll_review_notes
            (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                payroll_period_id INTEGER NOT NULL,
                note TEXT NOT NULL,
                created_by_user_id INTEGER NOT NULL
            )
            '
        );


        $this->db->exec(
            '
            CREATE TABLE payroll_exception_resolutions
            (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                payroll_period_id INTEGER NOT NULL,
                resolution_status TEXT NOT NULL
                    DEFAULT "open"
            )
            '
        );


        $this->db->exec(
            '
            CREATE TABLE audit_log
            (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NULL,
                action TEXT NOT NULL,
                details TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
            '
        );
    }


    private function createUser(
        UserRepository $users,
        string $username,
        string $role,
        bool $active
    ): int
    {
        return $users->createManaged(
            [
                'username' =>
                    $username,

                'email' =>
                    $username
                    .
                    '@example.test',

                'password_hash' =>
                    password_hash(
                        'TestingPassword!123',
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


    private function createPeriod(
        string $periodName,
        string $status
    ): int
    {
        $statement =
            $this->db->prepare(
                '
                INSERT INTO payroll_periods
                (
                    period_name,
                    start_date,
                    end_date,
                    status,
                    created_by_user_id
                )

                VALUES
                (
                    :period_name,
                    "2026-08-01",
                    "2026-08-07",
                    :status,
                    :created_by_user_id
                )
                '
            );


        $statement->execute(
            [
                'period_name' =>
                    $periodName,

                'status' =>
                    $status,

                'created_by_user_id' =>
                    $this->supervisorId
            ]
        );


        $periodId =
            (int)$this->db
                ->lastInsertId();


        $this->addHistory(
            $periodId,
            'created',
            null,
            'open'
        );


        return $periodId;
    }


    private function addHistory(
        int $periodId,
        string $action,
        ?string $previousStatus,
        string $newStatus
    ): void
    {
        $statement =
            $this->db->prepare(
                '
                INSERT INTO payroll_period_history
                (
                    payroll_period_id,
                    action,
                    previous_status,
                    new_status,
                    reason,
                    user_id
                )

                VALUES
                (
                    :payroll_period_id,
                    :action,
                    :previous_status,
                    :new_status,
                    NULL,
                    :user_id
                )
                '
            );


        $statement->execute(
            [
                'payroll_period_id' =>
                    $periodId,

                'action' =>
                    $action,

                'previous_status' =>
                    $previousStatus,

                'new_status' =>
                    $newStatus,

                'user_id' =>
                    $this->supervisorId
            ]
        );
    }


    private function tableCount(
        string $table
    ): int
    {
        return (int)$this->db
            ->query(
                'SELECT COUNT(*) FROM '
                .
                $table
            )
            ->fetchColumn();
    }


    private function latestHistoryAction(
        int $periodId
    ): string
    {
        $statement =
            $this->db->prepare(
                '
                SELECT action
                FROM payroll_period_history

                WHERE payroll_period_id =
                    :payroll_period_id

                ORDER BY id DESC

                LIMIT 1
                '
            );


        $statement->execute(
            [
                'payroll_period_id' =>
                    $periodId
            ]
        );


        return (string)$statement
            ->fetchColumn();
    }


    private function latestAuditAction(): string
    {
        return (string)$this->db
            ->query(
                '
                SELECT action
                FROM audit_log
                ORDER BY id DESC
                LIMIT 1
                '
            )
            ->fetchColumn();
    }
}
