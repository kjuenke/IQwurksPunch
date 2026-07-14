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


        $automaticMealEnabled =
            (bool)(
                $policy['meal_deduction_enabled']
                ??
                false
            );


        $automaticMealMinutes =
            $this->nonNegativeInteger(
                $policy['meal_deduction_minutes']
                ??
                0,
                'meal_deduction_minutes'
            );


        $paidBreakMinutes =
            $this->nonNegativeInteger(
                $policy['paid_break_minutes']
                ??
                0,
                'paid_break_minutes'
            );


        $sortedPunches =
            $this->sortPunches(
                $punches
            );


        $grossWorkedSeconds = 0;

        $mealSeconds = 0;

        $breakSeconds = 0;

        $clockIn = null;

        $mealOut = null;

        $breakOut = null;

        $workPeriods = [];

        $mealPeriods = [];

        $breakPeriods = [];

        $errors = [];


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


                if ($mealOut !== null) {

                    $errors[] =
                        'A meal-out punch does not have a matching meal-in punch.';


                    $mealOut = null;
                }


                if ($breakOut !== null) {

                    $errors[] =
                        'A break-out punch does not have a matching break-in punch.';


                    $breakOut = null;
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


                $grossWorkedSeconds +=
                    $periodSeconds;


                $workPeriods[] =
                    $this->period(
                        $clockIn,
                        $clockOut,
                        $periodSeconds
                    );


                $clockIn = null;


                continue;
            }


            if ($type === 'meal_out') {

                if ($clockIn === null) {

                    $errors[] =
                        'A meal-out punch occurred while the employee was not clocked in.';


                    continue;
                }


                if ($mealOut !== null) {

                    $errors[] =
                        'A meal-out punch occurred before the previous meal period was closed.';


                    continue;
                }


                $mealOut =
                    $time;


                continue;
            }


            if ($type === 'meal_in') {

                if ($mealOut === null) {

                    $errors[] =
                        'A meal-in punch occurred without a matching meal-out punch.';


                    continue;
                }


                if ($time < $mealOut) {

                    $errors[] =
                        'A meal-in punch occurred before its matching meal-out punch.';


                    $mealOut = null;


                    continue;
                }


                $periodSeconds =
                    $time->getTimestamp()
                    -
                    $mealOut->getTimestamp();


                $mealSeconds +=
                    $periodSeconds;


                $mealPeriods[] =
                    $this->period(
                        $mealOut,
                        $time,
                        $periodSeconds
                    );


                $mealOut = null;


                continue;
            }


            if ($type === 'break_out') {

                if ($clockIn === null) {

                    $errors[] =
                        'A break-out punch occurred while the employee was not clocked in.';


                    continue;
                }


                if ($breakOut !== null) {

                    $errors[] =
                        'A break-out punch occurred before the previous break was closed.';


                    continue;
                }


                $breakOut =
                    $time;


                continue;
            }


            if ($type === 'break_in') {

                if ($breakOut === null) {

                    $errors[] =
                        'A break-in punch occurred without a matching break-out punch.';


                    continue;
                }


                if ($time < $breakOut) {

                    $errors[] =
                        'A break-in punch occurred before its matching break-out punch.';


                    $breakOut = null;


                    continue;
                }


                $periodSeconds =
                    $time->getTimestamp()
                    -
                    $breakOut->getTimestamp();


                $breakSeconds +=
                    $periodSeconds;


                $breakPeriods[] =
                    $this->period(
                        $breakOut,
                        $time,
                        $periodSeconds
                    );


                $breakOut = null;
            }
        }


        if ($clockIn !== null) {

            $errors[] =
                'A clock-in punch does not have a matching clock-out punch.';
        }


        if ($mealOut !== null) {

            $errors[] =
                'A meal-out punch does not have a matching meal-in punch.';
        }


        if ($breakOut !== null) {

            $errors[] =
                'A break-out punch does not have a matching break-in punch.';
        }


        $automaticMealAppliedMinutes = 0;


        if (
            $automaticMealEnabled
            &&
            empty($mealPeriods)
            &&
            $grossWorkedSeconds > 0
            &&
            $automaticMealMinutes > 0
        ) {

            $automaticMealAppliedMinutes =
                min(
                    $automaticMealMinutes,
                    (int)floor(
                        $grossWorkedSeconds
                        /
                        60
                    )
                );
        }


        $paidBreakSeconds =
            min(
                $breakSeconds,
                $paidBreakMinutes
                *
                60
            );


        $unpaidBreakSeconds =
            max(
                0,
                $breakSeconds
                -
                $paidBreakSeconds
            );


        $deductionSeconds =
            $mealSeconds
            +
            $unpaidBreakSeconds
            +
            (
                $automaticMealAppliedMinutes
                *
                60
            );


        $payableSeconds =
            max(
                0,
                $grossWorkedSeconds
                -
                $deductionSeconds
            );


        $grossHours =
            $this->secondsToHours(
                $grossWorkedSeconds
            );


        $totalHours =
            $this->secondsToHours(
                $payableSeconds
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

            'meal_periods' =>
                $mealPeriods,

            'break_periods' =>
                $breakPeriods,

            'gross_hours' =>
                round(
                    $grossHours,
                    2
                ),

            'recorded_meal_minutes' =>
                (int)round(
                    $mealSeconds
                    /
                    60
                ),

            'automatic_meal_deduction_minutes' =>
                $automaticMealAppliedMinutes,

            'recorded_break_minutes' =>
                (int)round(
                    $breakSeconds
                    /
                    60
                ),

            'paid_break_minutes' =>
                (int)round(
                    $paidBreakSeconds
                    /
                    60
                ),

            'unpaid_break_minutes' =>
                (int)round(
                    $unpaidBreakSeconds
                    /
                    60
                ),

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


        $utcTime =
            new DateTimeImmutable(
                $value,
                new DateTimeZone('UTC')
            );


        return $utcTime->setTimezone(
            $timezone
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


    /**
     * @return array<string,mixed>
     */
    private function period(
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        int $seconds
    ): array
    {
        return [
            'start' =>
                $start->format(
                    'Y-m-d H:i:s'
                ),

            'end' =>
                $end->format(
                    'Y-m-d H:i:s'
                ),

            'seconds' =>
                $seconds,

            'hours' =>
                round(
                    $this->secondsToHours(
                        $seconds
                    ),
                    2
                )
        ];
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
        if (!is_numeric($value)) {

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
        if (!is_numeric($value)) {

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
