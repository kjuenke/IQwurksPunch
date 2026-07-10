<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\ReportScheduleRepository;
use DateTimeImmutable;
use DateTimeZone;

class ReportScheduleService
{
    private ReportScheduleRepository $schedules;


    public function __construct(
        ReportScheduleRepository $schedules
    )
    {
        $this->schedules = $schedules;
    }


    public function get(): ?array
    {
        return $this->schedules->get();
    }


    public function update(
        array $data
    ): array
    {
        $sendTime =
            trim(
                $data['send_time']
                ?? ''
            );


        if (
            !preg_match(
                '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
                $sendTime
            )
        ) {
            return [
                'success' => false,
                'errors' => [
                    'send_time' =>
                        'Send time must use the HH:MM format.'
                ]
            ];
        }


        $enabled =
            isset(
                $data['enabled']
            );


        $weekdaysOnly =
            isset(
                $data['weekdays_only']
            );


        $updated =
            $this->schedules->update(
                $enabled,
                $sendTime,
                $weekdaysOnly
            );


        return [
            'success' => $updated,
            'errors' => []
        ];
    }


    public function isDue(
        ?DateTimeImmutable $now = null
    ): bool
    {
        $schedule =
            $this->schedules->get();


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
            (bool)$schedule['weekdays_only']
            &&
            (int)$now->format('N') > 5
        ) {
            return false;
        }


        $scheduledTime =
            DateTimeImmutable::createFromFormat(
                'Y-m-d H:i',
                $now->format('Y-m-d')
                . ' '
                . $schedule['send_time'],
                $timezone
            );


        if (!$scheduledTime) {
            return false;
        }


        if ($now < $scheduledTime) {
            return false;
        }


        if (
            empty(
                $schedule['last_sent_at']
            )
        ) {
            return true;
        }


        $lastSent =
            new DateTimeImmutable(
                $schedule['last_sent_at'],
                new DateTimeZone('UTC')
            );


        $lastSent =
            $lastSent->setTimezone(
                $timezone
            );


        return
            $lastSent->format('Y-m-d')
            !==
            $now->format('Y-m-d');
    }


    public function markSent(): bool
    {
        $utcNow =
            new DateTimeImmutable(
                'now',
                new DateTimeZone('UTC')
            );


        return $this->schedules->markSent(
            $utcNow->format(
                'Y-m-d H:i:s'
            )
        );
    }
}
