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


                $dailyOvertimeHours =
                    (float)(
                        $day['daily_overtime_hours']
                        ??
                        $day['overtime_hours']
                        ??
                        0
                    );


                /*
                 * Weekly overtime is calculated at the weekly level.
                 * Individual daily rows therefore record zero weekly
                 * overtime hours.
                 */
                $weeklyOvertimeHours =
                    0.0;


                $doubleTimeHours =
                    (float)(
                        $day['double_time_hours']
                        ??
                        0
                    );


                $totalOvertimeHours =
                    (float)(
                        $day['overtime_hours']
                        ??
                        $dailyOvertimeHours
                    );


                $premiumHours =
                    (float)(
                        $day['premium_hours']
                        ??
                        (
                            $totalOvertimeHours
                            +
                            $doubleTimeHours
                        )
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
                '0.00',
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
                $summary['totals']['daily_overtime_hours']
                ??
                0
            ),
            $this->hours(
                $summary['totals']['weekly_overtime_hours']
                ??
                0
            ),
            $this->hours(
                $summary['totals']['double_time_hours']
                ??
                0
            ),
            $this->hours(
                $summary['totals']['overtime_hours']
                ??
                0
            ),
            $this->hours(
                $summary['totals']['premium_hours']
                ??
                (
                    (
                        $summary['totals']['overtime_hours']
                        ??
                        0
                    )
                    +
                    (
                        $summary['totals']['double_time_hours']
                        ??
                        0
                    )
                )
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


        $this->appendPayrollPeriodMetadata(
            $rows,
            $summary
        );


        return $this->createCsv(
            $rows
        );
    }


    /**
     * @param array<int,array<int,mixed>> $rows
     * @param array<string,mixed> $summary
     */
    private function appendPayrollPeriodMetadata(
        array &$rows,
        array $summary
    ): void
    {
        $association =
            (string)(
                $summary['payroll_period_association']
                ??
                'none'
            );


        $associationMessage =
            (string)(
                $summary[
                    'payroll_period_association_message'
                ]
                ??
                'This report is not associated with a payroll period.'
            );


        $rows[] = [];


        $rows[] = [
            'Payroll-Period Association',
            $this->associationLabel(
                $association
            )
        ];


        $rows[] = [
            'Payroll-Period Association Message',
            $associationMessage
        ];


        $payrollPeriod =
            $summary['payroll_period']
            ??
            null;


        if (
            $association !== 'exact'
            ||
            !is_array(
                $payrollPeriod
            )
        ) {

            $rows[] = [
                'Payroll Period ID',
                ''
            ];


            $rows[] = [
                'Payroll Period Name',
                ''
            ];


            $rows[] = [
                'Payroll Workflow Status',
                'Not Associated'
            ];


            $rows[] = [
                'Open Payroll Exceptions',
                ''
            ];


            $rows[] = [
                'Total Payroll Exceptions',
                ''
            ];


            $rows[] = [
                'Approval Blocked',
                ''
            ];


            return;
        }


        $rows[] = [
            'Payroll Period ID',
            (int)(
                $payrollPeriod['id']
                ??
                0
            )
        ];


        $rows[] = [
            'Payroll Period Name',
            (string)(
                $payrollPeriod['period_name']
                ??
                ''
            )
        ];


        $rows[] = [
            'Payroll Period Start',
            (string)(
                $payrollPeriod['start_date']
                ??
                ''
            )
        ];


        $rows[] = [
            'Payroll Period End',
            (string)(
                $payrollPeriod['end_date']
                ??
                ''
            )
        ];


        $rows[] = [
            'Payroll Workflow Status',
            $this->workflowStatusLabel(
                (string)(
                    $payrollPeriod['status']
                    ??
                    ''
                )
            )
        ];


        $rows[] = [
            'Open Payroll Exceptions',
            (int)(
                $payrollPeriod['open_exception_count']
                ??
                0
            )
        ];


        $rows[] = [
            'Total Payroll Exceptions',
            (int)(
                $payrollPeriod['total_exception_count']
                ??
                0
            )
        ];


        $rows[] = [
            'Approval Blocked',
            !empty(
                $payrollPeriod['approval_blocked']
            )
                ? 'Yes'
                : 'No'
        ];


        $rows[] = [
            'Created At',
            (string)(
                $payrollPeriod['created_at']
                ??
                ''
            )
        ];


        $rows[] = [
            'Created By',
            (string)(
                $payrollPeriod['created_by_username']
                ??
                ''
            )
        ];


        $rows[] = [
            'Review Started At',
            (string)(
                $payrollPeriod['review_started_at']
                ??
                ''
            )
        ];


        $rows[] = [
            'Reviewed By',
            (string)(
                $payrollPeriod['reviewed_by_username']
                ??
                ''
            )
        ];


        $rows[] = [
            'Approved At',
            (string)(
                $payrollPeriod['approved_at']
                ??
                ''
            )
        ];


        $rows[] = [
            'Approved By',
            (string)(
                $payrollPeriod['approved_by_username']
                ??
                ''
            )
        ];


        $rows[] = [
            'Locked At',
            (string)(
                $payrollPeriod['locked_at']
                ??
                ''
            )
        ];


        $rows[] = [
            'Locked By',
            (string)(
                $payrollPeriod['locked_by_username']
                ??
                ''
            )
        ];


        $rows[] = [
            'Payroll Period Detail',
            (string)(
                $payrollPeriod['detail_url']
                ??
                ''
            )
        ];
    }


    private function associationLabel(
        string $association
    ): string
    {
        return
            match ($association) {

                'exact' =>
                    'Exact Match',

                'partial_overlap' =>
                    'Partial Overlap',

                'none' =>
                    'No Association',

                default =>
                    ucwords(
                        str_replace(
                            '_',
                            ' ',
                            $association
                        )
                    )
            };
    }


    private function workflowStatusLabel(
        string $status
    ): string
    {
        if ($status === '') {

            return 'Unknown';
        }


        return ucwords(
            str_replace(
                '_',
                ' ',
                $status
            )
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
