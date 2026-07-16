<?php
declare(strict_types=1);

namespace App\Exports;

final class PayrollWorkspaceCsvExporter extends CsvExporter
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
                'Report Start',
                'Report End',
                'Employee Number',
                'Employee Name',
                'Department',
                'Date',
                'Gross Hours',
                'Recorded Meal Minutes',
                'Automatic Meal Deduction Minutes',
                'Total Meal Deduction Minutes',
                'Recorded Break Minutes',
                'Paid Break Minutes',
                'Unpaid Break Minutes',
                'Regular Hours',
                'Daily Overtime Hours',
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
            foreach (
                $employee['days']
                ??
                []
                as $day
            ) {
                $recordedMealMinutes =
                    (int)(
                        $day['recorded_meal_minutes']
                        ??
                        0
                    );


                $automaticMealMinutes =
                    (int)(
                        $day['automatic_meal_deduction_minutes']
                        ??
                        0
                    );


                $rows[] = [
                    (string)(
                        $summary['start_date']
                        ??
                        ''
                    ),

                    (string)(
                        $summary['end_date']
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

                    (string)(
                        $day['date']
                        ??
                        ''
                    ),

                    $this->hours(
                        $day['gross_hours']
                        ??
                        0
                    ),

                    $recordedMealMinutes,

                    $automaticMealMinutes,

                    $recordedMealMinutes
                    +
                    $automaticMealMinutes,

                    (int)(
                        $day['recorded_break_minutes']
                        ??
                        0
                    ),

                    (int)(
                        $day['paid_break_minutes']
                        ??
                        0
                    ),

                    (int)(
                        $day['unpaid_break_minutes']
                        ??
                        0
                    ),

                    $this->hours(
                        $day['regular_hours']
                        ??
                        0
                    ),

                    $this->hours(
                        $day['overtime_hours']
                        ??
                        0
                    ),

                    $this->hours(
                        $day['total_hours']
                        ??
                        0
                    ),

                    $this->status(
                        $day['complete']
                        ??
                        false
                    ),

                    $this->errors(
                        is_array(
                            $day['errors']
                            ??
                            null
                        )
                            ? $day['errors']
                            : []
                    )
                ];
            }
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
                    $summary['start_date']
                    ??
                    ''
                ),

                (string)(
                    $summary['end_date']
                    ??
                    ''
                ),

                '',
                '',
                '',
                '',
                '0.00',
                0,
                0,
                0,
                0,
                0,
                0,
                '0.00',
                '0.00',
                '0.00',
                'No Data',
                'No payroll activity was found for the selected filters.'
            ];
        }


        $rows[] = [];


        $rows[] = [
            'Report Totals',
            '',
            '',
            '',
            '',
            '',
            $this->hours(
                $summary['totals']['gross_hours']
                ??
                0
            ),
            '',
            '',
            '',
            '',
            '',
            '',
            $this->hours(
                $summary['totals']['regular_hours']
                ??
                0
            ),
            $this->hours(
                $summary['totals']['overtime_hours']
                ??
                0
            ),
            $this->hours(
                $summary['totals']['total_hours']
                ??
                0
            ),
            '',
            ''
        ];


        $rows[] = [
            'Daily Overtime Total',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            $this->hours(
                $summary['totals']['daily_overtime_hours']
                ??
                0
            ),
            '',
            '',
            ''
        ];


        $rows[] = [
            'Weekly Overtime Total',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            $this->hours(
                $summary['totals']['weekly_overtime_hours']
                ??
                0
            ),
            '',
            '',
            ''
        ];


        $rows[] = [
            'Employee Count',
            (int)(
                $summary['totals']['employee_count']
                ??
                0
            )
        ];


        $rows[] = [
            'Payroll Issue Count',
            (int)(
                $summary['issue_count']
                ??
                0
            )
        ];


        $rows[] = [
            'Timezone',
            (string)(
                $summary['timezone']
                ??
                ''
            )
        ];


        $rows[] = [
            'Employee Filter',
            $summary['filters']['employee_id']
            ??
            'All'
        ];


        $rows[] = [
            'Department Filter',
            $summary['filters']['department']
            ??
            'All'
        ];


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
