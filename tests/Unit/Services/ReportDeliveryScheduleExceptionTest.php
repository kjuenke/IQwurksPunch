<?php
declare(strict_types=1);

use App\Repositories\ReportDeliveryScheduleRepository;
use App\Services\ReportDeliveryScheduleService;
use PHPUnit\Framework\TestCase;

final class ReportDeliveryScheduleExceptionTest extends TestCase
{
    private PDO $db;

    private ReportDeliveryScheduleService $service;

    private string $originalTimezone;


    protected function setUp(): void
    {
        parent::setUp();


        $this->originalTimezone =
            date_default_timezone_get();


        date_default_timezone_set(
            'America/Los_Angeles'
        );


        $this->db =
            new PDO(
                'sqlite::memory:'
            );


        $this->db->setAttribute(
            PDO::ATTR_ERRMODE,
            PDO::ERRMODE_EXCEPTION
        );


        $this->createTable();

        $this->seedSchedules();


        $this->service =
            new ReportDeliveryScheduleService(
                new ReportDeliveryScheduleRepository(
                    $this->db
                )
            );
    }


    protected function tearDown(): void
    {
        date_default_timezone_set(
            $this->originalTimezone
        );


        parent::tearDown();
    }


    public function testExceptionScheduleUpdatesIndependently(): void
    {
        $dailyBefore =
            $this->scheduleRow(
                ReportDeliveryScheduleService::DAILY_PAYROLL
            );


        $weeklyBefore =
            $this->scheduleRow(
                ReportDeliveryScheduleService::WEEKLY_PAYROLL
            );


        $result =
            $this->service->updateException(
                [
                    'enabled' =>
                        'on',

                    'send_time' =>
                        '09:15',

                    'weekdays_only' =>
                        'on'
                ]
            );


        $exception =
            $this->scheduleRow(
                ReportDeliveryScheduleService::EXCEPTION_REPORT
            );


        self::assertTrue(
            $result['success']
        );


        self::assertSame(
            [],
            $result['errors']
        );


        self::assertSame(
            1,
            (int)$exception['enabled']
        );


        self::assertSame(
            '09:15',
            $exception['send_time']
        );


        self::assertSame(
            1,
            (int)$exception['weekdays_only']
        );


        self::assertNull(
            $exception['send_day_of_week']
        );


        self::assertSame(
            $dailyBefore,
            $this->scheduleRow(
                ReportDeliveryScheduleService::DAILY_PAYROLL
            )
        );


        self::assertSame(
            $weeklyBefore,
            $this->scheduleRow(
                ReportDeliveryScheduleService::WEEKLY_PAYROLL
            )
        );
    }


    public function testExceptionScheduleBecomesDueAfterConfiguredTime(): void
    {
        $this->enableExceptionSchedule(
            '08:00',
            false
        );


        $now =
            new DateTimeImmutable(
                '2026-07-28 08:01:00',
                new DateTimeZone(
                    'America/Los_Angeles'
                )
            );


        self::assertTrue(
            $this->service->isDue(
                ReportDeliveryScheduleService::EXCEPTION_REPORT,
                $now
            )
        );
    }


    public function testExceptionScheduleIsNotDueBeforeConfiguredTime(): void
    {
        $this->enableExceptionSchedule(
            '08:00',
            false
        );


        $now =
            new DateTimeImmutable(
                '2026-07-28 07:59:00',
                new DateTimeZone(
                    'America/Los_Angeles'
                )
            );


        self::assertFalse(
            $this->service->isDue(
                ReportDeliveryScheduleService::EXCEPTION_REPORT,
                $now
            )
        );
    }


    public function testWeekdaysOnlyExceptionScheduleIsBlockedOnWeekend(): void
    {
        $this->enableExceptionSchedule(
            '08:00',
            true
        );


        $saturday =
            new DateTimeImmutable(
                '2026-08-01 09:00:00',
                new DateTimeZone(
                    'America/Los_Angeles'
                )
            );


        self::assertFalse(
            $this->service->isDue(
                ReportDeliveryScheduleService::EXCEPTION_REPORT,
                $saturday
            )
        );
    }


