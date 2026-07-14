<?php
declare(strict_types=1);

namespace App\Payroll;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class PayrollCalculator
{
    /**
     * @param array<int,array<string,mixed>> $punches
     * @param array<string,mixed> $policy
     *
     * @return array<string,mixed>
     */
    public function calculateDay(
        array $punches,
        array $policy
    ): array
    {
        $timezone =
            $this->timezone(
                $policy['timezone']
                ??
                'America/Los_Angeles'
            );


        $roundingMinutes =
            $this->nonNegativeInteger(
                $policy['rounding_minutes']
                ??
                0,
                'rounding_minutes'
            );


        $roundingMode =
            $this->roundingMode(
                $policy['rounding_mode']
                ??
                'nearest'
            );


        $dailyOvertimeHours =
            $this->nonNegativeFloat(
                $policy['daily_overtime_hours']
                ??
                8,
                'daily_overtime_hours'
            );


        $mealDeductionEnabled =
            (bool)(
                $policy['meal_deduction_enabled']
                ??
                false
            );


        $mealDeductionMinutes =
            $this->nonNegativeInteger(
                $policy['meal_deduction_minutes']
                ??
                0,
                'meal_deduction_minutes'
            );


        $sortedPunches =
            $this->sortPunches(
                $punches
            );


        $workedSeconds = 0;

        $clockIn = null;

        $workPeriods = [];

        $errors = [];

        $mealPunchFound = false;


        foreach ($sortedPunches as $punch) {

            $type =
                (string)(
                    $punch['punch_type']
                    ??
                    ''
                );


            $time =
                $this->parsePunchTime(
                    $punch['punch_time']
                    ??
                    null,
                    $timezone
                );


            if (
                $type === 'meal_out'
                ||
                $type === 'meal_in'
            ) {
                $mealPunchFound = true;
            }


            if ($type === 'clock_in') {

                if ($clockIn !== null) {

                    $errors[] =
                        'A clock-in punch occurred before the previous work period was closed.';


                    continue;
                }


                $clockIn =
                    $this->roundTime(
                        $time,
                        $roundingMinutes,
                        $roundingMode
                    );


                continue;
            }


            if ($type === 'clock_out') {

                if ($clockIn === null) {

                    $errors[] =
                        'A clock-out punch occurred without a matching clock-in punch.';


                    continue;
                }


                $clockOut =
                    $this->roundTime(
                        $time,
                        $roundingMinutes,
                        $roundingMode
                    );


                if ($clockOut < $clockIn) {

                    $errors[] =
                        'A clock-out punch occurred before its matching clock-in punch.';


                    $clockIn = null;


                    continue;
                }


                $periodSeconds =
                    $clockOut->getTimestamp()
                    -
                    $clockIn->getTimestamp();


                $workedSeconds +=
                    $periodSeconds;


                $workPeriods[] = [
                    'clock_in' =>
                        $clockIn->format(
                            'Y-m-d H:i:s'
                        ),

                    'clock_out' =>
                        $clockOut->format(
                            'Y-m-d H:i:s'
                        ),

                    'seconds' =>
                        $periodSeconds,

                    'hours' =>
                        $this->secondsToHours(
                            $periodSeconds
                        )
                ];


                $clockIn = null;
            }
        }


        if ($clockIn !== null) {

            $errors[] =
                'A clock-in punch does not have a matching clock-out punch.';
        }


        $mealDeductionAppliedMinutes = 0;


        if (
            $mealDeductionEnabled
            &&
            !$mealPunchFound
            &&
            $workedSeconds > 0
            &&
            $mealDeductionMinutes > 0
        ) {

            $mealDeductionAppliedMinutes =
                min(
                    $mealDeductionMinutes,
                    (int)floor(
                        $workedSeconds / 60
                    )
                );


            $workedSeconds -=
                $mealDeductionAppliedMinutes
                *
                60;
        }


        $totalHours =
            $this->secondsToHours(
                $workedSeconds
            );


        $regularHours =
            min(
                $totalHours,
                $dailyOvertimeHours
            );


        $overtimeHours =
            max(
                0,
                $totalHours
                -
                $dailyOvertimeHours
            );


        return [
            'complete' =>
                empty($errors),

            'errors' =>
                $errors,

            'work_periods' =>
                $workPeriods,

            'gross_hours' =>
                $this->secondsToHours(
                    $workedSeconds
                    +
                    (
                        $mealDeductionAppliedMinutes
                        *
                        60
                    )
                ),

            'meal_deduction_minutes' =>
                $mealDeductionAppliedMinutes,

            'total_hours' =>
                round(
                    $totalHours,
                    2
                ),

            'regular_hours' =>
                round(
                    $regularHours,
                    2
                ),

            'overtime_hours' =>
                round(
                    $overtimeHours,
                    2
                )
        ];
    }


    /**
     * @param array<int,array<string,mixed>> $punches
     *
     * @return array<int,array<string,mixed>>
     */
    private function sortPunches(
        array $punches
    ): array
    {
        usort(
            $punches,
            static function (
                array $first,
                array $second
            ): int {

                return strcmp(
                    (string)(
                        $first['punch_time']
                        ??
                        ''
                    ),
                    (string)(
                        $second['punch_time']
                        ??
                        ''
                    )
                );
            }
        );


        return $punches;
    }


    private function parsePunchTime(
        mixed $value,
        DateTimeZone $timezone
    ): DateTimeImmutable
    {
        if (
            !is_string(
                $value
            )
            ||
            trim(
                $value
            ) === ''
        ) {
            throw new InvalidArgumentException(
                'Every punch must contain a valid punch_time value.'
            );
        }


        return new DateTimeImmutable(
            $value,
            new DateTimeZone('UTC')
        );
    }


    private function roundTime(
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


    private function timezone(
        mixed $value
    ): DateTimeZone
    {
        if (
            !is_string(
                $value
            )
            ||
            !in_array(
                $value,
                timezone_identifiers_list(),
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Invalid payroll timezone.'
            );
        }


        return new DateTimeZone(
            $value
        );
    }


    private function roundingMode(
        mixed $value
    ): string
    {
        if (
            !is_string(
                $value
            )
            ||
            !in_array(
                $value,
                [
                    'nearest',
                    'up',
                    'down'
                ],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Invalid payroll rounding mode.'
            );
        }


        return $value;
    }


    private function nonNegativeInteger(
        mixed $value,
        string $name
    ): int
    {
        if (
            !is_numeric(
                $value
            )
        ) {
            throw new InvalidArgumentException(
                "{$name} must be numeric."
            );
        }


        $number =
            (int)$value;


        if ($number < 0) {

            throw new InvalidArgumentException(
                "{$name} cannot be negative."
            );
        }


        return $number;
    }


    private function nonNegativeFloat(
        mixed $value,
        string $name
    ): float
    {
        if (
            !is_numeric(
                $value
            )
        ) {
            throw new InvalidArgumentException(
                "{$name} must be numeric."
            );
        }


        $number =
            (float)$value;


        if ($number < 0) {

            throw new InvalidArgumentException(
                "{$name} cannot be negative."
            );
        }


        return $number;
    }


    private function secondsToHours(
        int $seconds
    ): float
    {
        return
            $seconds
            /
            3600;
    }
}
