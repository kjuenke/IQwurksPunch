<?php
declare(strict_types=1);

use App\Repositories\PayrollExceptionResolutionRepository;
use App\Repositories\PayrollPeriodRepository;
use App\Services\PayrollExceptionService;
use App\Services\PayrollWorkspaceService;
use PHPUnit\Framework\TestCase;

final class PayrollExceptionServiceTest extends TestCase
{
    private PDO $db;

    private PayrollPeriodRepository $payrollPeriodRepository;

    private PayrollExceptionResolutionRepository $exceptionRepository;

    private PayrollExceptionService $service;


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

        $this->createUsersAndEmployees();


        $this->payrollPeriodRepository =
            new PayrollPeriodRepository(
                $this->db
            );


        $this->exceptionRepository =
            new PayrollExceptionResolutionRepository(
                $this->db
            );


        $workspaceReflection =
            new ReflectionClass(
                PayrollWorkspaceService::class
            );


        $workspace =
            $workspaceReflection
                ->newInstanceWithoutConstructor();


        $this->service =
            new PayrollExceptionService(
                $this->db,
                $this->payrollPeriodRepository,
                $this->exceptionRepository,
                $workspace
            );
    }


    public function testDetectsAndClassifiesPayrollErrors(): void
    {
        $payrollPeriodId =
            $this->createPeriod();


        $result =
            $this->service
                ->refreshFromSummary(
                    $payrollPeriodId,
                    $this->summary(
                        [
                            [
                                'date' =>
                                    '2026-07-20',

                                'errors' => [
                                    'Employee clock-in is missing clock-out.',
                                    'Clock-out occurred without a clock-in.',
                                    'Incomplete meal period.',
                                    'Incomplete break period.'
                                ]
                            ]
                        ]
                    )
                );


        self::assertSame(
            4,
            $result['detected_count']
        );


        self::assertSame(
            4,
            $result['open_count']
        );


        self::assertSame(
            4,
            $result['total_count']
        );


        $types =
            array_column(
                $result['exceptions'],
                'exception_type'
            );


        sort(
            $types
        );


        self::assertSame(
            [
                'missing_clock_in',
                'missing_clock_out',
                'unmatched_break',
                'unmatched_meal'
            ],
            $types
        );
    }


    public function testExceptionKeysRemainStableAcrossRefreshes(): void
    {
        $payrollPeriodId =
            $this->createPeriod();


        $firstResult =
            $this->service
                ->refreshFromSummary(
                    $payrollPeriodId,
                    $this->summary(
                        [
                            [
                                'date' =>
                                    '2026-07-20',

                                'errors' => [
                                    'Missing   clock-out'
                                ]
                            ]
                        ]
                    )
                );


        $firstException =
            $firstResult['exceptions'][0];


        $secondResult =
            $this->service
                ->refreshFromSummary(
                    $payrollPeriodId,
                    $this->summary(
                        [
                            [
                                'date' =>
                                    '2026-07-20',

                                'errors' => [
                                    '  Missing clock-out  '
                                ]
                            ]
                        ]
                    )
                );


        $secondException =
            $secondResult['exceptions'][0];


        self::assertSame(
            1,
            $firstResult['created_count']
        );


        self::assertSame(
            1,
            $secondResult['refreshed_count']
        );


        self::assertSame(
            1,
            $secondResult['total_count']
        );


        self::assertSame(
            (int)$firstException['id'],
            (int)$secondException['id']
        );


        self::assertSame(
            $firstException['exception_key'],
            $secondException['exception_key']
        );


        self::assertSame(
            'Missing clock-out',
            $secondException['description']
        );
    }


    public function testStaleOpenExceptionsAreDeleted(): void
    {
        $payrollPeriodId =
            $this->createPeriod();


        $firstResult =
            $this->service
                ->refreshFromSummary(
                    $payrollPeriodId,
                    $this->summary(
                        [
                            [
                                'date' =>
                                    '2026-07-20',

                                'errors' => [
                                    'Missing clock-out',
                                    'Incomplete meal period.'
                                ]
                            ]
                        ]
                    )
                );


        $secondResult =
            $this->service
                ->refreshFromSummary(
                    $payrollPeriodId,
                    $this->summary(
                        [
                            [
                                'date' =>
                                    '2026-07-20',

                                'errors' => [
                                    'Missing clock-out'
                                ]
                            ]
                        ]
                    )
                );


        self::assertSame(
            2,
            $firstResult['detected_count']
        );


        self::assertSame(
            1,
            $secondResult['deleted_stale_open_count']
        );


        self::assertSame(
            1,
            $secondResult['open_count']
        );


        self::assertSame(
            1,
            $secondResult['total_count']
        );


        self::assertCount(
            1,
            $secondResult['exceptions']
        );


        self::assertSame(
            'Missing clock-out',
            $secondResult['exceptions'][0]['description']
        );
    }


    public function testResolvedExceptionReopensWhenErrorReturns(): void
    {
        $payrollPeriodId =
            $this->createPeriod();


        $initialResult =
            $this->service
                ->refreshFromSummary(
                    $payrollPeriodId,
                    $this->singleErrorSummary(
                        'Missing clock-out'
                    )
                );


        $exceptionId =
            (int)$initialResult['exceptions'][0]['id'];


        self::assertTrue(
            $this->exceptionRepository
                ->markResolved(
                    $exceptionId,
                    1,
                    'The missing punch was corrected.'
                )
        );


        $resolved =
            $this->exceptionRepository
                ->find(
                    $exceptionId
                );


        self::assertNotNull(
            $resolved
        );


        self::assertSame(
            'resolved',
            $resolved['resolution_status']
        );


        $refreshedResult =
            $this->service
                ->refreshFromSummary(
                    $payrollPeriodId,
                    $this->singleErrorSummary(
                        'Missing clock-out'
                    )
                );


        self::assertSame(
            1,
            $refreshedResult['reopened_count']
        );


        self::assertSame(
            1,
            $refreshedResult['open_count']
        );


        $reopened =
            $this->exceptionRepository
                ->find(
                    $exceptionId
                );


        self::assertNotNull(
            $reopened
        );


        self::assertSame(
            'open',
            $reopened['resolution_status']
        );


        self::assertNull(
            $reopened['resolution_note']
        );


        self::assertNull(
            $reopened['resolved_by_user_id']
        );
    }


    public function testAcceptedExceptionRemainsAcceptedWhenErrorReturns(): void
    {
        $payrollPeriodId =
            $this->createPeriod();


        $initialResult =
            $this->service
                ->refreshFromSummary(
                    $payrollPeriodId,
                    $this->singleErrorSummary(
                        'Incomplete meal period.'
                    )
                );


        $exceptionId =
            (int)$initialResult['exceptions'][0]['id'];


        self::assertTrue(
            $this->exceptionRepository
                ->markAccepted(
                    $exceptionId,
                    2,
                    'The supervisor accepts this documented exception.'
                )
        );


        $refreshedResult =
            $this->service
                ->refreshFromSummary(
                    $payrollPeriodId,
                    $this->singleErrorSummary(
                        'Incomplete meal period.'
                    )
                );


        self::assertSame(
            1,
            $refreshedResult[
                'accepted_preserved_count'
            ]
        );


        self::assertSame(
            0,
            $refreshedResult['open_count']
        );


        self::assertSame(
            1,
            $refreshedResult['total_count']
        );


        $accepted =
            $this->exceptionRepository
                ->find(
                    $exceptionId
                );


        self::assertNotNull(
            $accepted
        );


        self::assertSame(
            'accepted',
            $accepted['resolution_status']
        );


        self::assertSame(
            'The supervisor accepts this documented exception.',
            $accepted['resolution_note']
        );


        self::assertSame(
            2,
            (int)$accepted['resolved_by_user_id']
        );
    }


    public function testApprovedAndLockedPeriodsCannotBeRefreshed(): void
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


            try {

                $this->service
                    ->refreshFromSummary(
                        $payrollPeriodId,
                        $this->singleErrorSummary(
                            'Missing clock-out'
                        )
                    );


                self::fail(
                    sprintf(
                        'A %s payroll period was refreshed.',
                        $status
                    )
                );

            } catch (\RuntimeException $exception) {

                self::assertSame(
                    'Payroll exceptions may be refreshed only while a payroll period is open or under review.',
                    $exception->getMessage()
                );
            }


            self::assertSame(
                0,
                $this->exceptionRepository
                    ->countForPeriod(
                        $payrollPeriodId
                    )
            );
        }
    }


    public function testSummaryDateRangeMustMatchPayrollPeriod(): void
    {
        $payrollPeriodId =
            $this->createPeriod();


        try {

            $this->service
                ->refreshFromSummary(
                    $payrollPeriodId,
                    [
                        'start_date' =>
                            '2026-07-21',

                        'end_date' =>
                            '2026-07-26',

                        'employees' =>
                            []
                    ]
                );


            self::fail(
                'A mismatched payroll summary was accepted.'
            );

        } catch (\InvalidArgumentException $exception) {

            self::assertSame(
                'The payroll workspace summary does not match the payroll period date range.',
                $exception->getMessage()
            );
        }


        self::assertSame(
            0,
            $this->exceptionRepository
                ->countForPeriod(
                    $payrollPeriodId
                )
        );
    }


    public function testSynchronizationRollsBackWhenAnInsertFails(): void
    {
        $payrollPeriodId =
            $this->createPeriod();


        $this->db->exec(
            "
            CREATE TRIGGER fail_second_exception_insert

            BEFORE INSERT
            ON payroll_exception_resolutions

            WHEN
            (
                SELECT COUNT(*)

                FROM payroll_exception_resolutions

                WHERE payroll_period_id
                    =
                    NEW.payroll_period_id
            )
            >=
            1

            BEGIN

                SELECT RAISE(
                    ABORT,
                    'forced exception synchronization failure'
                );

            END
            "
        );


        try {

            $this->service
                ->refreshFromSummary(
                    $payrollPeriodId,
                    $this->summary(
                        [
                            [
                                'date' =>
                                    '2026-07-20',

                                'errors' => [
                                    'First failure',
                                    'Second failure'
                                ]
                            ]
                        ]
                    )
                );


            self::fail(
                'Partial payroll exceptions remained after synchronization failed.'
            );

        } catch (\Throwable $exception) {

            self::assertStringContainsString(
                'forced exception synchronization failure',
                $exception->getMessage()
            );
        }


        self::assertSame(
            0,
            $this->exceptionRepository
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


    /**
     * @return array<string,mixed>
     */
    private function singleErrorSummary(
        string $error
    ): array
    {
        return
            $this->summary(
                [
                    [
                        'date' =>
                            '2026-07-20',

                        'errors' => [
                            $error
                        ]
                    ]
                ]
            );
    }


    /**
     * @param array<int,array<string,mixed>> $days
     *
     * @return array<string,mixed>
     */
    private function summary(
        array $days
    ): array
    {
        return [
            'start_date' =>
                '2026-07-20',

            'end_date' =>
                '2026-07-26',

            'employees' => [
                [
                    'employee_id' =>
                        1,

                    'employee_number' =>
                        '1001',

                    'name' =>
                        'Test Employee',

                    'days' =>
                        $days
                ]
            ]
        ];
    }


    private function createUsersAndEmployees(): void
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