    public function testExceptionScheduleCannotRunTwiceOnSameLocalDate(): void
    {
        $this->enableExceptionSchedule(
            '08:00',
            false
        );


        /*
         * 2026-07-28 16:00 UTC is 2026-07-28 09:00 PDT.
         */
        $statement =
            $this->db->prepare(
                "
                UPDATE report_delivery_schedules

                SET last_sent_at = :last_sent_at

                WHERE report_type = :report_type
                "
            );


        $statement->execute(
            [
                'last_sent_at' =>
                    '2026-07-28 16:00:00',

                'report_type' =>
                    ReportDeliveryScheduleService::EXCEPTION_REPORT
            ]
        );


        $laterSameDay =
            new DateTimeImmutable(
                '2026-07-28 10:00:00',
                new DateTimeZone(
                    'America/Los_Angeles'
                )
            );


        self::assertFalse(
            $this->service->isDue(
                ReportDeliveryScheduleService::EXCEPTION_REPORT,
                $laterSameDay
            )
        );
    }


    private function createTable(): void
    {
        $this->db->exec(
            "
            CREATE TABLE report_delivery_schedules
            (
                id INTEGER PRIMARY KEY AUTOINCREMENT,

                report_type TEXT NOT NULL UNIQUE,

                enabled INTEGER NOT NULL
                    DEFAULT 0,

                send_time TEXT NOT NULL
                    DEFAULT '08:00',

                weekdays_only INTEGER NOT NULL
                    DEFAULT 0,

                send_day_of_week INTEGER NULL,

                last_sent_at TEXT NULL,

                last_result TEXT NULL,

                last_error TEXT NULL,

                created_at TEXT NOT NULL
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at TEXT NOT NULL
                    DEFAULT CURRENT_TIMESTAMP
            )
            "
        );
    }


    private function seedSchedules(): void
    {
        $statement =
            $this->db->prepare(
                "
                INSERT INTO report_delivery_schedules
                (
                    report_type,
                    enabled,
                    send_time,
                    weekdays_only,
                    send_day_of_week
                )

                VALUES
                (
                    :report_type,
                    :enabled,
                    :send_time,
                    :weekdays_only,
                    :send_day_of_week
                )
                "
            );


        $statement->execute(
            [
                'report_type' =>
                    ReportDeliveryScheduleService::DAILY_PAYROLL,

                'enabled' =>
                    1,

                'send_time' =>
                    '19:30',

                'weekdays_only' =>
                    1,

                'send_day_of_week' =>
                    null
            ]
        );


        $statement->execute(
            [
                'report_type' =>
                    ReportDeliveryScheduleService::WEEKLY_PAYROLL,

                'enabled' =>
                    0,

                'send_time' =>
                    '08:00',

                'weekdays_only' =>
                    0,

                'send_day_of_week' =>
                    1
            ]
        );


        $statement->execute(
            [
                'report_type' =>
                    ReportDeliveryScheduleService::EXCEPTION_REPORT,

                'enabled' =>
                    0,

                'send_time' =>
                    '08:00',

                'weekdays_only' =>
                    1,

                'send_day_of_week' =>
                    null
            ]
        );
    }


    private function enableExceptionSchedule(
        string $sendTime,
        bool $weekdaysOnly
    ): void
    {
        $statement =
            $this->db->prepare(
                "
                UPDATE report_delivery_schedules

                SET
                    enabled = 1,
                    send_time = :send_time,
                    weekdays_only = :weekdays_only,
                    send_day_of_week = NULL,
                    last_sent_at = NULL,
                    last_result = NULL,
                    last_error = NULL

                WHERE report_type = :report_type
                "
            );


        $statement->execute(
            [
                'send_time' =>
                    $sendTime,

                'weekdays_only' =>
                    $weekdaysOnly
                        ? 1
                        : 0,

                'report_type' =>
                    ReportDeliveryScheduleService::EXCEPTION_REPORT
            ]
        );
    }


    /**
     * @return array<string,mixed>
     */
    private function scheduleRow(
        string $reportType
    ): array
    {
        $statement =
            $this->db->prepare(
                "
                SELECT
                    report_type,
                    enabled,
                    send_time,
                    weekdays_only,
                    send_day_of_week,
                    last_sent_at,
                    last_result,
                    last_error

                FROM report_delivery_schedules

                WHERE report_type = :report_type
                "
            );


        $statement->execute(
            [
                'report_type' =>
                    $reportType
            ]
        );


        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );


        self::assertIsArray(
            $row
        );


        return $row;
    }
}
