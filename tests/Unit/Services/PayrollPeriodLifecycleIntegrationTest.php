<?php
declare(strict_types=1);

use App\Repositories\PayrollExceptionResolutionRepository;
use App\Repositories\PayrollPeriodHistoryRepository;
use App\Repositories\PayrollPeriodRepository;
use App\Repositories\PayrollReviewNoteRepository;
use App\Services\PayrollApprovalService;
use App\Services\PayrollExceptionResolutionService;
use App\Services\PayrollExceptionService;
use App\Services\PayrollPeriodProtectionService;
use App\Services\PayrollPeriodService;
use App\Services\PayrollReportPeriodMetadataService;
use App\Services\PayrollReviewNoteService;
use App\Services\PayrollWorkspaceService;
use PHPUnit\Framework\TestCase;

final class PayrollPeriodLifecycleIntegrationTest extends TestCase
{
    private \PDO $db;

    private PayrollPeriodRepository $periods;

    private PayrollPeriodService $periodService;

    private PayrollPeriodProtectionService $protection;

    private PayrollReportPeriodMetadataService $metadata;

    private PayrollApprovalService $approval;

    private PayrollReviewNoteService $notes;

    private PayrollExceptionService $exceptions;

    private PayrollExceptionResolutionService $resolutions;


    protected function setUp(): void
    {
        parent::setUp();


        $this->db =
            new \PDO(
                'sqlite::memory:'
            );


        $this->db->setAttribute(
            \PDO::ATTR_ERRMODE,
            \PDO::ERRMODE_EXCEPTION
        );


        $this->db->setAttribute(
            \PDO::ATTR_DEFAULT_FETCH_MODE,
            \PDO::FETCH_ASSOC
        );


        $this->db->exec(
            'PRAGMA foreign_keys = ON'
        );


        $this->createSchema();

        $this->createUser();


        $this->periods =
            new PayrollPeriodRepository(
                $this->db
            );


        $history =
            new PayrollPeriodHistoryRepository(
                $this->db
            );


        $reviewNotes =
            new PayrollReviewNoteRepository(
                $this->db
            );


        $exceptionRepository =
            new PayrollExceptionResolutionRepository(
                $this->db
            );


        $this->periodService =
            new PayrollPeriodService(
                $this->db,
                $this->periods,
                $history
            );


        $this->protection =
            new PayrollPeriodProtectionService(
                $this->periods
            );


        $this->metadata =
            new PayrollReportPeriodMetadataService(
                $this->periods,
                $exceptionRepository
            );


        $this->approval =
            new PayrollApprovalService(
                $this->db,
                $this->periods,
                $history,
                $exceptionRepository
            );


        $this->notes =
            new PayrollReviewNoteService(
                $this->db,
                $this->periods,
                $reviewNotes,
                $history
            );


        $workspaceReflection =
            new \ReflectionClass(
                PayrollWorkspaceService::class
            );


        $workspace =
            $workspaceReflection
                ->newInstanceWithoutConstructor();


        $this->exceptions =
            new PayrollExceptionService(
                $this->db,
                $this->periods,
                $exceptionRepository,
                $workspace
            );


        $this->resolutions =
            new PayrollExceptionResolutionService(
                $this->db,
                $this->periods,
                $exceptionRepository,
                $history
            );
    }


    public function testInactivePeriodsDoNotBlockReplacementCreation(): void
    {
        $this->createPeriod(
            'Voided Draft',
            '2026-09-01',
            '2026-09-07',
            'open',
            voidedAt:
                '2026-09-08 08:00:00'
        );


        $voidReplacement =
            $this->periodService
                ->create(
                    [
                        'period_name' =>
                            'Replacement One',

                        'start_date' =>
                            '2026-09-01',

                        'end_date' =>
                            '2026-09-07'
                    ],
                    1
                );


        $this->createPeriod(
            'Archived Draft',
            '2026-09-08',
            '2026-09-14',
            'open',
            archivedAt:
                '2026-09-15 08:00:00'
        );


        $archiveReplacement =
            $this->periodService
                ->create(
                    [
                        'period_name' =>
                            'Replacement Two',

                        'start_date' =>
                            '2026-09-08',

                        'end_date' =>
                            '2026-09-14'
                    ],
                    1
                );


        self::assertGreaterThan(
            1,
            $voidReplacement
        );

        self::assertGreaterThan(
            $voidReplacement,
            $archiveReplacement
        );


        self::assertSame(
            'Replacement One',
            $this->periods
                ->find(
                    $voidReplacement
                )['period_name']
        );


        self::assertSame(
            'Replacement Two',
            $this->periods
                ->find(
                    $archiveReplacement
                )['period_name']
        );
    }


