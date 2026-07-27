<?php
declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Repositories\EmployeeRepository;
use App\Repositories\PayrollPeriodRepository;
use App\Repositories\PunchCorrectionHistoryRepository;
use App\Repositories\PunchRepository;
use App\Services\PayrollPeriodProtectionService;
use App\Services\PunchCorrectionService;
use PDO;
use PHPUnit\Framework\TestCase;

final class PunchCorrectionServiceTest extends TestCase
{
    private PDO $database;

    private PayrollPeriodRepository $payrollPeriodRepository;

    private PunchCorrectionService $service;


    protected function setUp(): void
    {
        $this->database =
            new PDO(
                'sqlite::memory:'
            );


        $this->database->setAttribute(
            PDO::ATTR_ERRMODE,
            PDO::ERRMODE_EXCEPTION
        );


        $this->database->setAttribute(
            PDO::ATTR_DEFAULT_FETCH_MODE,
            PDO::FETCH_ASSOC
        );


        $this->database->exec(
            'PRAGMA foreign_keys = ON'
        );


        $this->createSchema();

        $this->seedUser();

        $this->seedEmployee();


        $punches =
            new PunchRepository(
                $this->database
            );


        $history =
            new PunchCorrectionHistoryRepository(
                $this->database
            );


        $employees =
            new EmployeeRepository(
                $this->database
            );


        $this->payrollPeriodRepository =
            new PayrollPeriodRepository(
                $this->database
            );


        $payrollPeriodProtection =
            new PayrollPeriodProtectionService(
                $this->payrollPeriodRepository
            );


        $this->service =
            new PunchCorrectionService(
                $this->database,
                $punches,
                $history,
                $employees,
                'America/Los_Angeles',
                $payrollPeriodProtection
            );
    }


    public function testCreatesManualPunchAndConvertsLocalTimeToUtc(): void
    {
        $result =
            $this->service->create(
                10,
                [
                    'punch_time' =>
                        '2026-01-05T08:00',

                    'punch_type' =>
                        'clock_in',

                    'notes' =>
                        'Test manual punch',

                    'reason' =>
                        'Testing manual punch creation'
                ],
                7
            );


        self::assertTrue(
            $result['success']
        );


        $punch =
            $this->database
                ->query(
                    "
                    SELECT *
                    FROM punches
                    LIMIT 1
                    "
                )
                ->fetch();


        self::assertIsArray(
            $punch
        );


        self::assertSame(
            '2026-01-05 16:00:00',
            $punch['punch_time']
        );


        self::assertSame(
            'clock_in',
            $punch['punch_type']
        );


        self::assertSame(
            'manual',
            $punch['source']
        );


        self::assertSame(
            'Test manual punch',
            $punch['notes']
        );


        self::assertSame(
            7,
            (int)$punch['corrected_by_user_id']
        );


        self::assertSame(
            'Testing manual punch creation',
            $punch['correction_reason']
        );


        $history =
            $this->database
                ->query(
                    "
                    SELECT *
                    FROM punch_correction_history
                    LIMIT 1
                    "
                )
                ->fetch();


        self::assertIsArray(
            $history
        );


        self::assertSame(
            'created',
            $history['action']
        );


        self::assertSame(
            '2026-01-05 16:00:00',
            $history['new_punch_time']
        );


        self::assertSame(
            'clock_in',
            $history['new_punch_type']
        );


        self::assertSame(
            7,
            (int)$history['user_id']
        );
    }


