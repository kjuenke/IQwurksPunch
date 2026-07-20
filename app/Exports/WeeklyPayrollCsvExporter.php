<?php
declare(strict_types=1);

namespace App\Exports;

final class WeeklyPayrollCsvExporter extends CsvExporter
{
    /**
     * @param array<string,mixed> $summary
     */
    public function export(
        array $summary
    ): string
    {
        $rows = [
            [
                'Week Start',
                'Week End',
                'Employee Number',
                'Employee Name',
                'Department',
                'Gross Hours',
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


        foreach (
            $summary['employees']
            ??
            []
            as $employee
        ) {
            $dailyOvertimeHours =
                (float)(
                    $employee['daily_overtime_hours']
                    ??
                    0
                );


            $weeklyOvertimeHours =
                (float)(
                    $employee['weekly_overtime_hours']
                    ??
                    0
                );


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
                    (
                        $dailyOvertimeHours
                        +
                        $weeklyOvertimeHours
                    )
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
                    $summary['week_start']
                    ??
                    ''
                ),

                (string)(
                    $summary['week_end']
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


        if (
            count(
                $rows
            )
            ===
            1
        ) {
            $rows[] = [
                (string)(
                    $summary['week_start']
                    ??
                    ''
                ),

                (string)(
                    $summary['week_end']
                    ??
                    ''
                ),

                '',
                '',
                '',
                '0.00',
                '0.00',
                '0.00',
                '0.00',
                '0.00',
                '0.00',
                '0.00',
                '0.00',
                'No Data',
                'No weekly payroll activity was found.'
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
