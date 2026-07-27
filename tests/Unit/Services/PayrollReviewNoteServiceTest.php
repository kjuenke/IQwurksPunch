<?php
declare(strict_types=1);

use App\Repositories\PayrollPeriodHistoryRepository;
use App\Repositories\PayrollPeriodRepository;
use App\Repositories\PayrollReviewNoteRepository;
use App\Services\PayrollReviewNoteService;
use PHPUnit\Framework\TestCase;

final class PayrollReviewNoteServiceTest extends TestCase
{
    private PDO $db;

    private PayrollPeriodRepository $payrollPeriodRepository;

    private PayrollPeriodHistoryRepository $historyRepository;

    private PayrollReviewNoteRepository $reviewNoteRepository;

    private PayrollReviewNoteService $service;


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


        $this->reviewNoteRepository =
            new PayrollReviewNoteRepository(
                $this->db
            );


        $this->service =
            new PayrollReviewNoteService(
                $this->db,
                $this->payrollPeriodRepository,
                $this->reviewNoteRepository,
                $this->historyRepository
            );
    }


    public function testOpenPeriodCanReceiveReviewNote(): void
    {
        $payrollPeriodId =
            $this->createOpenPeriod();


        $note =
            $this->service
                ->add(
                    $payrollPeriodId,
                    1,
                    'Reviewed employee totals and punch activity.'
                );


        self::assertSame(
            $payrollPeriodId,
            (int)$note['payroll_period_id']
        );


        self::assertSame(
            'Reviewed employee totals and punch activity.',
            $note['note']
        );


        self::assertSame(
            1,
            (int)$note['created_by_user_id']
        );


        self::assertSame(
            'test-supervisor',
            $note['created_by_username']
        );


        self::assertSame(
            1,
            $this->reviewNoteRepository
                ->countForPeriod(
                    $payrollPeriodId
                )
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
            'note_added',
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
    }


    public function testUnderReviewPeriodCanReceiveReviewNote(): void
    {
        $payrollPeriodId =
            $this->createOpenPeriod();


        $this->setPeriodStatus(
            $payrollPeriodId,
            'under_review'
        );


        $note =
            $this->service
                ->add(
                    $payrollPeriodId,
                    2,
                    'Administrator reviewed the exception summary.'
                );


        self::assertSame(
            'Administrator reviewed the exception summary.',
            $note['note']
        );


        self::assertSame(
            2,
            (int)$note['created_by_user_id']
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
            'under_review',
            $history['previous_status']
        );


        self::assertSame(
            'under_review',
            $history['new_status']
        );
    }


    public function testBlankReviewNoteIsRejected(): void
    {
        $payrollPeriodId =
            $this->createOpenPeriod();


        try {

            $this->service
                ->add(
                    $payrollPeriodId,
                    1,
                    '   '
                );


            self::fail(
                'A blank payroll review note was accepted.'
            );

        } catch (\InvalidArgumentException $exception) {

            self::assertSame(
                'A review note is required.',
                $exception->getMessage()
            );
        }


        self::assertSame(
            0,
            $this->reviewNoteRepository
                ->countForPeriod(
                    $payrollPeriodId
                )
        );
    }


    public function testReviewNoteAtMaximumLengthIsAccepted(): void
    {
        $payrollPeriodId =
            $this->createOpenPeriod();


        $noteText =
            str_repeat(
                'N',
                1000
            );


        $note =
            $this->service
                ->add(
                    $payrollPeriodId,
                    1,
                    $noteText
                );


        self::assertSame(
            1000,
            strlen(
                (string)$note['note']
            )
        );


        self::assertSame(
            $noteText,
            $note['note']
        );


        self::assertSame(
            1,
            $this->reviewNoteRepository
                ->countForPeriod(
                    $payrollPeriodId
                )
        );


        self::assertSame(
            1,
            $this->historyRepository
                ->countForPeriod(
                    $payrollPeriodId
                )
        );
    }


    public function testReviewNoteOverMaximumLengthIsRejected(): void
    {
        $payrollPeriodId =
            $this->createOpenPeriod();


        try {

            $this->service
                ->add(
                    $payrollPeriodId,
                    1,
                    str_repeat(
                        'N',
                        1001
                    )
                );


            self::fail(
                'An oversized payroll review note was accepted.'
            );

        } catch (\InvalidArgumentException $exception) {

            self::assertSame(
                'A review note may not exceed 1000 characters.',
                $exception->getMessage()
            );
        }


        self::assertSame(
            0,
            $this->reviewNoteRepository
                ->countForPeriod(
                    $payrollPeriodId
                )
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


    public function testUnauthorizedRoleCannotAddReviewNote(): void
    {
        $payrollPeriodId =
            $this->createOpenPeriod();


        try {

            $this->service
                ->add(
                    $payrollPeriodId,
                    3,
                    'This note should not be accepted.'
                );


            self::fail(
                'An unauthorized user added a payroll review note.'
            );

        } catch (\RuntimeException $exception) {

            self::assertSame(
                'The acting user is not authorized to add payroll review notes.',
                $exception->getMessage()
            );
        }


        self::assertSame(
            0,
            $this->reviewNoteRepository
                ->countForPeriod(
                    $payrollPeriodId
                )
        );
    }


    public function testInactiveSupervisorCannotAddReviewNote(): void
    {
        $payrollPeriodId =
            $this->createOpenPeriod();


        try {

            $this->service
                ->add(
                    $payrollPeriodId,
                    4,
                    'This inactive supervisor note should fail.'
                );


            self::fail(
                'An inactive supervisor added a payroll review note.'
            );

        } catch (\RuntimeException $exception) {

            self::assertSame(
                'The acting user is inactive.',
                $exception->getMessage()
            );
        }


        self::assertSame(
            0,
            $this->reviewNoteRepository
                ->countForPeriod(
                    $payrollPeriodId
                )
        );
    }


    public function testApprovedAndLockedPeriodsRejectReviewNotes(): void
    {
        foreach (
            [
                'approved',
                'locked'
            ]
            as $status
        ) {
            $payrollPeriodId =
                $this->createOpenPeriod();


            $this->setPeriodStatus(
                $payrollPeriodId,
                $status
            );


            try {

                $this->service
                    ->add(
                        $payrollPeriodId,
                        1,
                        'This protected-period note should fail.'
                    );


                self::fail(
                    sprintf(
                        'A review note was added to a %s payroll period.',
                        $status
                    )
                );

            } catch (\RuntimeException $exception) {

                self::assertSame(
                    'Review notes may be added only while a payroll period is open or under review.',
                    $exception->getMessage()
                );
            }


            self::assertSame(
                0,
                $this->reviewNoteRepository
                    ->countForPeriod(
                        $payrollPeriodId
                    )
            );
        }
    }


    public function testInvalidPayrollPeriodIdIsRejected(): void
    {
        try {

            $this->service
                ->add(
                    0,
                    1,
                    'This note has an invalid period ID.'
                );


            self::fail(
                'An invalid payroll period ID was accepted.'
            );

        } catch (\InvalidArgumentException $exception) {

            self::assertSame(
                'A valid payroll period ID is required.',
                $exception->getMessage()
            );
        }
    }


    public function testHistoryFailureRollsBackReviewNote(): void
    {
        $payrollPeriodId =
            $this->createOpenPeriod();


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
                ->add(
                    $payrollPeriodId,
                    1,
                    'This note must be rolled back.'
                );


            self::fail(
                'The note remained after its history insert failed.'
            );

        } catch (\Throwable $exception) {

            self::assertStringContainsString(
                'forced history failure',
                $exception->getMessage()
            );
        }


        self::assertSame(
            0,
            $this->reviewNoteRepository
                ->countForPeriod(
                    $payrollPeriodId
                )
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


    private function createOpenPeriod(): int
    {
        return
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
    }


    private function setPeriodStatus(
        int $payrollPeriodId,
        string $status
    ): void
    {
        $statement =
            $this->db->prepare(
                "
                UPDATE payroll_periods

                SET
                    status = :status,
                    updated_at = CURRENT_TIMESTAMP

                WHERE id = :id
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
                ON DELETE RESTRICT,

                CHECK
                (
                    LENGTH(
                        TRIM(
                            note
                        )
                    )
                    >
                    0
                )
            )
            "
        );
    }
}
