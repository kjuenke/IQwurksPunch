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
                'Overtime Hours',
                'Payable Hours',
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
