<?php
declare(strict_types=1);

use App\Repositories\ReportDeliveryScheduleRepository;
use App\Services\ReportDeliveryScheduleService;
use PHPUnit\Framework\TestCase;

final class ReportDeliveryScheduleServiceTest extends TestCase
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


        $this->createScheduleTable();


        $repository =
            new ReportDeliveryScheduleRepository(
                $this->db
            );


        $this->service =
            new ReportDeliveryScheduleService(
                $repository
            );
    }


    protected function tearDown(): void
    {
        date_default_timezone_set(
            $this->originalTimezone
        );


        parent::tearDown();
    }


    public function testWeeklyScheduleUpdatePersistsValidatedSettings(): void
    {
        $result =
            $this->service->updateWeekly(
                [
                    'enabled' =>
                        '1',

                    'send_time' =>
                        '09:15',

                    'send_day_of_week' =>
                        '5'
                ]
            );


        self::assertTrue(
            $result['success']
        );


        $schedule =
            $this->service->get(
                ReportDeliveryScheduleService::WEEKLY_PAYROLL
            );


        self::assertNotNull(
            $schedule
        );


        self::assertSame(
            1,
            (int)$schedule['enabled']
        );


        self::assertSame(
            '09:15',
            $schedule['send_time']
        );


        self::assertSame(
            0,
            (int)$schedule['weekdays_only']
        );


        self::assertSame(
            5,
            (int)$schedule['send_day_of_week']
        );
    }


    public function testWeeklyScheduleRejectsInvalidDeliveryDay(): void
    {
        $result =
            $this->service->updateWeekly(
                [
                    'enabled' =>
                        '1',

                    'send_time' =>
                        '09:15',

                    'send_day_of_week' =>
                        '8'
                ]
            );


        self::assertFalse(
            $result['success']
        );


        self::assertSame(
            'Select a valid weekly delivery day.',
            $result['errors']['send_day_of_week']
        );
    }


    public function testWeeklyScheduleIsNotDueOnWrongDay(): void
    {
        $this->enableWeeklySchedule(
            1,
            '08:00'
        );


        $now =
            new DateTimeImmutable(
                '2026-07-28 09:00:00',
                new DateTimeZone(
                    'America/Los_Angeles'
                )
            );


        self::assertFalse(
            $this->service->isDue(
                ReportDeliveryScheduleService::WEEKLY_PAYROLL,
                $now
            )
        );
    }


    public function testWeeklyScheduleIsNotDueBeforeSendTime(): void
    {
        $this->enableWeeklySchedule(
            1,
            '08:00'
        );


        $now =
            new DateTimeImmutable(
                '2026-07-27 07:59:00',
                new DateTimeZone(
                    'America/Los_Angeles'
                )
            );


        self::assertFalse(
            $this->service->isDue(
                ReportDeliveryScheduleService::WEEKLY_PAYROLL,
                $now
            )
        );
    }


    public function testWeeklyScheduleIsDueAfterConfiguredTime(): void
    {
        $this->enableWeeklySchedule(
            1,
            '08:00'
        );


        $now =
            new DateTimeImmutable(
                '2026-07-27 08:01:00',
                new DateTimeZone(
                    'America/Los_Angeles'
                )
            );


        self::assertTrue(
            $this->service->isDue(
                ReportDeliveryScheduleService::WEEKLY_PAYROLL,
                $now
            )
        );
    }


    public function testWeeklySchedulePreventsDuplicateSendOnSameLocalDate(): void
    {
        $this->enableWeeklySchedule(
            1,
            '08:00'
        );


        $this->db->exec(
            "
            UPDATE report_delivery_schedules

            SET last_sent_at = '2026-07-27 15:30:00'

            WHERE report_type = 'weekly_payroll'
            "
        );


        $now =
            new DateTimeImmutable(
                '2026-07-27 09:00:00',
                new DateTimeZone(
                    'America/Los_Angeles'
                )
            );


        self::assertFalse(
            $this->service->isDue(
                ReportDeliveryScheduleService::WEEKLY_PAYROLL,
                $now
            )
        );
    }


    public function testPreviousWeekReferenceDateUsesCompanyLocalTime(): void
    {
        $now =
            new DateTimeImmutable(
                '2026-07-27 08:00:00',
                new DateTimeZone(
                    'America/Los_Angeles'
                )
            );


        self::assertSame(
            '2026-07-20',
            $this->service->previousWeekReferenceDate(
                $now
            )
        );
    }


    private function enableWeeklySchedule(
        int $dayOfWeek,
        string $sendTime
    ): void
    {
        $stmt =
            $this->db->prepare(
                "
                UPDATE report_delivery_schedules

                SET
                    enabled = 1,
                    send_time = :send_time,
                    send_day_of_week = :send_day_of_week,
                    last_sent_at = NULL

                WHERE report_type = 'weekly_payroll'
                "
            );


        $stmt->execute(
            [
                'send_time' =>
                    $sendTime,

                'send_day_of_week' =>
                    $dayOfWeek
            ]
        );
    }


    private function createScheduleTable(): void
    {
        $this->db->exec(
            "
            CREATE TABLE report_delivery_schedules
            (
                id INTEGER PRIMARY KEY AUTOINCREMENT,

                report_type TEXT NOT NULL,

                enabled INTEGER NOT NULL DEFAULT 0,

                send_time TEXT NOT NULL DEFAULT '18:00',

                weekdays_only INTEGER NOT NULL DEFAULT 0,

                send_day_of_week INTEGER DEFAULT NULL,

                last_sent_at DATETIME DEFAULT NULL,

                last_result TEXT DEFAULT NULL,

                last_error TEXT DEFAULT NULL,

                created_at DATETIME
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at DATETIME
                    DEFAULT CURRENT_TIMESTAMP
            )
            "
        );


        $this->db->exec(
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
                    'daily_payroll',
                    1,
                    '19:30',
                    1,
                    NULL
                ),
                (
                    'weekly_payroll',
                    0,
                    '08:00',
                    0,
                    1
                ),
                (
                    'exception_report',
                    0,
                    '08:00',
                    1,
                    NULL
                )
            "
        );
    }
}
