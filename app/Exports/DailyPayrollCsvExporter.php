<?php
declare(strict_types=1);

namespace App\Exports;

final class DailyPayrollCsvExporter extends CsvExporter
{
    /**
     * @param array<int,array<string,mixed>> $summary
     */
    public function export(
        array $summary
    ): string
    {
        $rows = [
            [
                'Date',
                'Employee Number',
                'Employee Name',
                'Department',
                'Gross Hours',
                'Recorded Meal Minutes',
                'Automatic Meal Deduction Minutes',
                'Total Meal Deduction Minutes',
                'Recorded Break Minutes',
                'Paid Break Minutes',
                'Unpaid Break Minutes',
                'Regular Hours',
                'Daily Overtime Hours',
                'Weekly Overtime Hours',
                'Double-Time Hours',
                'Total Overtime Hours',
                'Premium Hours',
                'Total Payable Hours',
                'Status',
                'Warnings'
            ]
        ];


        foreach ($summary as $employee) {

            $recordedMealMinutes =
                (int)(
                    $employee['recorded_meal_minutes']
                    ??
                    0
                );


            $automaticMealMinutes =
                (int)(
                    $employee['automatic_meal_deduction_minutes']
                    ??
                    0
                );


            $dailyOvertimeHours =
                (float)(
                    $employee['daily_overtime_hours']
                    ??
                    $employee['overtime_hours']
                    ??
                    0
                );


            /*
             * Weekly overtime is calculated only after the complete
             * payroll week is assembled. A daily report therefore
             * carries zero weekly overtime hours.
             */
            $weeklyOvertimeHours =
                0.0;


            $doubleTimeHours =
                (float)(
                    $employee['double_time_hours']
                    ??
                    0
                );


            $totalOvertimeHours =
                (float)(
                    $employee['overtime_hours']
                    ??
                    $dailyOvertimeHours
                );


            $premiumHours =
                (float)(
                    $employee['premium_hours']
                    ??
                    (
                        $totalOvertimeHours
                        +
                        $doubleTimeHours
                    )
                );


            $rows[] = [
                (string)(
                    $employee['date']
                    ??
                    ''
                ),

                (string)(
                    $employee['employee_number']
                    ??
                    ''
                ),

                (string)(
                    $employee['name']
                    ??
                    ''
                ),

                (string)(
                    $employee['department']
                    ??
                    ''
                ),

                $this->hours(
                    $employee['gross_hours']
                    ??
                    0
                ),

                $recordedMealMinutes,

                $automaticMealMinutes,

                $recordedMealMinutes
                +
                $automaticMealMinutes,

                (int)(
                    $employee['recorded_break_minutes']
                    ??
                    0
                ),

                (int)(
                    $employee['paid_break_minutes']
                    ??
                    0
                ),

                (int)(
                    $employee['unpaid_break_minutes']
                    ??
                    0
                ),

                $this->hours(
                    $employee['regular_hours']
                    ??
                    0
                ),

                $this->hours(
                    $dailyOvertimeHours
                ),

                $this->hours(
                    $weeklyOvertimeHours
                ),

                $this->hours(
                    $doubleTimeHours
                ),

                $this->hours(
                    $totalOvertimeHours
                ),

                $this->hours(
                    $premiumHours
                ),

                $this->hours(
                    $employee['total_hours']
                    ??
                    0
                ),

                $this->status(
                    $employee['complete']
                    ??
                    false
                ),

                $this->errors(
                    is_array(
                        $employee['errors']
                        ??
                        null
                    )
                        ? $employee['errors']
                        : []
                )
            ];
        }


        return $this->createCsv(
            $rows
        );
    }


    private function hours(
        mixed $value
    ): string
    {
        return number_format(
            (float)$value,
            2,
            '.',
            ''
        );
    }
}
