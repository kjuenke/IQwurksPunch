<?php
declare(strict_types=1);

namespace App\Exports\Pdf;

use Dompdf\Dompdf;
use Dompdf\Options;
use RuntimeException;

final class PayrollWorkspacePdfExporter
{
    /**
     * @param array<string,mixed> $summary
     * @param array<string,mixed> $company
     */
    public function export(
        array $summary,
        array $company
    ): string
    {
        $options =
            new Options();


        $options->set(
            'defaultFont',
            'DejaVu Sans'
        );


        $options->set(
            'isRemoteEnabled',
            false
        );


        $dompdf =
            new Dompdf(
                $options
            );


        $dompdf->setPaper(
            'letter',
            'landscape'
        );


        $dompdf->loadHtml(
            $this->html(
                $summary,
                $company
            )
        );


        $dompdf->render();


        $output =
            $dompdf->output();


        if ($output === '') {

            throw new RuntimeException(
                'The Payroll Workspace PDF could not be generated.'
            );
        }


        return $output;
    }


    /**
     * @param array<string,mixed> $summary
     * @param array<string,mixed> $company
     */
    private function html(
        array $summary,
        array $company
    ): string
    {
        $companyName =
            $this->escape(
                (string)(
                    $company['company_name']
                    ??
                    'Company'
                )
            );


        $startDate =
            $this->escape(
                (string)(
                    $summary['start_date']
                    ??
                    ''
                )
            );


        $endDate =
            $this->escape(
                (string)(
                    $summary['end_date']
                    ??
                    ''
                )
            );


        $timezone =
            $this->escape(
                (string)(
                    $summary['timezone']
                    ??
                    $company['timezone']
                    ??
                    ''
                )
            );


        $employeeFilter =
            $summary['filters']['employee_id']
            ??
            null;


        $departmentFilter =
            $summary['filters']['department']
            ??
            null;


        $generatedAt =
            $this->escape(
                date(
                    'Y-m-d h:i A T'
                )
            );


        $payrollPeriodSection =
            $this->payrollPeriodSection(
                $summary
            );


        $employeeSections = '';


        foreach (
            $summary['employees']
            ??
            []
            as $employee
        ) {
            $dayRows = '';


            foreach (
                $employee['days']
                ??
                []
                as $day
            ) {
                $mealMinutes =
                    (int)(
                        $day['recorded_meal_minutes']
                        ??
                        0
                    )
                    +
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


                $status =
                    !empty(
                        $day['complete']
                    )
                        ? 'Complete'
                        : 'Needs Review';


                $warnings =
                    $this->warningText(
                        is_array(
                            $day['errors']
                            ??
                            null
                        )
                            ? $day['errors']
                            : []
                    );


                $dayRows .=
                    '<tr>'
                    .
                    '<td>'
                    .
                    $this->escape(
                        (string)(
                            $day['date']
                            ??
                            ''
                        )
                    )
                    .
                    '</td>'
                    .
                    '<td class="number">'
                    .
                    $this->hours(
                        $day['gross_hours']
                        ??
                        0
                    )
                    .
                    '</td>'
                    .
                    '<td class="number">'
                    .
                    $mealMinutes
                    .
                    ' min'
                    .
                    '</td>'
                    .
                    '<td class="number">'
                    .
                    (int)(
                        $day['unpaid_break_minutes']
                        ??
                        0
                    )
                    .
                    ' min'
                    .
                    '</td>'
                    .
                    '<td class="number">'
                    .
                    $this->hours(
                        $day['regular_hours']
                        ??
                        0
                    )
                    .
                    '</td>'
                    .
                    '<td class="number">'
                    .
                    $this->hours(
                        $dailyOvertimeHours
                    )
                    .
                    '</td>'
                    .
                    '<td class="number">'
                    .
                    $this->hours(
                        $doubleTimeHours
                    )
                    .
                    '</td>'
                    .
                    '<td class="number">'
                    .
                    $this->hours(
                        $totalOvertimeHours
                    )
                    .
                    '</td>'
                    .
                    '<td class="number">'
                    .
                    $this->hours(
                        $premiumHours
                    )
                    .
                    '</td>'
                    .
                    '<td class="number">'
                    .
                    $this->hours(
                        $day['total_hours']
                        ??
                        0
                    )
                    .
                    '</td>'
                    .
                    '<td>'
                    .
                    $this->escape(
                        $status
                    )
                    .
                    '</td>'
                    .
                    '<td class="warnings">'
                    .
                    $this->escape(
                        $warnings
                    )
                    .
                    '</td>'
                    .
                    '</tr>';
            }


            if ($dayRows === '') {

                $dayRows =
                    '<tr>'
                    .
                    '<td colspan="12" class="empty">'
                    .
                    'No payroll activity is available for this employee.'
                    .
                    '</td>'
                    .
                    '</tr>';
            }


            $employeeStatus =
                !empty(
                    $employee['complete']
                )
                    ? 'Complete'
                    : 'Needs Review';


            $employeeDailyOvertime =
                (float)(
                    $employee['daily_overtime_hours']
                    ??
                    0
                );


            $employeeWeeklyOvertime =
                (float)(
                    $employee['weekly_overtime_hours']
                    ??
                    0
                );


            $employeeDoubleTime =
                (float)(
                    $employee['double_time_hours']
                    ??
                    0
                );


            $employeeTotalOvertime =
                (float)(
                    $employee['overtime_hours']
                    ??
                    (
                        $employeeDailyOvertime
                        +
                        $employeeWeeklyOvertime
                    )
                );


            $employeePremium =
                (float)(
                    $employee['premium_hours']
                    ??
                    (
                        $employeeTotalOvertime
                        +
                        $employeeDoubleTime
                    )
                );


            $employeeSections .=
                '<div class="employee-section">'
                .
                '<div class="employee-header">'
                .
                '<strong>'
                .
                $this->escape(
                    (string)(
                        $employee['name']
                        ??
                        ''
                    )
                )
                .
                '</strong>'
                .
                ' &nbsp; Employee #'
                .
                $this->escape(
                    (string)(
                        $employee['employee_number']
                        ??
                        ''
                    )
                )
                .
                (
                    !empty(
                        $employee['department']
                    )
                        ? ' &nbsp; | &nbsp; '
                        .
                        $this->escape(
                            (string)$employee['department']
                        )
                        : ''
                )
                .
                ' &nbsp; | &nbsp; '
                .
                $this->escape(
                    $employeeStatus
                )
                .
                '</div>'
                .
                '<table>'
                .
                '<thead>'
                .
                '<tr>'
                .
                '<th>Date</th>'
                .
                '<th>Gross</th>'
                .
                '<th>Meal</th>'
                .
                '<th>Unpaid Break</th>'
                .
                '<th>Regular</th>'
                .
                '<th>Daily OT</th>'
                .
                '<th>Double Time</th>'
                .
                '<th>Total OT</th>'
                .
                '<th>Premium</th>'
                .
                '<th>Payable</th>'
                .
                '<th>Status</th>'
                .
                '<th>Warnings</th>'
                .
                '</tr>'
                .
                '</thead>'
                .
                '<tbody>'
                .
                $dayRows
                .
                '</tbody>'
                .
                '<tfoot>'
                .
                '<tr class="totals">'
                .
                '<td>Employee Totals</td>'
                .
                '<td class="number">'
                .
                $this->hours(
                    $employee['gross_hours']
                    ??
                    0
                )
                .
                '</td>'
                .
                '<td></td>'
                .
                '<td></td>'
                .
                '<td class="number">'
                .
                $this->hours(
                    $employee['regular_hours']
                    ??
                    0
                )
                .
                '</td>'
                .
                '<td class="number">'
                .
                $this->hours(
                    $employeeDailyOvertime
                )
                .
                '</td>'
                .
                '<td class="number">'
                .
                $this->hours(
                    $employeeDoubleTime
                )
                .
                '</td>'
                .
                '<td class="number">'
                .
                $this->hours(
                    $employeeTotalOvertime
                )
                .
                '</td>'
                .
                '<td class="number">'
                .
                $this->hours(
                    $employeePremium
                )
                .
                '</td>'
                .
                '<td class="number">'
                .
                $this->hours(
                    $employee['total_hours']
                    ??
                    0
                )
                .
                '</td>'
                .
                '<td colspan="2"></td>'
                .
                '</tr>'
                .
                '</tfoot>'
                .
                '</table>'
                .
                '<div class="weekly-note">'
                .
                'Weekly overtime for this employee: '
                .
                $this->hours(
                    $employeeWeeklyOvertime
                )
                .
                ' hours.'
                .
                '</div>'
                .
                '</div>';
        }


        if ($employeeSections === '') {

            $employeeSections =
                '<div class="empty-report">'
                .
                'No payroll activity was found for the selected filters.'
                .
                '</div>';
        }


        $reportDailyOvertime =
            (float)(
                $summary['totals']['daily_overtime_hours']
                ??
                0
            );


        $reportWeeklyOvertime =
            (float)(
                $summary['totals']['weekly_overtime_hours']
                ??
                0
            );


        $reportDoubleTime =
            (float)(
                $summary['totals']['double_time_hours']
                ??
                0
            );


        $reportTotalOvertime =
            (float)(
                $summary['totals']['overtime_hours']
                ??
                (
                    $reportDailyOvertime
                    +
                    $reportWeeklyOvertime
                )
            );


        $reportPremium =
            (float)(
                $summary['totals']['premium_hours']
                ??
                (
                    $reportTotalOvertime
                    +
                    $reportDoubleTime
                )
            );


        return
            '<!DOCTYPE html>'
            .
            '<html lang="en">'
            .
            '<head>'
            .
            '<meta charset="utf-8">'
            .
            '<style>'
            .
            '@page { margin: 22px; }'
            .
            'body {'
            .
            'font-family: "DejaVu Sans", sans-serif;'
            .
            'font-size: 7px;'
            .
            'color: #222;'
            .
            '}'
            .
            'h1 {'
            .
            'font-size: 18px;'
            .
            'margin: 0 0 4px 0;'
            .
            '}'
            .
            '.company {'
            .
            'font-size: 13px;'
            .
            'font-weight: bold;'
            .
            'margin-bottom: 2px;'
            .
            '}'
            .
            '.meta {'
            .
            'color: #555;'
            .
            'margin-bottom: 10px;'
            .
            '}'
            .
            '.summary {'
            .
            'margin-bottom: 10px;'
            .
            'padding: 8px;'
            .
            'border: 1px solid #aaa;'
            .
            'background: #f5f5f5;'
            .
            'line-height: 1.7;'
            .
            '}'
            .
            '.payroll-period {'
            .
            'margin-bottom: 14px;'
            .
            'padding: 8px;'
            .
            'border: 1px solid #5b7fa3;'
            .
            'background: #eef5fb;'
            .
            'line-height: 1.6;'
            .
            '}'
            .
            '.payroll-period.warning {'
            .
            'border-color: #b8860b;'
            .
            'background: #fff7d6;'
            .
            '}'
            .
            '.payroll-period.neutral {'
            .
            'border-color: #aaa;'
            .
            'background: #f7f7f7;'
            .
            '}'
            .
            '.payroll-period-title {'
            .
            'font-size: 9px;'
            .
            'font-weight: bold;'
            .
            'margin-bottom: 5px;'
            .
            '}'
            .
            '.payroll-period-grid {'
            .
            'width: 100%;'
            .
            'border-collapse: collapse;'
            .
            'table-layout: fixed;'
            .
            '}'
            .
            '.payroll-period-grid td {'
            .
            'border: none;'
            .
            'padding: 2px 8px 2px 0;'
            .
            'vertical-align: top;'
            .
            '}'
            .
            '.payroll-period-label {'
            .
            'font-weight: bold;'
            .
            '}'
            .
            '.employee-section {'
            .
            'margin-bottom: 16px;'
            .
            'page-break-inside: avoid;'
            .
            '}'
            .
            '.employee-header {'
            .
            'background: #e9ecef;'
            .
            'border: 1px solid #888;'
            .
            'border-bottom: none;'
            .
            'padding: 6px;'
            .
            '}'
            .
            'table {'
            .
            'width: 100%;'
            .
            'border-collapse: collapse;'
            .
            'table-layout: fixed;'
            .
            '}'
            .
            'th {'
            .
            'background: #f1f3f5;'
            .
            'border: 1px solid #888;'
            .
            'padding: 3px;'
            .
            'text-align: left;'
            .
            'font-size: 6.5px;'
            .
            '}'
            .
            'td {'
            .
            'border: 1px solid #aaa;'
            .
            'padding: 3px;'
            .
            'vertical-align: top;'
            .
            'overflow-wrap: break-word;'
            .
            '}'
            .
            '.number {'
            .
            'text-align: right;'
            .
            'white-space: nowrap;'
            .
            '}'
            .
            '.warnings {'
            .
            'font-size: 6px;'
            .
            '}'
            .
            '.totals td {'
            .
            'font-weight: bold;'
            .
            'background: #f5f5f5;'
            .
            '}'
            .
            '.weekly-note {'
            .
            'font-size: 6.5px;'
            .
            'color: #555;'
            .
            'margin-top: 4px;'
            .
            '}'
            .
            '.empty, .empty-report {'
            .
            'text-align: center;'
            .
            'color: #777;'
            .
            'padding: 18px;'
            .
            '}'
            .
            '.footer {'
            .
            'margin-top: 12px;'
            .
            'font-size: 7px;'
            .
            'color: #666;'
            .
            '}'
            .
            '</style>'
            .
            '</head>'
            .
            '<body>'
            .
            '<div class="company">'
            .
            $companyName
            .
            '</div>'
            .
            '<h1>Payroll Workspace Report</h1>'
            .
            '<div class="meta">'
            .
            'Report period: '
            .
            $startDate
            .
            ' through '
            .
            $endDate
            .
            ' &nbsp; | &nbsp; Timezone: '
            .
            $timezone
            .
            '</div>'
            .
            '<div class="summary">'
            .
            '<strong>Employees:</strong> '
            .
            (int)(
                $summary['totals']['employee_count']
                ??
                0
            )
            .
            ' &nbsp; | &nbsp; '
            .
            '<strong>Gross:</strong> '
            .
            $this->hours(
                $summary['totals']['gross_hours']
                ??
                0
            )
            .
            ' &nbsp; | &nbsp; '
            .
            '<strong>Regular:</strong> '
            .
            $this->hours(
                $summary['totals']['regular_hours']
                ??
                0
            )
            .
            ' &nbsp; | &nbsp; '
            .
            '<strong>Daily OT:</strong> '
            .
            $this->hours(
                $reportDailyOvertime
            )
            .
            ' &nbsp; | &nbsp; '
            .
            '<strong>Weekly OT:</strong> '
            .
            $this->hours(
                $reportWeeklyOvertime
            )
            .
            ' &nbsp; | &nbsp; '
            .
            '<strong>Double Time:</strong> '
            .
            $this->hours(
                $reportDoubleTime
            )
            .
            ' &nbsp; | &nbsp; '
            .
            '<strong>Total OT:</strong> '
            .
            $this->hours(
                $reportTotalOvertime
            )
            .
            ' &nbsp; | &nbsp; '
            .
            '<strong>Premium:</strong> '
            .
            $this->hours(
                $reportPremium
            )
            .
            ' &nbsp; | &nbsp; '
            .
            '<strong>Payable:</strong> '
            .
            $this->hours(
                $summary['totals']['total_hours']
                ??
                0
            )
            .
            ' &nbsp; | &nbsp; '
            .
            '<strong>Issues:</strong> '
            .
            (int)(
                $summary['issue_count']
                ??
                0
            )
            .
            '<br>'
            .
            '<strong>Employee filter:</strong> '
            .
            $this->escape(
                $employeeFilter === null
                    ? 'All'
                    : (string)$employeeFilter
            )
            .
            ' &nbsp; | &nbsp; '
            .
            '<strong>Department filter:</strong> '
            .
            $this->escape(
                $departmentFilter === null
                    ? 'All'
                    : (string)$departmentFilter
            )
            .
            '</div>'
            .
            $payrollPeriodSection
            .
            $employeeSections
            .
            '<div class="footer">'
            .
            'Total overtime includes daily and weekly overtime. '
            .
            'Premium hours include total overtime plus double-time hours. '
            .
            'Generated by IQwurksPunch on '
            .
            $generatedAt
            .
            '. Review all records marked Needs Review before processing payroll.'
            .
            '</div>'
            .
            '</body>'
            .
            '</html>';
    }


