<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\ReportDeliveryScheduleRepository;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final class ReportDeliveryScheduleService
{
    public const DAILY_PAYROLL =
        'daily_payroll';

    public const WEEKLY_PAYROLL =
        'weekly_payroll';

    public const EXCEPTION_REPORT =
        'exception_report';


    private ReportDeliveryScheduleRepository $schedules;


    public function __construct(
        ReportDeliveryScheduleRepository $schedules
    )
    {
        $this->schedules =
            $schedules;
    }


    /**
     * @return array<int,array<string,mixed>>
     */
    public function all(): array
    {
        return $this->schedules->all();
    }


    /**
     * @return array<string,mixed>|null
     */
    public function get(
        string $reportType
    ): ?array
    {
        return $this->schedules->findByType(
            $reportType
        );
    }


    /**
     * @return array{
     *     success:bool,
     *     errors:array<string,string>
     * }
     */
    public function updateDaily(
        array $data
    ): array
    {
        return $this->update(
            self::DAILY_PAYROLL,
            $data
        );
    }


    /**
     * @return array{
     *     success:bool,
     *     errors:array<string,string>
     * }
     */
    public function updateWeekly(
        array $data
    ): array
    {
        return $this->update(
            self::WEEKLY_PAYROLL,
            $data
        );
    }


    /**
     * @return array{
     *     success:bool,
     *     errors:array<string,string>
     * }
     */
    public function update(
        string $reportType,
        array $data
    ): array
    {
        $this->assertSupportedType(
            $reportType
        );


        $errors = [];


        $sendTime =
            trim(
                (string)(
                    $data['send_time']
                    ??
                    ''
                )
            );


        if (
            !preg_match(
                '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
                $sendTime
            )
        ) {
            $errors['send_time'] =
                'Send time must use the HH:MM format.';
        }


        $enabled =
            isset(
                $data['enabled']
            );


        $weekdaysOnly =
            $reportType === self::DAILY_PAYROLL
            &&
            isset(
                $data['weekdays_only']
            );


        $sendDayOfWeek =
            null;


        if ($reportType === self::WEEKLY_PAYROLL) {

            $rawDay =
                trim(
                    (string)(
                        $data['send_day_of_week']
                        ??
                        ''
                    )
                );


            if (
                !preg_match(
                    '/^[1-7]$/',
                    $rawDay
                )
            ) {
                $errors['send_day_of_week'] =
                    'Select a valid weekly delivery day.';

            } else {

                $sendDayOfWeek =
                    (int)$rawDay;
            }
        }


        if (!empty($errors)) {

            return [
                'success' =>
                    false,

                'errors' =>
                    $errors
            ];
        }


        $updated =
            $this->schedules->update(
                $reportType,
                $enabled,
                $sendTime,
                $weekdaysOnly,
                $sendDayOfWeek
            );


        return [
            'success' =>
                $updated,

            'errors' =>
                $updated
                    ? []
                    : [
                        'schedule' =>
                            'Unable to update the report schedule.'
                    ]
        ];
    }


    public function isDue(
        string $reportType,
        ?DateTimeImmutable $now = null
    ): bool
    {
        $this->assertSupportedType(
            $reportType
        );


        $schedule =
            $this->schedules->findByType(
                $reportType
            );


        if (
            !$schedule
            ||
            !(bool)$schedule['enabled']
        ) {
            return false;
        }


        $timezone =
            new DateTimeZone(
                date_default_timezone_get()
            );


        $now =
            $now
            ??
            new DateTimeImmutable(
                'now',
                $timezone
            );


        $now =
            $now->setTimezone(
                $timezone
            );


        if (
            !empty(
                $schedule['weekdays_only']
            )
            &&
            (int)$now->format(
                'N'
            ) > 5
        ) {
            return false;
        }


        if ($reportType === self::WEEKLY_PAYROLL) {

            $scheduledDay =
                (int)(
                    $schedule['send_day_of_week']
                    ??
                    0
                );


            if (
                $scheduledDay < 1
                ||
                $scheduledDay > 7
                ||
                (int)$now->format(
                    'N'
                ) !== $scheduledDay
            ) {
                return false;
            }
        }


        $scheduledTime =
            DateTimeImmutable::createFromFormat(
                '!Y-m-d H:i',
                $now->format(
                    'Y-m-d'
                )
                .
                ' '
                .
                (string)$schedule['send_time'],
                $timezone
            );


        if (!$scheduledTime) {
            return false;
        }


        if ($now < $scheduledTime) {
            return false;
        }


        $lastSentAt =
            trim(
                (string)(
                    $schedule['last_sent_at']
                    ??
                    ''
                )
            );


        if ($lastSentAt === '') {
            return true;
        }


        $lastSent =
            new DateTimeImmutable(
                $lastSentAt,
                new DateTimeZone(
                    'UTC'
                )
            );


        $lastSent =
            $lastSent->setTimezone(
                $timezone
            );


        return
            $lastSent->format(
                'Y-m-d'
            )
            !==
            $now->format(
                'Y-m-d'
            );
    }


    public function markSent(
        string $reportType
    ): bool
    {
        $this->assertSupportedType(
            $reportType
        );


        $utcNow =
            new DateTimeImmutable(
                'now',
                new DateTimeZone(
                    'UTC'
                )
            );


        return $this->schedules->markSent(
            $reportType,
            $utcNow->format(
                'Y-m-d H:i:s'
            )
        );
    }


    public function markFailed(
        string $reportType,
        string $error
    ): bool
    {
        $this->assertSupportedType(
            $reportType
        );


        return $this->schedules->markFailed(
            $reportType,
            $error
        );
    }


    /**
     * Return a date within the previous completed seven-day period.
     *
     * PunchReportService uses this date to determine the company workweek.
     */
    public function previousWeekReferenceDate(
        ?DateTimeImmutable $now = null
    ): string
    {
        $timezone =
            new DateTimeZone(
                date_default_timezone_get()
            );


        $now =
            $now
            ??
            new DateTimeImmutable(
                'now',
                $timezone
            );


        return
            $now
                ->setTimezone(
                    $timezone
                )
                ->modify(
                    '-7 days'
                )
                ->format(
                    'Y-m-d'
                );
    }


    /**
     * @return array<int,string>
     */
    public function dayOptions(): array
    {
        return [
            1 =>
                'Monday',

            2 =>
                'Tuesday',

            3 =>
                'Wednesday',

            4 =>
                'Thursday',

            5 =>
                'Friday',

            6 =>
                'Saturday',

            7 =>
                'Sunday'
        ];
    }


    private function assertSupportedType(
        string $reportType
    ): void
    {
        if (
            !in_array(
                $reportType,
                [
                    self::DAILY_PAYROLL,
                    self::WEEKLY_PAYROLL,
                    self::EXCEPTION_REPORT
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'Unsupported report schedule type: '
                .
                $reportType
            );
        }
    }
}
