<?php
declare(strict_types=1);

namespace App\Payroll;

use InvalidArgumentException;

final class WeeklyPayrollCalculator
{
    /**
     * @param array<int,array<string,mixed>> $dailyResults
     *
     * @return array<string,mixed>
     */
    public function calculate(
        array $dailyResults,
        PayrollPolicy $policy
    ): array
    {
        $days = [];

        $errors = [];

        $complete = true;

        $grossHours = 0.0;

        $totalHours = 0.0;

        $dailyRegularHours = 0.0;

        $dailyOvertimeHours = 0.0;


        foreach ($dailyResults as $index => $dailyResult) {

            $day =
                $this->validateDay(
                    $dailyResult,
                    $index
                );


            $days[] =
                $day;


            $grossHours +=
                $day['gross_hours'];


            $totalHours +=
                $day['total_hours'];


            $dailyRegularHours +=
                $day['regular_hours'];


            $dailyOvertimeHours +=
                $day['overtime_hours'];


            if (!$day['complete']) {

                $complete = false;


                foreach ($day['errors'] as $error) {

                    $errors[] =
                        $this->dayError(
                            $day,
                            $index,
                            $error
                        );
                }
            }
        }


        $weeklyThreshold =
            $policy->weeklyOvertimeHours();


        $weeklyOvertimeHours =
            max(
                0,
                $dailyRegularHours
                -
                $weeklyThreshold
            );


        $regularHours =
            max(
                0,
                $dailyRegularHours
                -
                $weeklyOvertimeHours
            );


        $overtimeHours =
            $dailyOvertimeHours
            +
            $weeklyOvertimeHours;


        return [
            'complete' =>
                $complete,

            'errors' =>
                $errors,

            'days' =>
                $days,

            'gross_hours' =>
                round(
                    $grossHours,
                    2
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

            'daily_overtime_hours' =>
                round(
                    $dailyOvertimeHours,
                    2
                ),

            'weekly_overtime_hours' =>
                round(
                    $weeklyOvertimeHours,
                    2
                ),

            'overtime_hours' =>
                round(
                    $overtimeHours,
                    2
                ),

            'weekly_overtime_threshold' =>
                round(
                    $weeklyThreshold,
                    2
                )
        ];
    }


    /**
     * @param array<string,mixed> $dailyResult
     *
     * @return array<string,mixed>
     */
    private function validateDay(
        array $dailyResult,
        int $index
    ): array
    {
        $requiredNumericFields = [
            'gross_hours',
            'total_hours',
            'regular_hours',
            'overtime_hours'
        ];


        foreach ($requiredNumericFields as $field) {

            if (
                !array_key_exists(
                    $field,
                    $dailyResult
                )
                ||
                !is_numeric(
                    $dailyResult[$field]
                )
            ) {
                throw new InvalidArgumentException(
                    "Daily payroll result {$index} is missing a valid {$field} value."
                );
            }
        }


        $complete =
            (bool)(
                $dailyResult['complete']
                ??
                false
            );


        $errors =
            $dailyResult['errors']
            ??
            [];


        if (!is_array($errors)) {

            throw new InvalidArgumentException(
                "Daily payroll result {$index} contains an invalid errors value."
            );
        }


        $date =
            $dailyResult['date']
            ??
            null;


        if (
            $date !== null
            &&
            !is_string($date)
        ) {
            throw new InvalidArgumentException(
                "Daily payroll result {$index} contains an invalid date value."
            );
        }


        return [
            ...$dailyResult,

            'date' =>
                $date,

            'complete' =>
                $complete,

            'errors' =>
                array_values(
                    $errors
                ),

            'gross_hours' =>
                (float)$dailyResult['gross_hours'],

            'total_hours' =>
                (float)$dailyResult['total_hours'],

            'regular_hours' =>
                (float)$dailyResult['regular_hours'],

            'overtime_hours' =>
                (float)$dailyResult['overtime_hours']
        ];
    }


    private function dayError(
        array $day,
        int $index,
        mixed $error
    ): string
    {
        $label =
            !empty(
                $day['date']
            )
                ? $day['date']
                : 'Day '
                    .
                    ($index + 1);


        return
            $label
            .
            ': '
            .
            (string)$error;
    }
}
