<?php
declare(strict_types=1);

use App\Repositories\PayrollPeriodHistoryRepository;
use App\Repositories\PayrollPeriodRepository;
use App\Services\PayrollPeriodService;
use PHPUnit\Framework\TestCase;

final class PayrollPeriodServiceTest extends TestCase
{
    private PDO $db;

    private PayrollPeriodRepository $payrollPeriodRepository;

    private PayrollPeriodHistoryRepository $historyRepository;

    private PayrollPeriodService $service;


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


        $this->db->exec(
            "
            INSERT INTO users
            (
                id,
                username,
                email
            )

            VALUES
            (
                1,
                'test-supervisor',
                'supervisor@example.test'
            )
            "
        );


        $this->payrollPeriodRepository =
            new PayrollPeriodRepository(
                $this->db
            );


        $this->historyRepository =
            new PayrollPeriodHistoryRepository(
                $this->db
            );


        $this->service =
            new PayrollPeriodService(
                $this->db,
                $this->payrollPeriodRepository,
                $this->historyRepository
            );
    }


    public function testValidateCreateDataNormalizesValidInput(): void
    {
        $validated =
            $this->service
                ->validateCreateData(
                    [
                        'period_name' =>
                            '  Weekly Payroll  ',

                        'start_date' =>
                            '2026-07-13',

                        'end_date' =>
                            '2026-07-19'
                    ]
                );


        self::assertSame(
            'Weekly Payroll',
            $validated['period_name']
        );


        self::assertSame(
            '2026-07-13',
            $validated['start_date']
        );


        self::assertSame(
            '2026-07-19',
            $validated['end_date']
        );


        self::assertSame(
            7,
            $validated['day_count']
        );
    }


    public function testValidateCreateDataRejectsInvalidDate(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );


        $this->expectExceptionMessage(
            'must use a valid YYYY-MM-DD date'
        );


        $this->service
            ->validateCreateData(
                [
                    'period_name' =>
                        'Invalid Date Period',

                    'start_date' =>
                        '2026-02-30',

                    'end_date' =>
                        '2026-03-07'
                ]
            );
    }


    public function testValidateCreateDataRejectsEndBeforeStart(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );


        $this->expectExceptionMessage(
            'end date must be on or after the start date'
        );


        $this->service
            ->validateCreateData(
                [
                    'period_name' =>
                        'Reversed Period',

                    'start_date' =>
                        '2026-07-19',

                    'end_date' =>
                        '2026-07-13'
                ]
            );
    }


    public function testValidateCreateDataRejectsPeriodLongerThanThirtyOneDays(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );


        $this->expectExceptionMessage(
            'may not exceed 31 calendar days'
        );


        $this->service
            ->validateCreateData(
                [
                    'period_name' =>
                        'Oversized Period',

                    'start_date' =>
                        '2026-01-01',

                    'end_date' =>
                        '2026-02-01'
                ]
            );
    }


    public function testCreateStoresPeriodAndImmutableCreationHistory(): void
    {
        $payrollPeriodId =
            $this->service
                ->create(
                    [
                        'period_name' =>
                            'Weekly Payroll',

                        'start_date' =>
                            '2026-07-13',

                        'end_date' =>
                            '2026-07-19'
                    ],
                    1
                );


        self::assertSame(
            1,
            $payrollPeriodId
        );


        $period =
            $this->payrollPeriodRepository
                ->find(
                    $payrollPeriodId
                );


        self::assertNotNull(
            $period
        );


        self::assertSame(
            'Weekly Payroll',
            $period['period_name']
        );


        self::assertSame(
            '2026-07-13',
            $period['start_date']
        );


        self::assertSame(
            '2026-07-19',
            $period['end_date']
        );


        self::assertSame(
            'open',
            $period['status']
        );


        self::assertSame(
            'test-supervisor',
            $period['created_by_username']
        );


        $history =
            $this->historyRepository
                ->allForPeriod(
                    $payrollPeriodId
                );


        self::assertCount(
            1,
            $history
        );


        self::assertSame(
            'created',
            $history[0]['action']
        );


        self::assertNull(
            $history[0]['previous_status']
        );


        self::assertSame(
            'open',
            $history[0]['new_status']
        );


        self::assertNull(
            $history[0]['reason']
        );


        self::assertSame(
            1,
            (int)$history[0]['user_id']
        );


        self::assertSame(
            'test-supervisor',
            $history[0]['user_username']
        );


        self::assertFalse(
            $this->db->inTransaction()
        );
    }


    public function testCreateRejectsOverlappingPayrollPeriod(): void
    {
        $firstPeriodId =
            $this->service
                ->create(
                    [
                        'period_name' =>
                            'First Weekly Payroll',

                        'start_date' =>
                            '2026-07-13',

                        'end_date' =>
                            '2026-07-19'
                    ],
                    1
                );


        self::assertSame(
            1,
            $firstPeriodId
        );


        try {

            $this->service
                ->create(
                    [
                        'period_name' =>
                            'Overlapping Payroll',

                        'start_date' =>
                            '2026-07-19',

                        'end_date' =>
                            '2026-07-25'
                    ],
                    1
                );


            self::fail(
                'An overlapping payroll period was accepted.'
            );

        } catch (InvalidArgumentException $exception) {

            self::assertStringContainsString(
                'overlaps "First Weekly Payroll"',
                $exception->getMessage()
            );
        }


        self::assertSame(
            1,
            $this->payrollPeriodRepository
                ->count()
        );


        self::assertSame(
            1,
            $this->historyRepository
                ->countForPeriod(
                    $firstPeriodId
                )
        );


        self::assertFalse(
            $this->db->inTransaction()
        );
    }


    public function testCreateRollsBackWhenHistoryCannotBeRecorded(): void
    {
        $this->db->exec(
            "
            CREATE TRIGGER fail_payroll_period_history_insert

            BEFORE INSERT
            ON payroll_period_history

            BEGIN
                SELECT RAISE(
                    ABORT,
                    'forced history failure'
                );
            END
            "
        );


        try {

            $this->service
                ->create(
                    [
                        'period_name' =>
                            'Rollback Payroll',

                        'start_date' =>
                            '2026-07-20',

                        'end_date' =>
                            '2026-07-26'
                    ],
                    1
                );


            self::fail(
                'Payroll period creation did not fail when history insertion failed.'
            );

        } catch (Throwable $exception) {

            self::assertStringContainsString(
                'forced history failure',
                $exception->getMessage()
            );
        }


        self::assertSame(
            0,
            $this->payrollPeriodRepository
                ->count()
        );


        $historyCount =
            (int)$this->db
                ->query(
                    "
                    SELECT COUNT(*)
                    FROM payroll_period_history
                    "
                )
                ->fetchColumn();


        self::assertSame(
            0,
            $historyCount
        );


        self::assertFalse(
            $this->db->inTransaction()
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

                email TEXT NULL
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
    }
}
