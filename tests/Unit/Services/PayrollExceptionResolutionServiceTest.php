<?php
declare(strict_types=1);

use App\Repositories\PayrollExceptionResolutionRepository;
use App\Repositories\PayrollPeriodHistoryRepository;
use App\Repositories\PayrollPeriodRepository;
use App\Services\PayrollExceptionResolutionService;
use PHPUnit\Framework\TestCase;

final class PayrollExceptionResolutionServiceTest extends TestCase
{
    private PDO $db;

    private PayrollPeriodRepository $payrollPeriodRepository;

    private PayrollExceptionResolutionRepository $exceptionRepository;

    private PayrollPeriodHistoryRepository $historyRepository;

    private PayrollExceptionResolutionService $service;


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

        $this->createUsersAndEmployee();


        $this->payrollPeriodRepository =
            new PayrollPeriodRepository(
                $this->db
            );


        $this->exceptionRepository =
            new PayrollExceptionResolutionRepository(
                $this->db
            );


        $this->historyRepository =
            new PayrollPeriodHistoryRepository(
                $this->db
            );


        $this->service =
            new PayrollExceptionResolutionService(
                $this->db,
                $this->payrollPeriodRepository,
                $this->exceptionRepository,
                $this->historyRepository
            );
    }


    public function testOpenExceptionCanBeResolvedWithHistory(): void
    {
        $payrollPeriodId =
            $this->createPeriod();


        $exceptionId =
            $this->createException(
                $payrollPeriodId,
                'missing_clock_out',
                '2026-07-20',
                'Employee clock-in is missing clock-out.'
            );


        $result =
            $this->service
                ->resolve(
                    $payrollPeriodId,
                    $exceptionId,
                    1,
                    'The missing clock-out punch was added.'
                );


        self::assertSame(
            'resolved',
            $result['resolution_status']
        );


        self::assertSame(
            'The missing clock-out punch was added.',
            $result['resolution_note']
        );


        self::assertSame(
            1,
            (int)$result['resolved_by_user_id']
        );


        self::assertNotEmpty(
            $result['resolved_at']
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
            'exception_resolved',
            $history['action']
        );


        self::assertSame(
            'open',
            $history['previous_status']
        );


        self::assertSame(
            'open',
            $history['new_status']
        );


        self::assertSame(
            1,
            (int)$history['user_id']
        );


        self::assertStringContainsString(
            sprintf(
                'Exception #%d',
                $exceptionId
            ),
            (string)$history['reason']
        );


        self::assertStringContainsString(
            'The missing clock-out punch was added.',
            (string)$history['reason']
        );
    }


    public function testOpenExceptionCanBeAcceptedWithRequiredExplanation(): void
    {
        $payrollPeriodId =
            $this->createPeriod(
                'under_review'
            );


        $exceptionId =
            $this->createException(
                $payrollPeriodId,
                'unmatched_meal',
                '2026-07-21',
                'Incomplete meal period.'
            );


        $result =
            $this->service
                ->accept(
                    $payrollPeriodId,
                    $exceptionId,
                    2,
                    'The employee confirmed that no meal break was taken.'
                );


        self::assertSame(
            'accepted',
            $result['resolution_status']
        );


        self::assertSame(
            'The employee confirmed that no meal break was taken.',
            $result['resolution_note']
        );


        self::assertSame(
            2,
            (int)$result['resolved_by_user_id']
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
            'exception_accepted',
            $history['action']
        );


        self::assertSame(
            'under_review',
            $history['previous_status']
        );


        self::assertSame(
            'under_review',
            $history['new_status']
        );


        self::assertStringContainsString(
            'The employee confirmed that no meal break was taken.',
            (string)$history['reason']
        );


        self::assertSame(
            0,
            $this->exceptionRepository
                ->countOpenForPeriod(
                    $payrollPeriodId
                )
        );


        self::assertSame(
            1,
            $this->exceptionRepository
                ->countForPeriod(
                    $payrollPeriodId
                )
        );
    }


    public function testAcceptanceExplanationMustContainAtLeastTenCharacters(): void
    {
        $payrollPeriodId =
            $this->createPeriod();


        $exceptionId =
            $this->createException(
                $payrollPeriodId,
                'payroll_calculation_warning',
                '2026-07-20',
                'Payroll calculation warning.'
            );


        try {

            $this->service
                ->accept(
                    $payrollPeriodId,
                    $exceptionId,
                    1,
                    'Too short'
                );


            self::fail(
                'A short acceptance explanation was accepted.'
            );

        } catch (\InvalidArgumentException $exception) {

            self::assertSame(
                'An acceptance explanation of at least 10 characters is required.',
                $exception->getMessage()
            );
        }


        $record =
            $this->exceptionRepository
                ->find(
                    $exceptionId
                );


        self::assertNotNull(
            $record
        );


        self::assertSame(
            'open',
            $record['resolution_status']
        );


        self::assertSame(
            0,
            $this->historyRepository
                ->countForPeriod(
                    $payrollPeriodId
                )
        );
    }


    public function testUnauthorizedRoleCannotResolveException(): void
    {
        $payrollPeriodId =
            $this->createPeriod();


        $exceptionId =
            $this->createException(
                $payrollPeriodId,
                'missing_clock_out',
                '2026-07-20',
                'Missing clock-out.'
            );


        try {

            $this->service
                ->resolve(
                    $payrollPeriodId,
                    $exceptionId,
                    3,
                    'Attempted unauthorized resolution.'
                );


            self::fail(
                'An unauthorized user resolved a payroll exception.'
            );

        } catch (\RuntimeException $exception) {

            self::assertSame(
                'The acting user is not authorized to update payroll exceptions.',
                $exception->getMessage()
            );
        }


        $record =
            $this->exceptionRepository
                ->find(
                    $exceptionId
                );


        self::assertNotNull(
            $record
        );


        self::assertSame(
            'open',
            $record['resolution_status']
        );


        self::assertSame(
            0,
            $this->historyRepository
                ->countForPeriod(
                    $payrollPeriodId
                )
        );
    }


    public function testInactiveSupervisorCannotResolveException(): void
    {
        $payrollPeriodId =
            $this->createPeriod();


        $exceptionId =
            $this->createException(
                $payrollPeriodId,
                'missing_clock_in',
                '2026-07-20',
                'Clock-out occurred without a clock-in.'
            );


        try {

            $this->service
                ->resolve(
                    $payrollPeriodId,
                    $exceptionId,
                    4,
                    'Attempted inactive-user resolution.'
                );


            self::fail(
                'An inactive supervisor resolved a payroll exception.'
            );

        } catch (\RuntimeException $exception) {

            self::assertSame(
                'The acting user is inactive.',
                $exception->getMessage()
            );
        }


        $record =
            $this->exceptionRepository
                ->find(
                    $exceptionId
                );


        self::assertNotNull(
            $record
        );


        self::assertSame(
            'open',
            $record['resolution_status']
        );


        self::assertSame(
            0,
            $this->historyRepository
                ->countForPeriod(
                    $payrollPeriodId
                )
        );
    }


    public function testApprovedAndLockedPeriodsRejectExceptionUpdates(): void
    {
        foreach (
            [
                'approved',
                'locked'
            ]
            as $status
        ) {
            $payrollPeriodId =
                $this->createPeriod(
                    $status
                );


            $exceptionId =
                $this->createException(
                    $payrollPeriodId,
                    'missing_clock_out',
                    '2026-07-20',
                    'Missing clock-out.'
                );


            try {

                $this->service
                    ->resolve(
                        $payrollPeriodId,
                        $exceptionId,
                        1,
                        'This update should be rejected.'
                    );


                self::fail(
                    sprintf(
                        'An exception was resolved in a %s payroll period.',
                        $status
                    )
                );

            } catch (\RuntimeException $exception) {

                self::assertSame(
                    'Payroll exceptions may be updated only while a payroll period is open or under review.',
                    $exception->getMessage()
                );
            }


            $record =
                $this->exceptionRepository
                    ->find(
                        $exceptionId
                    );


            self::assertNotNull(
                $record
            );


            self::assertSame(
                'open',
                $record['resolution_status']
            );


            self::assertSame(
                0,
                $this->historyRepository
                    ->countForPeriod(
                        $payrollPeriodId
                    )
            );
        }
    }


    public function testExceptionMustBelongToSpecifiedPayrollPeriod(): void
    {
        $firstPeriodId =
            $this->createPeriod();


        $secondPeriodId =
            $this->createPeriod();


        $exceptionId =
            $this->createException(
                $firstPeriodId,
                'unmatched_break',
                '2026-07-20',
                'Incomplete break period.'
            );


        try {

            $this->service
                ->resolve(
                    $secondPeriodId,
                    $exceptionId,
                    1,
                    'Attempted against the wrong period.'
                );


            self::fail(
                'An exception was resolved through the wrong payroll period.'
            );

        } catch (\RuntimeException $exception) {

            self::assertSame(
                'The payroll exception does not belong to this payroll period.',
                $exception->getMessage()
            );
        }


        $record =
            $this->exceptionRepository
                ->find(
                    $exceptionId
                );


        self::assertNotNull(
            $record
        );


        self::assertSame(
            'open',
            $record['resolution_status']
        );


        self::assertSame(
            0,
            $this->historyRepository
                ->countForPeriod(
                    $firstPeriodId
                )
        );


        self::assertSame(
            0,
            $this->historyRepository
                ->countForPeriod(
                    $secondPeriodId
                )
        );
    }


    public function testResolvedExceptionCannotBeResolvedAgain(): void
    {
        $payrollPeriodId =
            $this->createPeriod();


        $exceptionId =
            $this->createException(
                $payrollPeriodId,
                'missing_clock_out',
                '2026-07-20',
                'Missing clock-out.'
            );


        $firstResult =
            $this->service
                ->resolve(
                    $payrollPeriodId,
                    $exceptionId,
                    1,
                    'The missing punch was corrected.'
                );


        self::assertSame(
            'resolved',
            $firstResult['resolution_status']
        );


        try {

            $this->service
                ->resolve(
                    $payrollPeriodId,
                    $exceptionId,
                    2,
                    'Attempting a duplicate resolution.'
                );


            self::fail(
                'An already resolved exception was resolved again.'
            );

        } catch (\RuntimeException $exception) {

            self::assertSame(
                'This payroll exception is already resolved.',
                $exception->getMessage()
            );
        }


        self::assertSame(
            1,
            $this->historyRepository
                ->countForPeriod(
                    $payrollPeriodId
                )
        );


        $record =
            $this->exceptionRepository
                ->find(
                    $exceptionId
                );


        self::assertNotNull(
            $record
        );


        self::assertSame(
            'resolved',
            $record['resolution_status']
        );


        self::assertSame(
            'The missing punch was corrected.',
            $record['resolution_note']
        );
    }


    public function testHistoryFailureRollsBackExceptionResolution(): void
    {
        $payrollPeriodId =
            $this->createPeriod();


        $exceptionId =
            $this->createException(
                $payrollPeriodId,
                'missing_clock_out',
                '2026-07-20',
                'Missing clock-out.'
            );


        $this->db->exec(
            "
            CREATE TRIGGER fail_exception_history_insert

            BEFORE INSERT
            ON payroll_period_history

            BEGIN

                SELECT RAISE(
                    ABORT,
                    'forced exception history failure'
                );

            END
            "
        );


        try {

            $this->service
                ->resolve(
                    $payrollPeriodId,
                    $exceptionId,
                    1,
                    'This resolution must be rolled back.'
                );


            self::fail(
                'The exception remained resolved after history creation failed.'
            );

        } catch (\Throwable $exception) {

            self::assertStringContainsString(
                'forced exception history failure',
                $exception->getMessage()
            );
        }


        $record =
            $this->exceptionRepository
                ->find(
                    $exceptionId
                );


        self::assertNotNull(
            $record
        );


        self::assertSame(
            'open',
            $record['resolution_status']
        );


        self::assertNull(
            $record['resolution_note']
        );


        self::assertNull(
            $record['resolved_by_user_id']
        );


        self::assertSame(
            0,
            $this->historyRepository
                ->countForPeriod(
                    $payrollPeriodId
                )
        );


        self::assertFalse(
            $this->db
                ->inTransaction()
        );
    }


    private function createPeriod(
        string $status = 'open'
    ): int
    {
        $payrollPeriodId =
            $this->payrollPeriodRepository
                ->create(
                    [
                        'period_name' =>
                            'Weekly Payroll',

                        'start_date' =>
                            '2026-07-20',

                        'end_date' =>
                            '2026-07-26',

                        'created_by_user_id' =>
                            1
                    ]
                );


        if ($status !== 'open') {

            $statement =
                $this->db->prepare(
                    "
                    UPDATE payroll_periods

                    SET
                        status =
                            :status,

                        updated_at =
                            CURRENT_TIMESTAMP

                    WHERE id =
                        :id
                    "
                );


            $statement->execute(
                [
                    'status' =>
                        $status,

                    'id' =>
                        $payrollPeriodId
                ]
            );
        }


        return $payrollPeriodId;
    }


    private function createException(
        int $payrollPeriodId,
        string $exceptionType,
        string $exceptionDate,
        string $description
    ): int
    {
        $exceptionId =
            $this->exceptionRepository
                ->createOrRefresh(
                    [
                        'payroll_period_id' =>
                            $payrollPeriodId,

                        'employee_id' =>
                            1,

                        'exception_key' =>
                            sprintf(
                                'test:%d:%s:%s',
                                $payrollPeriodId,
                                $exceptionDate,
                                $exceptionType
                            ),

                        'exception_type' =>
                            $exceptionType,

                        'exception_date' =>
                            $exceptionDate,

                        'description' =>
                            $description
                    ]
                );


        self::assertGreaterThan(
            0,
            $exceptionId
        );


        return $exceptionId;
    }


    private function createUsersAndEmployee(): void
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
                    'employee@example.test',
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


        $this->db->exec(
            "
            INSERT INTO employees
            (
                id,
                employee_number,
                first_name,
                last_name
            )

            VALUES
            (
                1,
                '1001',
                'Test',
                'Employee'
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
                ON DELETE RESTRICT
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

                UNIQUE
                (
                    payroll_period_id,
                    exception_key
                ),

                CHECK
                (
                    resolution_status IN
                    (
                        'open',
                        'resolved',
                        'accepted'
                    )
                )
            )
            "
        );
    }
}