    public function testUpdatesPunchAndPreservesOriginalValuesInHistory(): void
    {
        $clockInId =
            $this->insertPunch(
                '2026-01-05 16:00:00',
                'clock_in'
            );


        $clockOutId =
            $this->insertPunch(
                '2026-01-06 01:00:00',
                'clock_out'
            );


        self::assertGreaterThan(
            0,
            $clockInId
        );


        $result =
            $this->service->update(
                $clockOutId,
                [
                    'punch_time' =>
                        '2026-01-05T17:30',

                    'punch_type' =>
                        'clock_out',

                    'notes' =>
                        'Corrected test clock-out',

                    'reason' =>
                        'Testing supervisor punch editing'
                ],
                7
            );


        self::assertTrue(
            $result['success']
        );


        $punch =
            $this->punchById(
                $clockOutId
            );


        self::assertSame(
            '2026-01-06 01:30:00',
            $punch['punch_time']
        );


        self::assertSame(
            'clock_out',
            $punch['punch_type']
        );


        self::assertSame(
            'manual',
            $punch['source']
        );


        self::assertSame(
            'Corrected test clock-out',
            $punch['notes']
        );


        $history =
            $this->historyByPunchId(
                $clockOutId
            );


        self::assertSame(
            'updated',
            $history['action']
        );


        self::assertSame(
            '2026-01-06 01:00:00',
            $history['old_punch_time']
        );


        self::assertSame(
            'clock_out',
            $history['old_punch_type']
        );


        self::assertSame(
            '2026-01-06 01:30:00',
            $history['new_punch_time']
        );


        self::assertSame(
            'clock_out',
            $history['new_punch_type']
        );
    }


    public function testDeletesPunchAndPreservesDeletionHistory(): void
    {
        $this->insertPunch(
            '2026-01-05 16:00:00',
            'clock_in'
        );


        $clockOutId =
            $this->insertPunch(
                '2026-01-06 01:00:00',
                'clock_out'
            );


        $result =
            $this->service->delete(
                $clockOutId,
                'Testing protected punch deletion',
                7
            );


        self::assertTrue(
            $result['success']
        );


        self::assertSame(
            0,
            $this->countRows(
                'punches',
                'id = '
                .
                $clockOutId
            )
        );


        $history =
            $this->historyByPunchId(
                $clockOutId
            );


        self::assertSame(
            'deleted',
            $history['action']
        );


        self::assertSame(
            '2026-01-06 01:00:00',
            $history['old_punch_time']
        );


        self::assertSame(
            'clock_out',
            $history['old_punch_type']
        );


        self::assertNull(
            $history['new_punch_time']
        );


        self::assertNull(
            $history['new_punch_type']
        );


        self::assertSame(
            'Testing protected punch deletion',
            $history['reason']
        );
    }


    public function testRejectsMissingCorrectionReason(): void
    {
        $result =
            $this->service->create(
                10,
                [
                    'punch_time' =>
                        '2026-01-05T08:00',

                    'punch_type' =>
                        'clock_in',

                    'notes' =>
                        '',

                    'reason' =>
                        ''
                ],
                7
            );


        self::assertFalse(
            $result['success']
        );


        self::assertSame(
            'A correction reason is required.',
            $result['errors']['reason']
        );


        self::assertSame(
            0,
            $this->countRows(
                'punches'
            )
        );


        self::assertSame(
            0,
            $this->countRows(
                'punch_correction_history'
            )
        );
    }


    public function testRejectsInvalidFirstPunchAndRollsBackCreation(): void
    {
        $result =
            $this->service->create(
                10,
                [
                    'punch_time' =>
                        '2026-01-05T17:00',

                    'punch_type' =>
                        'clock_out',

                    'notes' =>
                        '',

                    'reason' =>
                        'Testing invalid punch sequence'
                ],
                7
            );


        self::assertFalse(
            $result['success']
        );


        self::assertArrayHasKey(
            'sequence',
            $result['errors']
        );


        self::assertStringContainsString(
            'Clock Out must follow Clock In.',
            $result['errors']['sequence']
        );


        self::assertSame(
            0,
            $this->countRows(
                'punches'
            )
        );


        self::assertSame(
            0,
            $this->countRows(
                'punch_correction_history'
            )
        );
    }


