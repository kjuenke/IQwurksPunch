<?php
declare(strict_types=1);

namespace App\Payroll;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class DailyPayrollCalculator
{
    private PunchRounding $rounding;


    public function __construct(
        ?PunchRounding $rounding = null
    )
    {
        $this->rounding =
            $rounding
            ??
            new PunchRounding();
    }


    /**
     * @param array<int,array<string,mixed>> $punches
     *
     * @return array<string,mixed>
     */
    public function calculate(
        array $punches,
        PayrollPolicy $policy
    ): array
    {
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
                    $policy->timezone()
                );


            if ($type === 'clock_in') {

                if ($clockIn !== null) {

                    $errors[] =
                        'A clock-in punch occurred before the previous work period was closed.';


                    continue;
                }


                $clockIn =
                    $this->rounding->round(
                        $time,
                        $policy->roundingMinutes(),
                        $policy->roundingMode()
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
                    $this->rounding->round(
                        $time,
                        $policy->roundingMinutes(),
                        $policy->roundingMode()
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


        $automaticMealAppliedMinutes =
            $this->automaticMealDeduction(
                $policy,
                $mealPeriods,
                $grossWorkedSeconds
            );


        $paidBreakSeconds =
            min(
                $breakSeconds,
                $policy->paidBreakMinutes()
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
                $policy->dailyOvertimeHours()
            );


        $overtimeHours =
            max(
                0,
                $totalHours
                -
                $policy->dailyOvertimeHours()
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
     * @param array<int,array<string,mixed>> $mealPeriods
     */
    private function automaticMealDeduction(
        PayrollPolicy $policy,
        array $mealPeriods,
        int $grossWorkedSeconds
    ): int
    {
        if (
            !$policy->mealDeductionEnabled()
            ||
            !empty($mealPeriods)
            ||
            $grossWorkedSeconds <= 0
            ||
            $policy->mealDeductionMinutes() <= 0
        ) {
            return 0;
        }


        return min(
            $policy->mealDeductionMinutes(),
            (int)floor(
                $grossWorkedSeconds
                /
                60
            )
        );
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
