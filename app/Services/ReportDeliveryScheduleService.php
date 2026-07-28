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
        $this->assertSupportedType(
            $reportType
        );


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
    public function updateException(
        array $data
    ): array
    {
        return $this->update(
            self::EXCEPTION_REPORT,
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


        $enabled =
            $this->checkboxValue(
                $data['enabled']
                ??
                null
            );


        $sendTime =
            trim(
                (string)(
                    $data['send_time']
                    ??
                    ''
                )
            );


        if (!$this->validTime($sendTime)) {
            $errors['send_time'] =
                'Enter a valid delivery time.';
        }


        $weekdaysOnly =
            false;


        $sendDayOfWeek =
            null;


        if (
            $reportType
            ===
            self::WEEKLY_PAYROLL
        ) {
            $sendDayOfWeek =
                filter_var(
                    $data['send_day_of_week']
                    ??
                    null,
                    FILTER_VALIDATE_INT,
                    [
                        'options' => [
                            'min_range' =>
                                1,

                            'max_range' =>
                                7
                        ]
                    ]
                );


            if ($sendDayOfWeek === false) {
                $errors['send_day_of_week'] =
                    'Select a valid weekly delivery day.';
            }


            $sendDayOfWeek =
                $sendDayOfWeek === false
                    ? null
                    : (int)$sendDayOfWeek;

        } else {

            $weekdaysOnly =
                $this->checkboxValue(
                    $data['weekdays_only']
                    ??
                    null
                );
        }


        if ($errors !== []) {
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
            !(bool)(
                $schedule['enabled']
                ??
                false
            )
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


        if (
            $reportType
            ===
            self::WEEKLY_PAYROLL
        ) {
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


        $sendTime =
            trim(
                (string)(
                    $schedule['send_time']
                    ??
                    ''
                )
            );


        if (!$this->validTime($sendTime)) {
            return false;
        }


        $scheduledAt =
            DateTimeImmutable::createFromFormat(
                '!Y-m-d H:i',
                $now->format(
                    'Y-m-d'
                )
                .
                ' '
                .
                $sendTime,
                $timezone
            );


        if (
            !$scheduledAt
            ||
            $now < $scheduledAt
        ) {
            return false;
        }


        $lastSentValue =
            trim(
                (string)(
                    $schedule['last_sent_at']
                    ??
                    ''
                )
            );


        if ($lastSentValue === '') {
            return true;
        }


        $lastSent =
            DateTimeImmutable::createFromFormat(
                '!Y-m-d H:i:s',
                $lastSentValue,
                new DateTimeZone(
                    'UTC'
                )
            );


        if (!$lastSent) {
            return true;
        }


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
     * Return a date inside the previous completed seven-day period.
     *
     * PunchReportService uses the reference date to determine the company
     * workweek and its beginning and ending dates.
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


    private function validTime(
        string $value
    ): bool
    {
        return
            preg_match(
                '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
                $value
            )
            ===
            1;
    }


    private function checkboxValue(
        mixed $value
    ): bool
    {
        return in_array(
            $value,
            [
                1,
                '1',
                true,
                'true',
                'on',
                'yes'
            ],
            true
        );
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