    public function testInvalidUpdateRollsBackToOriginalPunch(): void
    {
        $this->insertPunch(
            '2026-01-05 16:00:00',
            'clock_in'
        );


        $clockOutId =
            $this->insertPunch(
                '2026-01-06 01:00:00',
                'clock_out'
            );


        $result =
            $this->service->update(
                $clockOutId,
                [
                    'punch_time' =>
                        '2026-01-05T17:00',

                    'punch_type' =>
                        'meal_in',

                    'notes' =>
                        'Invalid test update',

                    'reason' =>
                        'Testing invalid update rollback'
                ],
                7
            );


        self::assertFalse(
            $result['success']
        );


        self::assertArrayHasKey(
            'sequence',
            $result['errors']
        );


        $punch =
            $this->punchById(
                $clockOutId
            );


        self::assertSame(
            '2026-01-06 01:00:00',
            $punch['punch_time']
        );


        self::assertSame(
            'clock_out',
            $punch['punch_type']
        );


        self::assertSame(
            'kiosk',
            $punch['source']
        );


        self::assertSame(
            0,
            $this->countRows(
                'punch_correction_history'
            )
        );
    }


    public function testDeletionThatBreaksSequenceIsRolledBack(): void
    {
        $clockInId =
            $this->insertPunch(
                '2026-01-05 16:00:00',
                'clock_in'
            );


        $clockOutId =
            $this->insertPunch(
                '2026-01-06 01:00:00',
                'clock_out'
            );


        $result =
            $this->service->delete(
                $clockInId,
                'Testing invalid deletion rollback',
                7
            );


        self::assertFalse(
            $result['success']
        );


        self::assertArrayHasKey(
            'sequence',
            $result['errors']
        );


        self::assertSame(
            2,
            $this->countRows(
                'punches'
            )
        );


        self::assertIsArray(
            $this->punchById(
                $clockInId
            )
        );


        self::assertIsArray(
            $this->punchById(
                $clockOutId
            )
        );


        self::assertSame(
            0,
            $this->countRows(
                'punch_correction_history'
            )
        );
    }


    public function testCreateIsBlockedInsideApprovedPayrollPeriod(): void
    {
        $this->createPayrollPeriod(
            'Approved Weekly Payroll',
            '2026-01-05',
            '2026-01-11',
            'approved'
        );


        $result =
            $this->service->create(
                10,
                [
                    'punch_time' =>
                        '2026-01-05T08:00',

                    'punch_type' =>
                        'clock_in',

                    'notes' =>
                        'Protected creation attempt',

                    'reason' =>
                        'Testing approved payroll protection'
                ],
                7
            );


        self::assertFalse(
            $result['success']
        );


        self::assertArrayHasKey(
            'punch',
            $result['errors']
        );


        self::assertStringContainsString(
            'approved payroll period',
            $result['errors']['punch']
        );


        self::assertStringContainsString(
            '"Approved Weekly Payroll"',
            $result['errors']['punch']
        );


        self::assertStringContainsString(
            'Reopen the payroll period before making corrections.',
            $result['errors']['punch']
        );


        self::assertSame(
            0,
            $this->countRows(
                'punches'
            )
        );


        self::assertSame(
            0,
            $this->countRows(
                'punch_correction_history'
            )
        );
    }


    public function testUpdateIsBlockedWhenOriginalPunchIsInsideLockedPeriod(): void
    {
        $this->insertPunch(
            '2026-01-05 16:00:00',
            'clock_in'
        );


        $clockOutId =
            $this->insertPunch(
                '2026-01-06 01:00:00',
                'clock_out'
            );


        $this->createPayrollPeriod(
            'Locked Weekly Payroll',
            '2026-01-05',
            '2026-01-11',
            'locked'
        );


        $result =
            $this->service->update(
                $clockOutId,
                [
                    'punch_time' =>
                        '2026-01-05T17:30',

                    'punch_type' =>
                        'clock_out',

                    'notes' =>
                        'Blocked locked-period update',

                    'reason' =>
                        'Testing locked payroll protection'
                ],
                7
            );


        self::assertFalse(
            $result['success']
        );


        self::assertArrayHasKey(
            'punch',
            $result['errors']
        );


        self::assertStringContainsString(
            'locked payroll period',
            $result['errors']['punch']
        );


        $punch =
            $this->punchById(
                $clockOutId
            );


        self::assertSame(
            '2026-01-06 01:00:00',
            $punch['punch_time']
        );


        self::assertSame(
            'kiosk',
            $punch['source']
        );


        self::assertSame(
            0,
            $this->countRows(
                'punch_correction_history'
            )
        );
    }


