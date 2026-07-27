<?php
declare(strict_types=1);

use App\Repositories\PayrollPeriodRepository;
use App\Services\PayrollPeriodProtectionService;
use PHPUnit\Framework\TestCase;

final class PayrollPeriodProtectionServiceTest extends TestCase
{
    private PDO $db;

    private PayrollPeriodRepository $payrollPeriodRepository;

    private PayrollPeriodProtectionService $service;


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
            )
            "
        );


        $this->payrollPeriodRepository =
            new PayrollPeriodRepository(
                $this->db
            );


        $this->service =
            new PayrollPeriodProtectionService(
                $this->payrollPeriodRepository
            );
    }


    public function testUtcTimestampBeforeLocalMidnightUsesPreviousCompanyDate(): void
    {
        $localDate =
            $this->service
                ->localDateForUtcTimestamp(
                    '2026-07-20 06:59:59',
                    'America/Los_Angeles'
                );


        self::assertSame(
            '2026-07-19',
            $localDate
        );
    }


    public function testUtcTimestampAtLocalMidnightUsesNewCompanyDate(): void
    {
        $localDate =
            $this->service
                ->localDateForUtcTimestamp(
                    '2026-07-20 07:00:00',
                    'America/Los_Angeles'
                );


        self::assertSame(
            '2026-07-20',
            $localDate
        );
    }


    public function testUtcConversionHandlesDaylightSavingFallBoundary(): void
    {
        $beforeMidnight =
            $this->service
                ->localDateForUtcTimestamp(
                    '2026-11-01 06:59:59',
                    'America/Los_Angeles'
                );


        $atMidnight =
            $this->service
                ->localDateForUtcTimestamp(
                    '2026-11-01 07:00:00',
                    'America/Los_Angeles'
                );


        self::assertSame(
            '2026-10-31',
            $beforeMidnight
        );


        self::assertSame(
            '2026-11-01',
            $atMidnight
        );
    }


    public function testApprovedPeriodIsFoundUsingCompanyLocalDate(): void
    {
        $payrollPeriodId =
            $this->createPeriod(
                periodName:
                    'Approved Weekly Payroll',

                startDate:
                    '2026-07-13',

                endDate:
                    '2026-07-19',

                status:
                    'approved'
            );


        $protectedPeriod =
            $this->service
                ->findProtectedForUtcTimestamp(
                    '2026-07-20 06:30:00',
                    'America/Los_Angeles'
                );


        self::assertNotNull(
            $protectedPeriod
        );


        self::assertSame(
            $payrollPeriodId,
            (int)$protectedPeriod['id']
        );


        self::assertSame(
            'approved',
            $protectedPeriod['status']
        );


        self::assertSame(
            '2026-07-19',
            $protectedPeriod['end_date']
        );
    }


    public function testLockedPeriodIsProtected(): void
    {
        $payrollPeriodId =
            $this->createPeriod(
                periodName:
                    'Locked Weekly Payroll',

                startDate:
                    '2026-07-20',

                endDate:
                    '2026-07-26',

                status:
                    'locked'
            );


        $protectedPeriod =
            $this->service
                ->findProtectedForLocalDate(
                    '2026-07-23'
                );


        self::assertNotNull(
            $protectedPeriod
        );


        self::assertSame(
            $payrollPeriodId,
            (int)$protectedPeriod['id']
        );


        self::assertSame(
            'locked',
            $protectedPeriod['status']
        );


        self::assertTrue(
            $this->service
                ->isLocalDateProtected(
                    '2026-07-23'
                )
        );
    }


    public function testOpenPeriodRemainsEditable(): void
    {
        $this->createPeriod(
            periodName:
                'Open Weekly Payroll',

            startDate:
                '2026-07-27',

            endDate:
                '2026-08-02',

            status:
                'open'
        );


        self::assertFalse(
            $this->service
                ->isLocalDateProtected(
                    '2026-07-29'
                )
        );


        self::assertNull(
            $this->service
                ->findProtectedForLocalDate(
                    '2026-07-29'
                )
        );
    }


    public function testUnderReviewPeriodRemainsEditable(): void
    {
        $this->createPeriod(
            periodName:
                'Payroll Under Review',

            startDate:
                '2026-08-03',

            endDate:
                '2026-08-09',

            status:
                'under_review'
        );


        self::assertFalse(
            $this->service
                ->isUtcTimestampProtected(
                    '2026-08-06 18:00:00',
                    'America/Los_Angeles'
                )
        );


        $this->service
            ->assertUtcTimestampIsEditable(
                '2026-08-06 18:00:00',
                'America/Los_Angeles'
            );


        self::addToAssertionCount(
            1
        );
    }


    public function testProtectedPeriodThrowsDetailedCorrectionMessage(): void
    {
        $this->createPeriod(
            periodName:
                'Final Weekly Payroll',

            startDate:
                '2026-08-10',

            endDate:
                '2026-08-16',

            status:
                'locked'
        );


        try {

            $this->service
                ->assertLocalDateIsEditable(
                    '2026-08-12'
                );


            self::fail(
                'A correction inside a locked payroll period was allowed.'
            );

        } catch (RuntimeException $exception) {

            self::assertStringContainsString(
                'locked payroll period',
                $exception->getMessage()
            );


            self::assertStringContainsString(
                '"Final Weekly Payroll"',
                $exception->getMessage()
            );


            self::assertStringContainsString(
                '2026-08-10 through 2026-08-16',
                $exception->getMessage()
            );


            self::assertStringContainsString(
                'Reopen the payroll period before making corrections.',
                $exception->getMessage()
            );
        }
    }


    public function testDateOutsideProtectedPeriodRemainsEditable(): void
    {
        $this->createPeriod(
            periodName:
                'Approved Weekly Payroll',

            startDate:
                '2026-08-17',

            endDate:
                '2026-08-23',

            status:
                'approved'
        );


        $this->service
            ->assertLocalDateIsEditable(
                '2026-08-24'
            );


        self::assertFalse(
            $this->service
                ->isLocalDateProtected(
                    '2026-08-24'
                )
        );
    }


    public function testInvalidCompanyLocalDateIsRejected(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );


        $this->expectExceptionMessage(
            'must use a valid YYYY-MM-DD date'
        );


        $this->service
            ->findProtectedForLocalDate(
                '2026-02-30'
            );
    }


    public function testInvalidCompanyTimezoneIsRejected(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );


        $this->expectExceptionMessage(
            'company timezone "Invalid/Timezone" is invalid'
        );


        $this->service
            ->localDateForUtcTimestamp(
                '2026-07-20 12:00:00',
                'Invalid/Timezone'
            );
    }


    private function createPeriod(
        string $periodName,
        string $startDate,
        string $endDate,
        string $status
    ): int
    {
        $approvedByUserId =
            in_array(
                $status,
                [
                    'approved',
                    'locked'
                ],
                true
            )
                ? 1
                : null;


        $approvedAt =
            in_array(
                $status,
                [
                    'approved',
                    'locked'
                ],
                true
            )
                ? '2026-07-20 12:00:00'
                : null;


        $lockedByUserId =
            $status === 'locked'
                ? 1
                : null;


        $lockedAt =
            $status === 'locked'
                ? '2026-07-20 13:00:00'
                : null;


        $reviewedByUserId =
            $status === 'under_review'
                ? 1
                : null;


        $reviewStartedAt =
            $status === 'under_review'
                ? '2026-07-20 11:00:00'
                : null;


        $statement =
            $this->db->prepare(
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
                    1,
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
            (int)$this->db
                ->lastInsertId();
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
            CREATE INDEX idx_payroll_periods_dates

            ON payroll_periods
            (
                start_date,
                end_date
            )
            "
        );


        $this->db->exec(
            "
            CREATE INDEX idx_payroll_periods_status

            ON payroll_periods
            (
                status
            )
            "
        );
    }
}