    public function testInactiveFinalPeriodsDoNotProtectPunches(): void
    {
        $this->createPeriod(
            'Archived Approval',
            '2026-09-15',
            '2026-09-21',
            'approved',
            archivedAt:
                '2026-09-22 08:00:00'
        );


        $this->createPeriod(
            'Voided Final',
            '2026-09-22',
            '2026-09-28',
            'locked',
            voidedAt:
                '2026-09-29 08:00:00'
        );


        self::assertNull(
            $this->protection
                ->findProtectedForLocalDate(
                    '2026-09-18'
                )
        );


        self::assertNull(
            $this->protection
                ->findProtectedForLocalDate(
                    '2026-09-25'
                )
        );


        self::assertFalse(
            $this->protection
                ->isLocalDateProtected(
                    '2026-09-18'
                )
        );


        self::assertFalse(
            $this->protection
                ->isLocalDateProtected(
                    '2026-09-25'
                )
        );
    }


    public function testInactivePeriodsAreExcludedFromReportAssociation(): void
    {
        $this->createPeriod(
            'Voided Report Period',
            '2026-10-01',
            '2026-10-07',
            'open',
            voidedAt:
                '2026-10-08 08:00:00'
        );


        $exact =
            $this->metadata
                ->forRange(
                    '2026-10-01',
                    '2026-10-07'
                );


        $partial =
            $this->metadata
                ->forRange(
                    '2026-10-02',
                    '2026-10-06'
                );


        self::assertSame(
            'none',
            $exact['association_status']
        );

        self::assertNull(
            $exact['payroll_period']
        );

        self::assertSame(
            'none',
            $partial['association_status']
        );

        self::assertNull(
            $partial['payroll_period']
        );
    }


    public function testArchivedPeriodRejectsApprovalWorkflow(): void
    {
        $periodId =
            $this->createPeriod(
                'Archived Workflow',
                '2026-10-08',
                '2026-10-14',
                'open',
                archivedAt:
                    '2026-10-15 08:00:00'
            );


        $this->expectException(
            \RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Archived payroll periods are read-only'
        );


        $this->approval
            ->beginReview(
                $periodId,
                1
            );
    }


    public function testVoidedPeriodRejectsReviewNotes(): void
    {
        $periodId =
            $this->createPeriod(
                'Voided Notes',
                '2026-10-15',
                '2026-10-21',
                'open',
                voidedAt:
                    '2026-10-22 08:00:00'
            );


        $this->expectException(
            \RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Voided payroll periods are read-only'
        );


        $this->notes
            ->add(
                $periodId,
                1,
                'This note must not be accepted.'
            );
    }


    public function testArchivedPeriodRejectsExceptionRefresh(): void
    {
        $periodId =
            $this->createPeriod(
                'Archived Exceptions',
                '2026-10-22',
                '2026-10-28',
                'open',
                archivedAt:
                    '2026-10-29 08:00:00'
            );


        $this->expectException(
            \RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Archived payroll periods are read-only'
        );


        $this->exceptions
            ->refresh(
                $periodId
            );
    }


    public function testVoidedPeriodRejectsExceptionResolution(): void
    {
        $periodId =
            $this->createPeriod(
                'Voided Resolution',
                '2026-10-29',
                '2026-11-04',
                'open',
                voidedAt:
                    '2026-11-05 08:00:00'
            );


        $exceptionId =
            $this->createException(
                $periodId
            );


        $this->expectException(
            \RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Voided payroll periods are read-only'
        );


        $this->resolutions
            ->resolve(
                $periodId,
                $exceptionId,
                1,
                'This resolution must not be accepted.'
            );
    }


    private function createPeriod(
        string $name,
        string $startDate,
        string $endDate,
        string $status,
        ?string $archivedAt = null,
        ?string $voidedAt = null
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
                    created_by_user_id,
                    archived_at,
                    archived_by_user_id,
                    archive_reason,
                    voided_at,
                    voided_by_user_id,
                    void_reason
                )