    public function testUpdateCannotMoveEditablePunchIntoApprovedPeriod(): void
    {
        $this->insertPunch(
            '2026-01-12 16:00:00',
            'clock_in'
        );


        $clockOutId =
            $this->insertPunch(
                '2026-01-13 01:00:00',
                'clock_out'
            );


        $this->createPayrollPeriod(
            'Approved Weekly Payroll',
            '2026-01-05',
            '2026-01-11',
            'approved'
        );


        $result =
            $this->service->update(
                $clockOutId,
                [
                    'punch_time' =>
                        '2026-01-05T17:00',

                    'punch_type' =>
                        'clock_out',

                    'notes' =>
                        'Attempt to move into approved period',

                    'reason' =>
                        'Testing protected destination date'
                ],
                7
            );


        self::assertFalse(
            $result['success']
        );


        self::assertStringContainsString(
            'approved payroll period',
            $result['errors']['punch']
        );


        $punch =
            $this->punchById(
                $clockOutId
            );


        self::assertSame(
            '2026-01-13 01:00:00',
            $punch['punch_time']
        );


        self::assertSame(
            0,
            $this->countRows(
                'punch_correction_history'
            )
        );
    }


    public function testDeleteIsBlockedInsideLockedPayrollPeriod(): void
    {
        $this->insertPunch(
            '2026-01-05 16:00:00',
            'clock_in'
        );


        $clockOutId =
            $this->insertPunch(
                '2026-01-06 01:00:00',
                'clock_out'
            );


        $this->createPayrollPeriod(
            'Final Locked Payroll',
            '2026-01-05',
            '2026-01-11',
            'locked'
        );


        $result =
            $this->service->delete(
                $clockOutId,
                'Testing locked-period deletion protection',
                7
            );


        self::assertFalse(
            $result['success']
        );


        self::assertArrayHasKey(
            'punch',
            $result['errors']
        );


        self::assertStringContainsString(
            'locked payroll period',
            $result['errors']['punch']
        );


        self::assertSame(
            2,
            $this->countRows(
                'punches'
            )
        );


        self::assertSame(
            0,
            $this->countRows(
                'punch_correction_history'
            )
        );
    }


    public function testCorrectionIsAllowedAfterLockedPeriodIsReopened(): void
    {
        $this->insertPunch(
            '2026-01-05 16:00:00',
            'clock_in'
        );


        $clockOutId =
            $this->insertPunch(
                '2026-01-06 01:00:00',
                'clock_out'
            );


        $payrollPeriodId =
            $this->createPayrollPeriod(
                'Reopened Weekly Payroll',
                '2026-01-05',
                '2026-01-11',
                'locked'
            );


        $blockedResult =
            $this->service->delete(
                $clockOutId,
                'Confirm deletion is initially blocked',
                7
            );


        self::assertFalse(
            $blockedResult['success']
        );


        $this->reopenPayrollPeriod(
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
            'under_review',
            $period['status']
        );


        $result =
            $this->service->delete(
                $clockOutId,
                'Correct payroll after reopening period',
                7
            );


        self::assertTrue(
            $result['success']
        );


        self::assertSame(
            1,
            $this->countRows(
                'punches'
            )
        );


        $history =
            $this->historyByPunchId(
                $clockOutId
            );


        self::assertSame(
            'deleted',
            $history['action']
        );


        self::assertSame(
            'Correct payroll after reopening period',
            $history['reason']
        );
    }


