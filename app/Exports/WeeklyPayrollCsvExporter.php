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
                'Total Overtime Hours',
                'Payable Hours',
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

                number_format(
                    (float)(
                        $employee['gross_hours']
                        ??
                        0
                    ),
                    2,
                    '.',
                    ''
                ),

                number_format(
                    (float)(
                        $employee['regular_hours']
                        ??
                        0
                    ),
                    2,
                    '.',
                    ''
                ),

                number_format(
                    (float)(
                        $employee['daily_overtime_hours']
                        ??
                        0
                    ),
                    2,
                    '.',
                    ''
                ),

                number_format(
                    (float)(
                        $employee['weekly_overtime_hours']
                        ??
                        0
                    ),
                    2,
                    '.',
                    ''
                ),

                number_format(
                    (float)(
                        $employee['overtime_hours']
                        ??
                        0
                    ),
                    2,
                    '.',
                    ''
                ),

                number_format(
                    (float)(
                        $employee['total_hours']
                        ??
                        0
                    ),
                    2,
                    '.',
                    ''
                ),

                $this->status(
                    $employee['complete']
                    ??
                    false
                ),

                $this->errors(
                    $employee['errors']
                    ??
                    []
                )
            ];
        }


        return $this->createCsv(
            $rows
        );
    }
}
