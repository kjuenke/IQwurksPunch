<?php
declare(strict_types=1);

use App\Repositories\PayrollExceptionResolutionRepository;
use App\Repositories\PayrollPeriodRepository;
use App\Services\PayrollReportPeriodMetadataService;
use PHPUnit\Framework\TestCase;

final class PayrollReportPeriodMetadataServiceTest extends TestCase
{
    private PDO $db;

    private PayrollPeriodRepository $payrollPeriodRepository;

    private PayrollExceptionResolutionRepository $exceptionRepository;

    private PayrollReportPeriodMetadataService $service;


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


        $this->service =
            new PayrollReportPeriodMetadataService(
                $this->payrollPeriodRepository,
                $this->exceptionRepository
            );
    }


    public function testExactRangeReturnsPayrollPeriodMetadataAndExceptionCounts(): void
    {
        $payrollPeriodId =
            $this->createPeriod();


        $firstExceptionId =
            $this->createException(
                $payrollPeriodId,
                'missing_clock_out',
                '2026-07-20',
                'Employee clock-in is missing clock-out.'
            );


        $this->createException(
            $payrollPeriodId,
            'unmatched_meal',
            '2026-07-21',
            'Incomplete meal period.'
        );


        $this->exceptionRepository
            ->markResolved(
                $firstExceptionId,
                1,
                'The missing clock-out was corrected.'
            );


        $result =
            $this->service
                ->forRange(
                    '2026-07-20',
                    '2026-07-26'
                );


        self::assertSame(
            'exact',
            $result['association_status']
        );


        self::assertSame(
            'This report exactly matches a payroll review period.',
            $result['association_message']
        );


        self::assertNotNull(
            $result['payroll_period']
        );


        $period =
            $result['payroll_period'];


        self::assertSame(
            $payrollPeriodId,
            $period['id']
        );


        self::assertSame(
            'Weekly Payroll',
            $period['period_name']
        );


        self::assertSame(
            '2026-07-20',
            $period['start_date']
        );


        self::assertSame(
            '2026-07-26',
            $period['end_date']
        );


        self::assertSame(
            'open',
            $period['status']
        );


        self::assertSame(
            1,
            $period['created_by_user_id']
        );


        self::assertSame(
            'test-supervisor',
            $period['created_by_username']
        );


        self::assertSame(
            1,
            $period['open_exception_count']
        );


        self::assertSame(
            2,
            $period['total_exception_count']
        );


        self::assertTrue(
            $period['approval_blocked']
        );


        self::assertSame(
            '/payroll-periods/'
            .
            $payrollPeriodId,
            $period['detail_url']
        );
    }


    public function testExactRangeWithoutOpenExceptionsDoesNotBlockApproval(): void
    {
        $payrollPeriodId =
            $this->createPeriod(
                'under_review'
            );


        $resolvedExceptionId =
            $this->createException(
                $payrollPeriodId,
                'missing_clock_out',
                '2026-07-20',
                'Missing clock-out.'
            );


        $acceptedExceptionId =
            $this->createException(
                $payrollPeriodId,
                'unmatched_break',
                '2026-07-21',
                'Incomplete break period.'
            );


        $this->exceptionRepository
            ->markResolved(
                $resolvedExceptionId,
                1,
                'The missing punch was corrected.'
            );


        $this->exceptionRepository
            ->markAccepted(
                $acceptedExceptionId,
                2,
                'The supervisor accepts this documented variance.'
            );


        $result =
            $this->service
                ->forRange(
                    '2026-07-20',
                    '2026-07-26'
                );


        self::assertSame(
            'exact',
            $result['association_status']
        );


        self::assertNotNull(
            $result['payroll_period']
        );


        self::assertSame(
            0,
            $result['payroll_period']['open_exception_count']
        );


        self::assertSame(
            2,
            $result['payroll_period']['total_exception_count']
        );


        self::assertFalse(
            $result['payroll_period']['approval_blocked']
        );
    }


    public function testRangeWithoutOverlapReturnsNoAssociation(): void
    {
        $this->createPeriod();


        $result =
            $this->service
                ->forRange(
                    '2026-08-03',
                    '2026-08-09'
                );


        self::assertSame(
            'none',
            $result['association_status']
        );


        self::assertSame(
            'This report is not associated with a payroll period.',
            $result['association_message']
        );


        self::assertNull(
            $result['payroll_period']
        );
    }


    public function testPartialOverlapIsNotReportedAsAnExactAssociation(): void
    {
        $this->createPeriod();


        $result =
            $this->service
                ->forRange(
                    '2026-07-21',
                    '2026-07-25'
                );


        self::assertSame(
            'partial_overlap',
            $result['association_status']
        );


        self::assertNull(
            $result['payroll_period']
        );


        self::assertStringContainsString(
            'Weekly Payroll',
            $result['association_message']
        );


        self::assertStringContainsString(
            '2026-07-20 through 2026-07-26',
            $result['association_message']
        );
    }


    public function testExactRangeIncludesReviewApprovalAndLockMetadata(): void
    {
        $payrollPeriodId =
            $this->createPeriod();


        $statement =
            $this->db->prepare(
                "
                UPDATE payroll_periods

                SET
                    status =
                        'locked',

                    reviewed_by_user_id =
                        1,

                    review_started_at =
                        '2026-07-27 08:00:00',

                    approved_by_user_id =
                        2,

                    approved_at =
                        '2026-07-27 09:00:00',

                    locked_by_user_id =
                        3,

                    locked_at =
                        '2026-07-27 10:00:00',

                    updated_at =
                        '2026-07-27 10:00:00'

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


        $result =
            $this->service
                ->forRange(
                    '2026-07-20',
                    '2026-07-26'
                );


        self::assertSame(
            'exact',
            $result['association_status']
        );


        self::assertNotNull(
            $result['payroll_period']
        );


        $period =
            $result['payroll_period'];


        self::assertSame(
            'locked',
            $period['status']
        );


        self::assertSame(
            1,
            $period['reviewed_by_user_id']
        );


        self::assertSame(
            'test-supervisor',
            $period['reviewed_by_username']
        );


        self::assertSame(
            '2026-07-27 08:00:00',
            $period['review_started_at']
        );


        self::assertSame(
            2,
            $period['approved_by_user_id']
        );


        self::assertSame(
            'test-admin',
            $period['approved_by_username']
        );


        self::assertSame(
            '2026-07-27 09:00:00',
            $period['approved_at']
        );


        self::assertSame(
            3,
            $period['locked_by_user_id']
        );


        self::assertSame(
            'second-supervisor',
            $period['locked_by_username']
        );


        self::assertSame(
            '2026-07-27 10:00:00',
            $period['locked_at']
        );
    }


    public function testEnrichPreservesSummaryAndAddsAssociationFields(): void
    {
        $payrollPeriodId =
            $this->createPeriod();


        $summary = [
            'start_date' =>
                '2026-07-20',

            'end_date' =>
                '2026-07-26',

            'timezone' =>
                'America/Los_Angeles',

            'totals' => [
                'employee_count' =>
                    4,

                'total_hours' =>
                    160.0
            ],

            'employees' =>
                []
        ];


        $result =
            $this->service
                ->enrich(
                    $summary,
                    '2026-07-20',
                    '2026-07-26'
                );


        self::assertSame(
            4,
            $result['totals']['employee_count']
        );


        self::assertSame(
            'exact',
            $result['payroll_period_association']
        );


        self::assertSame(
            'This report exactly matches a payroll review period.',
            $result[
                'payroll_period_association_message'
            ]
        );


        self::assertNotNull(
            $result['payroll_period']
        );


        self::assertSame(
            $payrollPeriodId,
            $result['payroll_period']['id']
        );


        self::assertSame(
            '2026-07-20',
            $result['start_date']
        );
    }


    public function testInvalidStartAndEndDatesAreRejected(): void
    {
        $cases = [
            [
                'start_date' =>
                    '2026-7-20',

                'end_date' =>
                    '2026-07-26',

                'message' =>
                    'The report start date must use YYYY-MM-DD format.'
            ],

            [
                'start_date' =>
                    '2026-07-20',

                'end_date' =>
                    '2026-07-32',

                'message' =>
                    'The report end date must use YYYY-MM-DD format.'
            ]
        ];


        foreach ($cases as $case) {

            try {

                $this->service
                    ->forRange(
                        $case['start_date'],
                        $case['end_date']
                    );


                self::fail(
                    'An invalid report date was accepted.'
                );

            } catch (\InvalidArgumentException $exception) {

                self::assertSame(
                    $case['message'],
                    $exception->getMessage()
                );
            }
        }
    }


    public function testEndDateBeforeStartDateIsRejected(): void
    {
        try {

            $this->service
                ->forRange(
                    '2026-07-26',
                    '2026-07-20'
                );


            self::fail(
                'A reversed report date range was accepted.'
            );

        } catch (\InvalidArgumentException $exception) {

            self::assertSame(
                'The report end date cannot be earlier than the report start date.',
                $exception->getMessage()
            );
        }
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
        return
            $this->exceptionRepository
                ->createOrRefresh(
                    [
                        'payroll_period_id' =>
                            $payrollPeriodId,

                        'employee_id' =>
                            1,

                        'exception_key' =>
                            sprintf(
                                'metadata-test:%d:%s:%s',
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
                    'second-supervisor',
                    'second@example.test',
                    'supervisor',
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