    private function createSchema(): void
    {
        $this->database->exec(
            "
            CREATE TABLE users
            (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT 'supervisor',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                email TEXT,
                active INTEGER NOT NULL DEFAULT 1,
                last_login DATETIME
            )
            "
        );


        $this->database->exec(
            "
            CREATE TABLE employees
            (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                employee_number TEXT NOT NULL UNIQUE,
                first_name TEXT NOT NULL,
                last_name TEXT NOT NULL,
                pin_hash TEXT NOT NULL,
                department TEXT NOT NULL DEFAULT '',
                active INTEGER NOT NULL DEFAULT 1,
                notes TEXT NOT NULL DEFAULT '',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
            "
        );


        $this->database->exec(
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


        $this->database->exec(
            "
            CREATE INDEX idx_payroll_periods_dates

            ON payroll_periods
            (
                start_date,
                end_date
            )
            "
        );


        $this->database->exec(
            "
            CREATE INDEX idx_payroll_periods_status

            ON payroll_periods
            (
                status
            )
            "
        );


        $this->database->exec(
            "
            CREATE TABLE punches
            (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                employee_id INTEGER NOT NULL,
                punch_time DATETIME NOT NULL,
                punch_type TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                source TEXT NOT NULL DEFAULT 'kiosk',
                notes TEXT NOT NULL DEFAULT '',
                corrected_at DATETIME DEFAULT NULL,
                corrected_by_user_id INTEGER DEFAULT NULL,
                correction_reason TEXT NOT NULL DEFAULT '',

                FOREIGN KEY(employee_id)
                    REFERENCES employees(id)
            )
            "
        );


        $this->database->exec(
            "
            CREATE TABLE punch_correction_history
            (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                punch_id INTEGER,
                employee_id INTEGER NOT NULL,
                employee_number TEXT NOT NULL DEFAULT '',
                employee_name TEXT NOT NULL DEFAULT '',
                action TEXT NOT NULL,
                old_punch_time DATETIME,
                old_punch_type TEXT,
                new_punch_time DATETIME,
                new_punch_type TEXT,
                reason TEXT NOT NULL,
                user_id INTEGER,
                created_at DATETIME NOT NULL
                    DEFAULT CURRENT_TIMESTAMP
            )
            "
        );
    }


    private function seedUser(): void
    {
        $statement =
            $this->database->prepare(
                "
                INSERT INTO users
                (
                    id,
                    username,
                    password_hash,
                    role,
                    active
                )

                VALUES
                (
                    7,
                    'supervisor',
                    'test-hash',
                    'supervisor',
                    1
                )
                "
            );


        $statement->execute();
    }


    private function seedEmployee(): void
    {
        $statement =
            $this->database->prepare(
                "
                INSERT INTO employees
                (
                    id,
                    employee_number,
                    first_name,
                    last_name,
                    pin_hash,
                    department,
                    active,
                    notes
                )

                VALUES
                (
                    10,
                    'TEST-PUNCH',
                    'Punch',
                    'Test',
                    'test-pin-hash',
                    'Testing',
                    1,
                    ''
                )
                "
            );


        $statement->execute();
    }


    private function createPayrollPeriod(
        string $periodName,
        string $startDate,
        string $endDate,
        string $status
    ): int
    {
        $reviewedByUserId =
            in_array(
                $status,
                [
                    'under_review',
                    'approved',
                    'locked'
                ],
                true
            )
                ? 7
                : null;


        $reviewStartedAt =
            $reviewedByUserId === null
                ? null
                : '2026-01-12 12:00:00';


        $approvedByUserId =
            in_array(
                $status,
                [
                    'approved',
                    'locked'
                ],
                true
            )
                ? 7
                : null;


        $approvedAt =
            $approvedByUserId === null
                ? null
                : '2026-01-12 13:00:00';


        $lockedByUserId =
            $status === 'locked'
                ? 7
                : null;


        $lockedAt =
            $status === 'locked'
                ? '2026-01-12 14:00:00'
                : null;


        $statement =
            $this->database->prepare(
                "
                INSERT INTO payroll_periods
                (
                    period_name,
                    start_date,
                    end_date,
                    status,
                    created_by_user_id,
                    reviewed_by_user_id,
                    approved_by_user_id,
                    locked_by_user_id,
                    review_started_at,
                    approved_at,
                    locked_at
                )

                VALUES
                (
                    :period_name,
                    :start_date,
                    :end_date,
                    :status,
                    7,
                    :reviewed_by_user_id,
                    :approved_by_user_id,
                    :locked_by_user_id,
                    :review_started_at,
                    :approved_at,
                    :locked_at
                )
                "
            );


        $statement->execute(
            [
                'period_name' =>
                    $periodName,

                'start_date' =>
                    $startDate,

                'end_date' =>
                    $endDate,

                'status' =>
                    $status,

                'reviewed_by_user_id' =>
                    $reviewedByUserId,

                'approved_by_user_id' =>
                    $approvedByUserId,

                'locked_by_user_id' =>
                    $lockedByUserId,

                'review_started_at' =>
                    $reviewStartedAt,

                'approved_at' =>
                    $approvedAt,

                'locked_at' =>
                    $lockedAt
            ]
        );


        return
            (int)$this->database
                ->lastInsertId();
    }


    private function reopenPayrollPeriod(
        int $payrollPeriodId
    ): void
    {
        $statement =
            $this->database->prepare(
                "
                UPDATE payroll_periods

                SET
                    status =
                        'under_review',

                    reviewed_by_user_id =
                        7,

                    review_started_at =
                        CURRENT_TIMESTAMP,

                    approved_by_user_id =
                        NULL,

                    approved_at =
                        NULL,

                    locked_by_user_id =
                        NULL,

                    locked_at =
                        NULL,

                    updated_at =
                        CURRENT_TIMESTAMP

                WHERE id =
                    :id

                  AND status IN
                  (
                      'approved',
                      'locked'
                  )
                "
            );


        $statement->execute(
            [
                'id' =>
                    $payrollPeriodId
            ]
        );


        self::assertSame(
            1,
            $statement->rowCount()
        );
    }


    private function insertPunch(
        string $punchTime,
        string $punchType
    ): int
    {
        $statement =
            $this->database->prepare(
                "
                INSERT INTO punches
                (
                    employee_id,
                    punch_time,
                    punch_type,
                    source,
                    notes,
                    correction_reason
                )

                VALUES
                (
                    10,
                    :punch_time,
                    :punch_type,
                    'kiosk',
                    '',
                    ''
                )
                "
            );


        $statement->execute(
            [
                'punch_time' =>
                    $punchTime,

                'punch_type' =>
                    $punchType
            ]
        );


        return
            (int)$this->database
                ->lastInsertId();
    }


    /**
     * @return array<string,mixed>
     */
    private function punchById(
        int $id
    ): array
    {
        $statement =
            $this->database->prepare(
                "
                SELECT *
                FROM punches
                WHERE id = :id
                "
            );


        $statement->execute(
            [
                'id' =>
                    $id
            ]
        );


        $punch =
            $statement->fetch();


        self::assertIsArray(
            $punch
        );


        return $punch;
    }


    /**
     * @return array<string,mixed>
     */
    private function historyByPunchId(
        int $punchId
    ): array
    {
        $statement =
            $this->database->prepare(
                "
                SELECT *
                FROM punch_correction_history
                WHERE punch_id = :punch_id
                ORDER BY id DESC
                LIMIT 1
                "
            );


        $statement->execute(
            [
                'punch_id' =>
                    $punchId
            ]
        );


        $history =
            $statement->fetch();


        self::assertIsArray(
            $history
        );


        return $history;
    }


    private function countRows(
        string $table,
        ?string $condition = null
    ): int
    {
        $sql =
            'SELECT COUNT(*) FROM '
            .
            $table;


        if (
            $condition !== null
            &&
            $condition !== ''
        ) {
            $sql .=
                ' WHERE '
                .
                $condition;
        }


        return
            (int)$this->database
                ->query(
                    $sql
                )
                ->fetchColumn();
    }
}
