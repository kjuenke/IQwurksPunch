<?php
declare(strict_types=1);

namespace App\Payroll;

use DateTimeImmutable;

final class PunchRounding
{
    public function round(
        DateTimeImmutable $time,
        int $intervalMinutes,
        string $mode
    ): DateTimeImmutable
    {
        if ($intervalMinutes === 0) {

            return $time;
        }


        $intervalSeconds =
            $intervalMinutes
            *
            60;


        $timestamp =
            $time->getTimestamp();


        $roundedTimestamp =
            match ($mode) {
                'up' =>
                    (int)(
                        ceil(
                            $timestamp
                            /
                            $intervalSeconds
                        )
                        *
                        $intervalSeconds
                    ),

                'down' =>
                    (int)(
                        floor(
                            $timestamp
                            /
                            $intervalSeconds
                        )
                        *
                        $intervalSeconds
                    ),

                default =>
                    (int)(
                        round(
                            $timestamp
                            /
                            $intervalSeconds
                        )
                        *
                        $intervalSeconds
                    )
            };


        return $time->setTimestamp(
            $roundedTimestamp
        );
    }
}
