<?php
declare(strict_types=1);

use App\Repositories\PayrollExceptionResolutionRepository;
use App\Repositories\PayrollPeriodHistoryRepository;
use App\Repositories\PayrollPeriodRepository;
use App\Services\PayrollApprovalService;
use PHPUnit\Framework\TestCase;

final class PayrollApprovalServiceTest extends TestCase
{
    private PDO $db;

    private PayrollPeriodRepository $payrollPeriodRepository;

    private PayrollPeriodHistoryRepository $historyRepository;

    private PayrollExceptionResolutionRepository $exceptionRepository;

    private PayrollApprovalService $service;


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

        $this->createUsers();


        $this->payrollPeriodRepository =
            new PayrollPeriodRepository(
                $this->db
            );


        $this->historyRepository =
            new PayrollPeriodHistoryRepository(
                $this->db
            );


        $this->exceptionRepository =
            new PayrollExceptionResolutionRepository(
                $this->db
            );


        $this->service =
            new PayrollApprovalService(
                $this->db,
                $this->payrollPeriodRepository,
                $this->historyRepository,
                $this->exceptionRepository
            );
    }


    public function testOpenPeriodCanBeginReview(): void
    {
        $payrollPeriodId =
            $this->createOpenPeriod();


        $period =
            $this->service
                ->beginReview(
                    $payrollPeriodId,
                    1
                );


        self::assertSame(
            'under_review',
            $period['status']
        );


        self::assertSame(
            1,
            (int)$period['reviewed_by_user_id']
        );


        self::assertNotEmpty(
            $period['review_started_at']
        );


        self::assertNull(
            $period['approved_by_user_id']
        );


        self::assertNull(
            $period['locked_by_user_id']
        );


        $history =
            $this->historyRepository
                ->latestForPeriod(
                    $payrollPeriodId
                );


        self::assertNotNull(
            $history
        );


        self::assertSame(
            'review_started',
            $history['action']
        );


        self::assertSame(
            'open',
            $history['previous_status']
        );


        self::assertSame(
            'under_review',
            $history['new_status']
        );


        self::assertSame(
            1,
            (int)$history['user_id']
        );
    }


    public function testUnauthorizedRoleCannotBeginReview(): void
    {
        $payrollPeriodId =
            $this->createOpenPeriod();


        try {

            $this->service
                ->beginReview(
                    $payrollPeriodId,
                    3
                );


            self::fail(
                'An unauthorized user began payroll review.'
            );

        } catch (RuntimeException $exception) {

            self::assertStringContainsString(
                'not authorized',
                $exception->getMessage()
            );
        }


        $period =
            $this->payrollPeriodRepository
                ->find(
                    $payrollPeriodId
                );


        self::assertNotNull(
            $period
        );


        self::assertSame(
            'open',
            $period['status']
        );


        self::assertSame(
            1,
            $this->historyRepository
                ->countForPeriod(
                    $payrollPeriodId
                )
        );
    }


    public function testInactiveSupervisorCannotBeginReview(): void
    {
        $payrollPeriodId =
            $this->createOpenPeriod();


        try {

            $this->service
                ->beginReview(
                    $payrollPeriodId,
                    4
                );


            self::fail(
                'An inactive supervisor began payroll review.'
            );

        } catch (RuntimeException $exception) {

            self::assertStringContainsString(
                'inactive',
                $exception->getMessage()
            );
        }


        $period =
            $this->payrollPeriodRepository
                ->find(
                    $payrollPeriodId
                );


        self::assertNotNull(
            $period
        );


        self::assertSame(
            'open',
            $period['status']
        );
    }


    public function testUnderReviewPeriodCanReturnToOpen(): void
    {
        $payrollPeriodId =
            $this->createOpenPeriod();


        $this->service
            ->beginReview(
                $payrollPeriodId,
                1
            );


        $period =
            $this->service
                ->returnToOpen(
                    $payrollPeriodId,
                    2,
                    'Additional corrections are required.'
                );


        self::assertSame(
            'open',
            $period['status']
        );


        self::assertNull(
            $period['reviewed_by_user_id']
        );


        self::assertNull(
            $period['review_started_at']
        );


        $history =
            $this->historyRepository
                ->latestForPeriod(
                    $payrollPeriodId
                );


        self::assertNotNull(
            $history
        );


        self::assertSame(
            'returned_to_open',
            $history['action']
        );


        self::assertSame(
            'under_review',
            $history['previous_status']
        );


        self::assertSame(
            'open',
            $history['new_status']
        );


        self::assertSame(
            'Additional corrections are required.',
            $history['reason']
        );


        self::assertSame(
            2,
            (int)$history['user_id']
        );
    }


    public function testApprovalRequiresExplicitConfirmation(): void
    {
        $payrollPeriodId =
            $this->createUnderReviewPeriod();


        $this->expectException(
            InvalidArgumentException::class
        );


        $this->expectExceptionMessage(
            'explicitly confirmed'
        );


        $this->service
            ->approve(
                $payrollPeriodId,
                1,
                false
            );
    }


    public function testOpenPeriodCannotBeApprovedDirectly(): void
    {
        $payrollPeriodId =
            $this->createOpenPeriod();


        try {

            $this->service
                ->approve(
                    $payrollPeriodId,
                    1,
                    true
                );


            self::fail(
                'An open payroll period was approved directly.'
            );

        } catch (RuntimeException $exception) {

            self::assertStringContainsString(
                'under review',
                $exception->getMessage()
            );
        }


        $period =
            $this->payrollPeriodRepository
                ->find(
                    $payrollPeriodId
                );


        self::assertNotNull(
            $period
        );


        self::assertSame(
            'open',
            $period['status']
        );
    }


    public function testOpenExceptionsBlockApproval(): void
    {
        $payrollPeriodId =
            $this->createUnderReviewPeriod();


        $exceptionId =
            $this->exceptionRepository
                ->createOrRefresh(
                    [
                        'payroll_period_id' =>
                            $payrollPeriodId,

                        'employee_id' =>
                            null,

                        'exception_key' =>
                            'missing-clock-out:2026-07-13:employee-1',

                        'exception_type' =>
                            'missing_clock_out',

                        'exception_date' =>
                            '2026-07-13',

                        'description' =>
                            'Employee has no matching clock-out.'
                    ]
                );


        self::assertGreaterThan(
            0,
            $exceptionId
        );


        try {

            $this->service
                ->approve(
                    $payrollPeriodId,
                    1,
                    true
                );


            self::fail(
                'A payroll period with an open exception was approved.'
            );

        } catch (RuntimeException $exception) {

            self::assertStringContainsString(
                '1 unresolved payroll exception',
                $exception->getMessage()
            );
        }


        $period =
            $this->payrollPeriodRepository
                ->find(
                    $payrollPeriodId
                );


        self::assertNotNull(
            $period
        );


        self::assertSame(
            'under_review',
            $period['status']
        );


        self::assertNull(
            $period['approved_by_user_id']
        );


        self::assertSame(
            2,
            $this->historyRepository
                ->countForPeriod(
                    $payrollPeriodId
                )
        );
    }


    public function testUnderReviewPeriodCanBeApproved(): void
    {
        $payrollPeriodId =
            $this->createUnderReviewPeriod();


        $period =
            $this->service
                ->approve(
                    $payrollPeriodId,
                    2,
                    true
                );


        self::assertSame(
            'approved',
            $period['status']
        );


        self::assertSame(
            2,
            (int)$period['approved_by_user_id']
        );


        self::assertSame(
            'test-admin',
            $period['approved_by_username']
        );


        self::assertNotEmpty(
            $period['approved_at']
        );


        self::assertNull(
            $period['locked_by_user_id']
        );


        $history =
            $this->historyRepository
                ->latestForPeriod(
                    $payrollPeriodId
                );


        self::assertNotNull(
            $history
        );


        self::assertSame(
            'approved',
            $history['action']
        );


        self::assertSame(
            'under_review',
            $history['previous_status']
        );


        self::assertSame(
            'approved',
            $history['new_status']
        );


        self::assertSame(
            2,
            (int)$history['user_id']
        );
    }


    public function testUnderReviewPeriodCannotBeLocked(): void
    {
        $payrollPeriodId =
            $this->createUnderReviewPeriod();


        try {

            $this->service
                ->lock(
                    $payrollPeriodId,
                    1,
                    'LOCK'
                );


            self::fail(
                'A payroll period under review was locked.'
            );

        } catch (RuntimeException $exception) {

            self::assertStringContainsString(
                'approved payroll period',
                $exception->getMessage()
            );
        }


        $period =
            $this->payrollPeriodRepository
                ->find(
                    $payrollPeriodId
                );


        self::assertNotNull(
            $period
        );


        self::assertSame(
            'under_review',
            $period['status']
        );
    }


    public function testLockRequiresExactConfirmation(): void
    {
        $payrollPeriodId =
            $this->createApprovedPeriod();


        $this->expectException(
            InvalidArgumentException::class
        );


        $this->expectExceptionMessage(
            'Type LOCK exactly'
        );


        $this->service
            ->lock(
                $payrollPeriodId,
                1,
                'lock'
            );
    }


    public function testApprovedPeriodCanBeLocked(): void
    {
        $payrollPeriodId =
            $this->createApprovedPeriod();


        $period =
            $this->service
                ->lock(
                    $payrollPeriodId,
                    1,
                    'LOCK'
                );


        self::assertSame(
            'locked',
            $period['status']
        );


        self::assertSame(
            1,
            (int)$period['locked_by_user_id']
        );


        self::assertSame(
            'test-supervisor',
            $period['locked_by_username']
        );


        self::assertNotEmpty(
            $period['locked_at']
        );


        self::assertNotNull(
            $period['approved_by_user_id']
        );


        self::assertNotEmpty(
            $period['approved_at']
        );


        $history =
            $this->historyRepository
                ->latestForPeriod(
                    $payrollPeriodId
                );


        self::assertNotNull(
            $history
        );


        self::assertSame(
            'locked',
            $history['action']
        );


        self::assertSame(
            'approved',
            $history['previous_status']
        );


        self::assertSame(
            'locked',
            $history['new_status']
        );
    }


    public function testApprovedPeriodWithoutApprovalMetadataCannotBeLocked(): void
    {
        $payrollPeriodId =
            $this->createOpenPeriod();


        $statement =
            $this->db->prepare(
                "
                UPDATE payroll_periods

                SET status =
                    'approved'

                WHERE id =
                    :id
                "
            );


        $statement->execute(
            [
                'id' =>
                    $payrollPeriodId
            ]
        );


        try {

            $this->service
                ->lock(
                    $payrollPeriodId,
                    1,
                    'LOCK'
                );


            self::fail(
                'A period without approval metadata was locked.'
            );

        } catch (RuntimeException $exception) {

            self::assertStringContainsString(
                'complete approval metadata',
                $exception->getMessage()
            );
        }


        $period =
            $this->payrollPeriodRepository
                ->find(
                    $payrollPeriodId
                );


        self::assertNotNull(
            $period
        );


        self::assertSame(
            'approved',
            $period['status']
        );


        self::assertNull(
            $period['locked_by_user_id']
        );
    }


    public function testApprovedPeriodCanBeReopenedWithReason(): void
    {
        $payrollPeriodId =
            $this->createApprovedPeriod();


        $period =
            $this->service
                ->reopen(
                    $payrollPeriodId,
                    1,
                    'Correct an employee time-entry error.'
                );


        self::assertSame(
            'under_review',
            $period['status']
        );


        self::assertSame(
            1,
            (int)$period['reviewed_by_user_id']
        );


        self::assertNotEmpty(
            $period['review_started_at']
        );


        self::assertNull(
            $period['approved_by_user_id']
        );


        self::assertNull(
            $period['approved_at']
        );


        self::assertNull(
            $period['locked_by_user_id']
        );


        self::assertNull(
            $period['locked_at']
        );


        $history =
            $this->historyRepository
                ->latestForPeriod(
                    $payrollPeriodId
                );


        self::assertNotNull(
            $history
        );


        self::assertSame(
            'reopened',
            $history['action']
        );


        self::assertSame(
            'approved',
            $history['previous_status']
        );


        self::assertSame(
            'under_review',
            $history['new_status']
        );


        self::assertSame(
            'Correct an employee time-entry error.',
            $history['reason']
        );
    }


    public function testLockedPeriodCanBeReopenedWithReason(): void
    {
        $payrollPeriodId =
            $this->createLockedPeriod();


        $period =
            $this->service
                ->reopen(
                    $payrollPeriodId,
                    2,
                    'Resolve a payroll problem found after locking.'
                );


        self::assertSame(
            'under_review',
            $period['status']
        );


        self::assertSame(
            2,
            (int)$period['reviewed_by_user_id']
        );


        self::assertNull(
            $period['approved_by_user_id']
        );


        self::assertNull(
            $period['approved_at']
        );


        self::assertNull(
            $period['locked_by_user_id']
        );


        self::assertNull(
            $period['locked_at']
        );


        $history =
            $this->historyRepository
                ->latestForPeriod(
                    $payrollPeriodId
                );


        self::assertNotNull(
            $history
        );


        self::assertSame(
            'reopened',
            $history['action']
        );


        self::assertSame(
            'locked',
            $history['previous_status']
        );


        self::assertSame(
            'under_review',
            $history['new_status']
        );


        self::assertSame(
            'Resolve a payroll problem found after locking.',
            $history['reason']
        );
    }


    public function testReopenRejectsShortReason(): void
    {
        $payrollPeriodId =
            $this->createApprovedPeriod();


        $this->expectException(
            InvalidArgumentException::class
        );


        $this->expectExceptionMessage(
            'at least 10 characters'
        );


        $this->service
            ->reopen(
                $payrollPeriodId,
                1,
                'Too short'
            );
    }


    public function testWorkflowTransitionRollsBackWhenHistoryInsertFails(): void
    {
        $payrollPeriodId =
            $this->createOpenPeriod();


        $this->db->exec(
            "
            CREATE TRIGGER fail_payroll_workflow_history_insert

            BEFORE INSERT
            ON payroll_period_history

            BEGIN
                SELECT RAISE(
                    ABORT,
                    'forced workflow history failure'
                );
            END
            "
        );


        try {

            $this->service
                ->beginReview(
                    $payrollPeriodId,
                    1
                );


            self::fail(
                'The workflow transition succeeded despite a history failure.'
            );

        } catch (Throwable $exception) {

            self::assertStringContainsString(
                'forced workflow history failure',
                $exception->getMessage()
            );
        }


        $period =
            $this->payrollPeriodRepository
                ->find(
                    $payrollPeriodId
                );


        self::assertNotNull(
            $period
        );


        self::assertSame(
            'open',
            $period['status']
        );


        self::assertNull(
            $period['reviewed_by_user_id']
        );


        self::assertNull(
            $period['review_started_at']
        );


        self::assertSame(
            1,
            $this->historyRepository
                ->countForPeriod(
                    $payrollPeriodId
                )
        );


        self::assertFalse(
            $this->db->inTransaction()
        );
    }


    private function createOpenPeriod(): int
    {
        $payrollPeriodId =
            $this->payrollPeriodRepository
                ->create(
                    [
                        'period_name' =>
                            'Weekly Payroll',

                        'start_date' =>
                            '2026-07-13',

                        'end_date' =>
                            '2026-07-19',

                        'created_by_user_id' =>
                            1
                    ]
                );


        $this->historyRepository
            ->create(
                [
                    'payroll_period_id' =>
                        $payrollPeriodId,

                    'action' =>
                        'created',

                    'previous_status' =>
                        null,

                    'new_status' =>
                        'open',

                    'reason' =>
                        null,

                    'user_id' =>
                        1
                ]
            );


        return $payrollPeriodId;
    }


    private function createUnderReviewPeriod(): int
    {
        $payrollPeriodId =
            $this->createOpenPeriod();


        $this->service
            ->beginReview(
                $payrollPeriodId,
                1
            );


        return $payrollPeriodId;
    }


    private function createApprovedPeriod(): int
    {
        $payrollPeriodId =
            $this->createUnderReviewPeriod();


        $this->service
            ->approve(
                $payrollPeriodId,
                2,
                true
            );


        return $payrollPeriodId;
    }


    private function createLockedPeriod(): int
    {
        $payrollPeriodId =
            $this->createApprovedPeriod();


        $this->service
            ->lock(
                $payrollPeriodId,
                1,
                'LOCK'
            );


        return $payrollPeriodId;
    }


    private function createUsers(): void
    {
        $this->db->exec(
            "
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
                    'test-supervisor',
                    'supervisor@example.test',
                    'supervisor',
                    1
                ),

                (
                    2,
                    'test-admin',
                    'admin@example.test',
                    'admin',
                    1
                ),

                (
                    3,
                    'unauthorized-user',
                    'user@example.test',
                    'employee',
                    1
                ),

                (
                    4,
                    'inactive-supervisor',
                    'inactive@example.test',
                    'supervisor',
                    0
                )
            "
        );
    }


    private function createSchema(): void
    {
        $this->db->exec(
            "
            CREATE TABLE users
            (
                id INTEGER PRIMARY KEY AUTOINCREMENT,

                username TEXT NOT NULL,

                email TEXT NULL,

                role TEXT NOT NULL,

                active INTEGER NOT NULL
                    DEFAULT 1
            )
            "
        );


        $this->db->exec(
            "
            CREATE TABLE employees
            (
                id INTEGER PRIMARY KEY AUTOINCREMENT,

                employee_number TEXT NOT NULL,

                first_name TEXT NOT NULL,

                last_name TEXT NOT NULL
            )
            "
        );


        $this->db->exec(
            "
            CREATE TABLE payroll_periods
            (
                id INTEGER PRIMARY KEY AUTOINCREMENT,

                period_name TEXT NOT NULL,

                start_date TEXT NOT NULL,

                end_date TEXT NOT NULL,

                status TEXT NOT NULL
                    DEFAULT 'open',

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

                FOREIGN KEY
                (
                    created_by_user_id
                )
                REFERENCES users(id)
                ON DELETE RESTRICT,

                FOREIGN KEY
                (
                    reviewed_by_user_id
                )
                REFERENCES users(id)
                ON DELETE RESTRICT,

                FOREIGN KEY
                (
                    approved_by_user_id
                )
                REFERENCES users(id)
                ON DELETE RESTRICT,

                FOREIGN KEY
                (
                    locked_by_user_id
                )
                REFERENCES users(id)
                ON DELETE RESTRICT,

                CHECK
                (
                    status IN
                    (
                        'open',
                        'under_review',
                        'approved',
                        'locked'
                    )
                ),

                CHECK
                (
                    start_date <= end_date
                )
            )
            "
        );


        $this->db->exec(
            "
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
                ON DELETE RESTRICT,

                CHECK
                (
                    previous_status IS NULL
                    OR
                    previous_status IN
                    (
                        'open',
                        'under_review',
                        'approved',
                        'locked'
                    )
                ),

                CHECK
                (
                    new_status IN
                    (
                        'open',
                        'under_review',
                        'approved',
                        'locked'
                    )
                )
            )
            "
        );


        $this->db->exec(
            "
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
                    DEFAULT 'open',

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
                    employee_id
                )
                REFERENCES employees(id)
                ON DELETE RESTRICT,

                FOREIGN KEY
                (
                    resolved_by_user_id
                )
                REFERENCES users(id)
                ON DELETE RESTRICT,

                CHECK
                (
                    resolution_status IN
                    (
                        'open',
                        'resolved',
                        'accepted'
                    )
                ),

                UNIQUE
                (
                    payroll_period_id,
                    exception_key
                )
            )
            "
        );
    }
}