    /**
     * @param array<string,mixed> $summary
     */
    private function payrollPeriodSection(
        array $summary
    ): string
    {
        $association =
            (string)(
                $summary['payroll_period_association']
                ??
                'none'
            );


        $message =
            (string)(
                $summary[
                    'payroll_period_association_message'
                ]
                ??
                'This report is not associated with a payroll period.'
            );


        $payrollPeriod =
            $summary['payroll_period']
            ??
            null;


        if (
            $association === 'exact'
            &&
            is_array(
                $payrollPeriod
            )
        ) {
            $status =
                (string)(
                    $payrollPeriod['status']
                    ??
                    ''
                );


            $approvalBlocked =
                !empty(
                    $payrollPeriod['approval_blocked']
                )
                    ? 'Yes'
                    : 'No';


            return
                '<div class="payroll-period">'
                .
                '<div class="payroll-period-title">'
                .
                'Exact Payroll-Period Association'
                .
                '</div>'
                .
                '<table class="payroll-period-grid">'
                .
                '<tr>'
                .
                '<td>'
                .
                '<span class="payroll-period-label">Period:</span> '
                .
                $this->escape(
                    (string)(
                        $payrollPeriod['period_name']
                        ??
                        ''
                    )
                )
                .
                '</td>'
                .
                '<td>'
                .
                '<span class="payroll-period-label">Period ID:</span> '
                .
                (int)(
                    $payrollPeriod['id']
                    ??
                    0
                )
                .
                '</td>'
                .
                '<td>'
                .
                '<span class="payroll-period-label">Workflow status:</span> '
                .
                $this->escape(
                    $this->workflowStatusLabel(
                        $status
                    )
                )
                .
                '</td>'
                .
                '</tr>'
                .
                '<tr>'
                .
                '<td>'
                .
                '<span class="payroll-period-label">Period range:</span> '
                .
                $this->escape(
                    (string)(
                        $payrollPeriod['start_date']
                        ??
                        ''
                    )
                )
                .
                ' through '
                .
                $this->escape(
                    (string)(
                        $payrollPeriod['end_date']
                        ??
                        ''
                    )
                )
                .
                '</td>'
                .
                '<td>'
                .
                '<span class="payroll-period-label">Open exceptions:</span> '
                .
                (int)(
                    $payrollPeriod['open_exception_count']
                    ??
                    0
                )
                .
                '</td>'
                .
                '<td>'
                .
                '<span class="payroll-period-label">Total exceptions:</span> '
                .
                (int)(
                    $payrollPeriod['total_exception_count']
                    ??
                    0
                )
                .
                '</td>'
                .
                '</tr>'
                .
                '<tr>'
                .
                '<td>'
                .
                '<span class="payroll-period-label">Approval blocked:</span> '
                .
                $approvalBlocked
                .
                '</td>'
                .
                '<td>'
                .
                '<span class="payroll-period-label">Created:</span> '
                .
                $this->workflowRecord(
                    $payrollPeriod['created_at']
                    ??
                    null,
                    $payrollPeriod['created_by_username']
                    ??
                    null
                )
                .
                '</td>'
                .
                '<td>'
                .
                '<span class="payroll-period-label">Review started:</span> '
                .
                $this->workflowRecord(
                    $payrollPeriod['review_started_at']
                    ??
                    null,
                    $payrollPeriod['reviewed_by_username']
                    ??
                    null
                )
                .
                '</td>'
                .
                '</tr>'
                .
                '<tr>'
                .
                '<td>'
                .
                '<span class="payroll-period-label">Approved:</span> '
                .
                $this->workflowRecord(
                    $payrollPeriod['approved_at']
                    ??
                    null,
                    $payrollPeriod['approved_by_username']
                    ??
                    null
                )
                .
                '</td>'
                .
                '<td>'
                .
                '<span class="payroll-period-label">Locked:</span> '
                .
                $this->workflowRecord(
                    $payrollPeriod['locked_at']
                    ??
                    null,
                    $payrollPeriod['locked_by_username']
                    ??
                    null
                )
                .
                '</td>'
                .
                '<td>'
                .
                '<span class="payroll-period-label">Association:</span> '
                .
                $this->escape(
                    $message
                )
                .
                '</td>'
                .
                '</tr>'
                .
                '</table>'
                .
                (
                    in_array(
                        $status,
                        [
                            'approved',
                            'locked'
                        ],
                        true
                    )
                        ? '<div><strong>Punch protection:</strong> '
                        .
                        'Punch corrections in this period require reopening the payroll period.'
                        .
                        '</div>'
                        : ''
                )
                .
                '</div>';
        }


        if ($association === 'partial_overlap') {

            return
                '<div class="payroll-period warning">'
                .
                '<div class="payroll-period-title">'
                .
                'Partial Payroll-Period Overlap'
                .
                '</div>'
                .
                $this->escape(
                    $message
                )
                .
                '<br>'
                .
                'This export does not carry payroll approval, lock, or exception-resolution status because the report range is not an exact match.'
                .
                '</div>';
        }


        return
            '<div class="payroll-period neutral">'
            .
            '<div class="payroll-period-title">'
            .
            'No Payroll-Period Association'
            .
            '</div>'
            .
            $this->escape(
                $message
            )
            .
            '<br>'
            .
            'This export does not carry payroll review, approval, lock, or exception-resolution status.'
            .
            '</div>';
    }


    private function workflowRecord(
        mixed $timestamp,
        mixed $username
    ): string
    {
        $timestamp =
            trim(
                (string)(
                    $timestamp
                    ??
                    ''
                )
            );


        $username =
            trim(
                (string)(
                    $username
                    ??
                    ''
                )
            );


        if (
            $timestamp === ''
            &&
            $username === ''
        ) {

            return '—';
        }


        $record =
            $timestamp === ''
                ? 'Time unavailable'
                : $this->escape(
                    $timestamp
                );


        if ($username !== '') {

            $record .=
                ' by '
                .
                $this->escape(
                    $username
                );
        }


        return $record;
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


    /**
     * @param array<int,mixed> $warnings
     */
    private function warningText(
        array $warnings
    ): string
    {
        return implode(
            ' | ',
            array_map(
                static fn (
                    mixed $warning
                ): string =>
                    (string)$warning,
                $warnings
            )
        );
    }


    private function escape(
        string $value
    ): string
    {
        return htmlspecialchars(
            $value,
            ENT_QUOTES
            |
            ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}