                VALUES
                (
                    :period_name,
                    :start_date,
                    :end_date,
                    :status,
                    1,
                    :archived_at,
                    :archived_by_user_id,
                    :archive_reason,
                    :voided_at,
                    :voided_by_user_id,
                    :void_reason
                )
                '
            );


        $statement->execute(
            [
                'period_name' =>
                    $name,

                'start_date' =>
                    $startDate,

                'end_date' =>
                    $endDate,

                'status' =>
                    $status,

                'archived_at' =>
                    $archivedAt,

                'archived_by_user_id' =>
                    $archivedAt !== null
                        ? 1
                        : null,

                'archive_reason' =>
                    $archivedAt !== null
                        ? 'Archived integration test period.'
                        : null,

                'voided_at' =>
                    $voidedAt,

                'voided_by_user_id' =>
                    $voidedAt !== null
                        ? 1
                        : null,

                'void_reason' =>
                    $voidedAt !== null
                        ? 'Voided integration test period.'
                        : null
            ]
        );


        return
            (int)$this->db
                ->lastInsertId();
    }


    private function createException(
        int $payrollPeriodId
    ): int
    {
        $statement =
            $this->db->prepare(
                '
                INSERT INTO payroll_exception_resolutions
                (
                    payroll_period_id,
                    employee_id,
                    exception_key,
                    exception_type,
                    exception_date,
                    description,
                    resolution_status
                )

                VALUES
                (
                    :payroll_period_id,
                    NULL,
                    :exception_key,
                    :exception_type,
                    :exception_date,
                    :description,
                    "open"
                )
                '
            );


        $statement->execute(
            [
                'payroll_period_id' =>
                    $payrollPeriodId,

                'exception_key' =>
                    'lifecycle-test-exception',

                'exception_type' =>
                    'missing_clock_out',

                'exception_date' =>
                    '2026-10-30',

                'description' =>
                    'Lifecycle integration test exception.'
            ]
        );


        return
            (int)$this->db
                ->lastInsertId();
    }


    private function createUser(): void
    {
        $this->db->exec(
            '
            INSERT INTO users
            (
                id,
                username,
                email,
                role,
                active
            )

            VALUES
            (
                1,
                "test-supervisor",
                "supervisor@example.test",
                "supervisor",
                1
            )
            '
        );
    }


    private function createSchema(): void
    {
        $this->db->exec(
            '
            CREATE TABLE users
            (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL,
                email TEXT NULL,
                role TEXT NOT NULL,
                active INTEGER NOT NULL DEFAULT 1
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
                created_at DATETIME NOT NULL
                    DEFAULT CURRENT_TIMESTAMP,
                review_started_at DATETIME NULL,
                approved_at DATETIME NULL,
                locked_at DATETIME NULL,
                updated_at DATETIME NOT NULL
                    DEFAULT CURRENT_TIMESTAMP,
                archived_at DATETIME NULL,
                archived_by_user_id INTEGER NULL,
                archive_reason TEXT NULL,
                voided_at DATETIME NULL,
                voided_by_user_id INTEGER NULL,
                void_reason TEXT NULL,

                FOREIGN KEY
                (
                    created_by_user_id
                )
                REFERENCES users(id)
                ON DELETE RESTRICT,

                FOREIGN KEY
                (
                    archived_by_user_id
                )
                REFERENCES users(id)
                ON DELETE RESTRICT,

                FOREIGN KEY
                (
                    voided_by_user_id
                )
                REFERENCES users(id)
                ON DELETE RESTRICT,

                CHECK
                (
                    status IN
                    (
                        "open",
                        "under_review",
                        "approved",
                        "locked"
                    )
                ),

                CHECK
                (
                    start_date <= end_date
                )
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
                created_at DATETIME NOT NULL
                    DEFAULT CURRENT_TIMESTAMP,

                FOREIGN KEY
                (
                    payroll_period_id
                )
                REFERENCES payroll_periods(id)
                ON DELETE RESTRICT,

                FOREIGN KEY
                (
                    user_id
                )
                REFERENCES users(id)
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
                created_by_user_id INTEGER NOT NULL,
                created_at DATETIME NOT NULL
                    DEFAULT CURRENT_TIMESTAMP,

                FOREIGN KEY
                (
                    payroll_period_id
                )
                REFERENCES payroll_periods(id)
                ON DELETE RESTRICT,

                FOREIGN KEY
                (
                    created_by_user_id
                )
                REFERENCES users(id)
                ON DELETE RESTRICT
            )
            '
        );


        $this->db->exec(
            '
            CREATE TABLE payroll_exception_resolutions
            (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                payroll_period_id INTEGER NOT NULL,
                employee_id INTEGER NULL,
                exception_key TEXT NOT NULL,
                exception_type TEXT NOT NULL,
                exception_date TEXT NULL,
                description TEXT NOT NULL,
                resolution_status TEXT NOT NULL
                    DEFAULT "open",
                resolution_note TEXT NULL,
                resolved_by_user_id INTEGER NULL,
                resolved_at DATETIME NULL,
                created_at DATETIME NOT NULL
                    DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL
                    DEFAULT CURRENT_TIMESTAMP,

                FOREIGN KEY
                (
                    payroll_period_id
                )
                REFERENCES payroll_periods(id)
                ON DELETE RESTRICT,

                FOREIGN KEY
                (
                    resolved_by_user_id
                )
                REFERENCES users(id)
                ON DELETE RESTRICT,

                UNIQUE
                (
                    payroll_period_id,
                    exception_key
                ),

                CHECK
                (
                    resolution_status IN
                    (
                        "open",
                        "resolved",
                        "accepted"
                    )
                )
            )
            '
        );
    }
}
