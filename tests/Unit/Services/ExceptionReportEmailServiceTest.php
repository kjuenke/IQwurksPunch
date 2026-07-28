<?php
declare(strict_types=1);

use App\Repositories\PayrollExceptionResolutionRepository;
use App\Repositories\PayrollPeriodRepository;
use App\Services\ExceptionReportEmailService;
use PHPUnit\Framework\TestCase;

final class ExceptionReportEmailServiceTest extends TestCase
{
    private PDO $db;

    private ExceptionReportEmailService $service;


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


        $this->db->exec(
            'PRAGMA foreign_keys = ON'
        );


        $this->createTables();

        $this->seedData();


        $this->service =
            $this->createService();
    }


    public function testReportIncludesOnlyOpenExceptionsFromActionablePeriods(): void
    {
        $report =
            $this->service
                ->openExceptionReportData();


        self::assertSame(
            2,
            $report['period_count']
        );


        self::assertSame(
            2,
            $report['exception_count']
        );


        self::assertCount(
            2,
            $report['periods']
        );


        $periodIds =
            array_map(
                static fn (
                    array $periodGroup
                ): int =>
                    (int)$periodGroup['period']['id'],
                $report['periods']
            );


        sort(
            $periodIds
        );


        self::assertSame(
            [
                1,
                2
            ],
            $periodIds
        );


        foreach (
            $report['periods']
            as
            $periodGroup
        ) {
            self::assertCount(
                1,
                $periodGroup['exceptions']
            );


            self::assertSame(
                'open',
                $periodGroup['exceptions'][0]['resolution_status']
            );
        }
    }


    public function testReportIsEmptyWhenNoActionableOpenExceptionsExist(): void
    {
        $this->db->exec(
            "
            UPDATE payroll_exception_resolutions

            SET resolution_status = 'accepted'
            "
        );


        $report =
            $this->service
                ->openExceptionReportData();


        self::assertSame(
            0,
            $report['period_count']
        );


        self::assertSame(
            0,
            $report['exception_count']
        );


        self::assertSame(
            [],
            $report['periods']
        );
    }


    public function testReportBodyExplainsScopeAndListsActionableExceptions(): void
    {
        $report =
            $this->service
                ->openExceptionReportData();


        $reflection =
            new ReflectionClass(
                ExceptionReportEmailService::class
            );


        $method =
            $reflection->getMethod(
                'buildBody'
            );


        $body =
            $method->invoke(
                $this->service,
                'RFE International, Inc.',
                'America/Los_Angeles',
                new DateTimeImmutable(
                    '2026-07-28 10:00:00',
                    new DateTimeZone(
                        'America/Los_Angeles'
                    )
                ),
                $report
            );


        self::assertStringContainsString(
            'RFE International, Inc. Payroll Exception Report',
            $body
        );


        self::assertStringContainsString(
            'Generated: 2026-07-28 10:00:00 PDT',
            $body
        );


        self::assertStringContainsString(
            'Actionable Payroll Periods: 2',
            $body
        );


        self::assertStringContainsString(
            'Open Payroll Exceptions: 2',
            $body
        );


        self::assertStringContainsString(
            'Current Open Period',
            $body
        );


        self::assertStringContainsString(
            'Current Review Period',
            $body
        );


        self::assertStringContainsString(
            'Employee 1001 — Alice Employee',
            $body
        );


        self::assertStringContainsString(
            'Employee 1002 — Bob Employee',
            $body
        );


        self::assertStringNotContainsString(
            'Accepted exception should be excluded.',
            $body
        );


        self::assertStringNotContainsString(
            'Approved Period',
            $body
        );


        self::assertStringContainsString(
            'Review these items in IQwurksPunch before approving',
            $body
        );
    }


    private function createService(): ExceptionReportEmailService
    {
        $reflection =
            new ReflectionClass(
                ExceptionReportEmailService::class
            );


        $service =
            $reflection->newInstanceWithoutConstructor();


        $periodProperty =
            $reflection->getProperty(
                'periods'
            );


        $periodProperty->setValue(
            $service,
            new PayrollPeriodRepository(
                $this->db
            )
        );


        $exceptionProperty =
            $reflection->getProperty(
                'exceptions'
            );


        $exceptionProperty->setValue(
            $service,
            new PayrollExceptionResolutionRepository(
                $this->db
            )
        );


        return $service;
    }


    private function createTables(): void
    {
        $this->db->exec(
            "
            CREATE TABLE users
            (
                id INTEGER PRIMARY KEY AUTOINCREMENT,

                username TEXT NOT NULL,

                email TEXT
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

                status TEXT NOT NULL,

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
                REFERENCES users(id),

                FOREIGN KEY
                (
                    reviewed_by_user_id
                )
                REFERENCES users(id),

                FOREIGN KEY
                (
                    approved_by_user_id
                )
                REFERENCES users(id),

                FOREIGN KEY
                (
                    locked_by_user_id
                )
                REFERENCES users(id)
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
                REFERENCES payroll_periods(id),

                FOREIGN KEY
                (
                    employee_id
                )
                REFERENCES employees(id),

                FOREIGN KEY
                (
                    resolved_by_user_id
                )
                REFERENCES users(id)
            )
            "
        );
    }


    private function seedData(): void
    {
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
                'supervisor',
                'supervisor@example.com'
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
                    'Alice',
                    'Employee'
                ),
                (
                    2,
                    '1002',
                    'Bob',
                    'Employee'
                )
            "
        );


        $this->db->exec(
            "
            INSERT INTO payroll_periods
            (
                id,
                period_name,
                start_date,
                end_date,
                status,
                created_by_user_id,
                reviewed_by_user_id
            )

            VALUES
                (
                    1,
                    'Current Open Period',
                    '2026-07-07',
                    '2026-07-13',
                    'open',
                    1,
                    NULL
                ),
                (
                    2,
                    'Current Review Period',
                    '2026-07-14',
                    '2026-07-20',
                    'under_review',
                    1,
                    1
                ),
                (
                    3,
                    'Approved Period',
                    '2026-06-23',
                    '2026-06-29',
                    'approved',
                    1,
                    1
                ),
                (
                    4,
                    'Locked Period',
                    '2026-06-16',
                    '2026-06-22',
                    'locked',
                    1,
                    1
                )
            "
        );


        $this->db->exec(
            "
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
                    1,
                    1,
                    'open-period-open-exception',
                    'missing_punch',
                    '2026-07-10',
                    'Alice has an unmatched clock-in punch.',
                    'open'
                ),
                (
                    1,
                    1,
                    'open-period-accepted-exception',
                    'payroll_calculation_warning',
                    '2026-07-11',
                    'Accepted exception should be excluded.',
                    'accepted'
                ),
                (
                    2,
                    2,
                    'review-period-open-exception',
                    'missing_punch',
                    '2026-07-17',
                    'Bob has an unmatched clock-out punch.',
                    'open'
                ),
                (
                    3,
                    1,
                    'approved-period-open-exception',
                    'missing_punch',
                    '2026-06-26',
                    'This approved-period exception must be excluded.',
                    'open'
                ),
                (
                    4,
                    2,
                    'locked-period-open-exception',
                    'missing_punch',
                    '2026-06-19',
                    'This locked-period exception must be excluded.',
                    'open'
                )
            "
        );
    }
}
