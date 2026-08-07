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


    public function testUpdateDraftStoresChangesAndImmutableHistory(): void
    {
        $payrollPeriodId =
            $this->service
                ->create(
                    [
                        'period_name' =>
                            'Original Weekly Payroll',

                        'start_date' =>
                            '2026-07-13',

                        'end_date' =>
                            '2026-07-19'
                    ],
                    1
                );


        $updated =
            $this->service
                ->updateDraft(
                    $payrollPeriodId,
                    [
                        'period_name' =>
                            'Updated Weekly Payroll',

                        'start_date' =>
                            '2026-07-14',

                        'end_date' =>
                            '2026-07-20'
                    ],
                    1
                );


        self::assertTrue(
            $updated
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
            'Updated Weekly Payroll',
            $period['period_name']
        );

        self::assertSame(
            '2026-07-14',
            $period['start_date']
        );

        self::assertSame(
            '2026-07-20',
            $period['end_date']
        );


        $history =
            $this->historyRepository
                ->allForPeriod(
                    $payrollPeriodId
                );


        self::assertCount(
            2,
            $history
        );

        self::assertSame(
            'updated',
            $history[1]['action']
        );

        self::assertSame(
            'open',
            $history[1]['previous_status']
        );

        self::assertSame(
            'open',
            $history[1]['new_status']
        );

        self::assertSame(
            1,
            (int)$history[1]['user_id']
        );


        $reason =
            json_decode(
                (string)$history[1]['reason'],
                true
            );


        self::assertIsArray(
            $reason
        );

        self::assertSame(
            'Original Weekly Payroll',
            $reason['changes']['period_name']['from']
        );

        self::assertSame(
            'Updated Weekly Payroll',
            $reason['changes']['period_name']['to']
        );

        self::assertSame(
            '2026-07-13',
            $reason['changes']['start_date']['from']
        );

        self::assertSame(
            '2026-07-20',
            $reason['changes']['end_date']['to']
        );

        self::assertFalse(
            $this->db->inTransaction()
        );
    }


    public function testUpdateDraftDoesNotRecordNoOpHistory(): void
    {
        $payrollPeriodId =
            $this->service
                ->create(
                    [
                        'period_name' =>
                            'Unchanged Payroll',

                        'start_date' =>
                            '2026-08-03',

                        'end_date' =>
                            '2026-08-09'
                    ],
                    1
                );


        $updated =
            $this->service
                ->updateDraft(
                    $payrollPeriodId,
                    [
                        'period_name' =>
                            'Unchanged Payroll',

                        'start_date' =>
                            '2026-08-03',

                        'end_date' =>
                            '2026-08-09'
                    ],
                    1
                );


        self::assertFalse(
            $updated
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


    public function testUpdateDraftRejectsOverlapAndPreservesOriginal(): void
    {
        $this->createPeriod(
            'First Payroll',
            '2026-09-01',
            '2026-09-07'
        );

        $secondPeriodId =
            $this->createPeriod(
                'Second Payroll',
                '2026-09-08',
                '2026-09-14'
            );


        try {
            $this->service
                ->updateDraft(
                    $secondPeriodId,
                    [
                        'period_name' =>
                            'Overlapping Second Payroll',

                        'start_date' =>
                            '2026-09-07',

                        'end_date' =>
                            '2026-09-13'
                    ],
                    1
                );

            self::fail(
                'An overlapping payroll-period update was accepted.'
            );

        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString(
                'overlaps "First Payroll"',
                $exception->getMessage()
            );
        }


        $period =
            $this->payrollPeriodRepository
                ->find(
                    $secondPeriodId
                );


        self::assertNotNull(
            $period
        );

        self::assertSame(
            'Second Payroll',
            $period['period_name']
        );

        self::assertSame(
            '2026-09-08',
            $period['start_date']
        );

        self::assertSame(
            '2026-09-14',
            $period['end_date']
        );

        self::assertSame(
            1,
            $this->historyRepository
                ->countForPeriod(
                    $secondPeriodId
                )
        );

        self::assertFalse(
            $this->db->inTransaction()
        );
    }


    public function testUpdateDraftRejectsNonOpenPeriod(): void
    {
        $payrollPeriodId =
            $this->createPeriod(
                'Approved Payroll',
                '2026-10-01',
                '2026-10-07'
            );

        $this->db->exec(
            "
            UPDATE payroll_periods

            SET status =
                'approved'

            WHERE id =
                {$payrollPeriodId}
            "
        );


        try {
            $this->service
                ->updateDraft(
                    $payrollPeriodId,
                    [
                        'period_name' =>
                            'Changed Approved Payroll',

                        'start_date' =>
                            '2026-10-01',

                        'end_date' =>
                            '2026-10-07'
                    ],
                    1
                );

            self::fail(
                'A non-open payroll period was edited.'
            );

        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'Only active open payroll periods can be edited.',
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
            'Approved Payroll',
            $period['period_name']
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


    public function testUpdateDraftRejectsArchivedAndVoidedPeriods(): void
    {
        $this->db->exec(
            "
            ALTER TABLE payroll_periods
            ADD COLUMN archived_at DATETIME NULL
            "
        );

        $this->db->exec(
            "
            ALTER TABLE payroll_periods
            ADD COLUMN voided_at DATETIME NULL
            "
        );


        $archivedPeriodId =
            $this->createPeriod(
                'Archived Payroll',
                '2026-11-01',
                '2026-11-07'
            );

        $this->db->exec(
            "
            UPDATE payroll_periods

            SET archived_at =
                '2026-11-08 08:00:00'

            WHERE id =
                {$archivedPeriodId}
            "
        );


        try {
            $this->service
                ->updateDraft(
                    $archivedPeriodId,
                    [
                        'period_name' =>
                            'Changed Archived Payroll',

                        'start_date' =>
                            '2026-11-01',

                        'end_date' =>
                            '2026-11-07'
                    ],
                    1
                );

            self::fail(
                'An archived payroll period was edited.'
            );

        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'Archived payroll periods cannot be edited.',
                $exception->getMessage()
            );
        }


        $voidedPeriodId =
            $this->createPeriod(
                'Voided Payroll',
                '2026-11-08',
                '2026-11-14'
            );

        $this->db->exec(
            "
            UPDATE payroll_periods

            SET voided_at =
                '2026-11-15 08:00:00'

            WHERE id =
                {$voidedPeriodId}
            "
        );


        try {
            $this->service
                ->updateDraft(
                    $voidedPeriodId,
                    [
                        'period_name' =>
                            'Changed Voided Payroll',

                        'start_date' =>
                            '2026-11-08',

                        'end_date' =>
                            '2026-11-14'
                    ],
                    1
                );

            self::fail(
                'A voided payroll period was edited.'
            );

        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'Voided payroll periods cannot be edited.',
                $exception->getMessage()
            );
        }


        self::assertSame(
            1,
            $this->historyRepository
                ->countForPeriod(
                    $archivedPeriodId
                )
        );

        self::assertSame(
            1,
            $this->historyRepository
                ->countForPeriod(
                    $voidedPeriodId
                )
        );

        self::assertFalse(
            $this->db->inTransaction()
        );
    }


    public function testUpdateDraftRollsBackWhenHistoryCannotBeRecorded(): void
    {
        $payrollPeriodId =
            $this->createPeriod(
                'Rollback Update Payroll',
                '2026-12-01',
                '2026-12-07'
            );

        $this->db->exec(
            "
            CREATE TRIGGER fail_payroll_period_update_history

            BEFORE INSERT
            ON payroll_period_history

            WHEN NEW.action =
                'updated'

            BEGIN
                SELECT RAISE(
                    ABORT,
                    'forced update history failure'
                );
            END
            "
        );


        try {
            $this->service
                ->updateDraft(
                    $payrollPeriodId,
                    [
                        'period_name' =>
                            'Changed Rollback Payroll',

                        'start_date' =>
                            '2026-12-02',

                        'end_date' =>
                            '2026-12-08'
                    ],
                    1
                );

            self::fail(
                'The update did not fail when history insertion failed.'
            );

        } catch (Throwable $exception) {
            self::assertStringContainsString(
                'forced update history failure',
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
            'Rollback Update Payroll',
            $period['period_name']
        );

        self::assertSame(
            '2026-12-01',
            $period['start_date']
        );

        self::assertSame(
            '2026-12-07',
            $period['end_date']
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


    private function createPeriod(
        string $name,
        string $startDate,
        string $endDate
    ): int
    {
        return
            $this->service
                ->create(
                    [
                        'period_name' =>
                            $name,

                        'start_date' =>
                            $startDate,

                        'end_date' =>
                            $endDate
                    ],
                    1
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
