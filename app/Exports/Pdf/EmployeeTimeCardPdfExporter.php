<?php
declare(strict_types=1);

namespace App\Exports\Pdf;

use Dompdf\Dompdf;
use Dompdf\Options;
use InvalidArgumentException;
use RuntimeException;

final class EmployeeTimeCardPdfExporter
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
        $employees =
            $summary['employees']
            ??
            [];


        if (
            !is_array(
                $employees
            )
            ||
            count(
                $employees
            )
            !==
            1
        ) {

            throw new InvalidArgumentException(
                'An employee time card requires exactly one selected employee with payroll activity.'
            );
        }


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
                $company,
                $employees[0]
            )
        );


        $dompdf->render();


        $output =
            $dompdf->output();


        if ($output === '') {

            throw new RuntimeException(
                'The employee time-card PDF could not be generated.'
            );
        }


        return $output;
    }


    /**
     * @param array<string,mixed> $summary
     * @param array<string,mixed> $company
     * @param array<string,mixed> $employee
     */
    private function html(
        array $summary,
        array $company,
        array $employee
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


        $companyAddress =
            $this->companyAddress(
                $company
            );


        $employeeName =
            $this->escape(
                (string)(
                    $employee['name']
                    ??
                    ''
                )
            );


        $employeeNumber =
            $this->escape(
                (string)(
                    $employee['employee_number']
                    ??
                    ''
                )
            );


        $department =
            $this->escape(
                (string)(
                    $employee['department']
                    ??
                    ''
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


        $employeeStatus =
            !empty(
                $employee['complete']
            )
                ? 'Complete'
                : 'Needs Review';


        $payrollPeriodSection =
            $this->payrollPeriodSection(
                $summary
            );


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


            $dailyPremiumHours =
                (float)(
                    $day['premium_hours']
                    ??
                    (
                        $dailyOvertimeHours
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
                    $dailyPremiumHours
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
                '</tr>';


            if ($warnings !== '') {

                $dayRows .=
                    '<tr class="warning-row">'
                    .
                    '<td colspan="10">'
                    .
                    '<strong>Warning: </strong>'
                    .
                    $this->escape(
                        $warnings
                    )
                    .
                    '</td>'
                    .
                    '</tr>';
            }
        }


        if ($dayRows === '') {

            $dayRows =
                '<tr>'
                .
                '<td colspan="10" class="empty">'
                .
                'No payroll activity was found for this employee.'
                .
                '</td>'
                .
                '</tr>';
        }


        $weekRows = '';


        foreach (
            $employee['weeks']
            ??
            []
            as $week
        ) {
            $dailyOvertimeHours =
                (float)(
                    $week['daily_overtime_hours']
                    ??
                    0
                );


            $weeklyOvertimeHours =
                (float)(
                    $week['weekly_overtime_hours']
                    ??
                    0
                );


            $doubleTimeHours =
                (float)(
                    $week['double_time_hours']
                    ??
                    0
                );


            $totalOvertimeHours =
                (float)(
                    $week['overtime_hours']
                    ??
                    (
                        $dailyOvertimeHours
                        +
                        $weeklyOvertimeHours
                    )
                );


            $premiumHours =
                (float)(
                    $week['premium_hours']
                    ??
                    (
                        $totalOvertimeHours
                        +
                        $doubleTimeHours
                    )
                );


            $weekRows .=
                '<tr>'
                .
                '<td>'
                .
                $this->escape(
                    (string)(
                        $week['week_start']
                        ??
                        ''
                    )
                )
                .
                ' through '
                .
                $this->escape(
                    (string)(
                        $week['week_end']
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
                    $week['gross_hours']
                    ??
                    0
                )
                .
                '</td>'
                .
                '<td class="number">'
                .
                $this->hours(
                    $week['regular_hours']
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
                    $weeklyOvertimeHours
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
                    $week['total_hours']
                    ??
                    0
                )
                .
                '</td>'
                .
                '</tr>';
        }


        if ($weekRows === '') {

            $weekRows =
                '<tr>'
                .
                '<td colspan="9" class="empty">'
                .
                'No payroll-week totals are available.'
                .
                '</td>'
                .
                '</tr>';
        }


        $generatedAt =
            $this->escape(
                date(
                    'Y-m-d h:i A T'
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
            '@page { margin: 26px; }'
            .
            'body {'
            .
            'font-family: "DejaVu Sans", sans-serif;'
            .
            'font-size: 8px;'
            .
            'color: #222;'
            .
            '}'
            .
            'h1 {'
            .
            'font-size: 19px;'
            .
            'margin: 0 0 5px 0;'
            .
            '}'
            .
            'h2 {'
            .
            'font-size: 12px;'
            .
            'margin: 16px 0 6px 0;'
            .
            '}'
            .
            '.company {'
            .
            'font-size: 14px;'
            .
            'font-weight: bold;'
            .
            '}'
            .
            '.address {'
            .
            'color: #555;'
            .
            'margin-bottom: 12px;'
            .
            '}'
            .
            '.period {'
            .
            'color: #555;'
            .
            'margin-bottom: 12px;'
            .
            '}'
            .
            '.employee-box {'
            .
            'border: 1px solid #888;'
            .
            'padding: 7px;'
            .
            'margin-bottom: 10px;'
            .
            'background: #f7f7f7;'
            .
            '}'
            .
            '.payroll-period {'
            .
            'border: 1px solid #5b7fa3;'
            .
            'background: #eef5fb;'
            .
            'padding: 7px;'
            .
            'margin-bottom: 10px;'
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
            'margin-bottom: 4px;'
            .
            '}'
            .
            '.totals-table {'
            .
            'margin-bottom: 12px;'
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
            'background: #e9ecef;'
            .
            'border: 1px solid #888;'
            .
            'padding: 4px;'
            .
            'text-align: left;'
            .
            'font-size: 7px;'
            .
            '}'
            .
            'td {'
            .
            'border: 1px solid #aaa;'
            .
            'padding: 4px;'
            .
            'vertical-align: top;'
            .
            'overflow-wrap: break-word;'
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
            '.payroll-period .payroll-period-grid td {'
            .
            'border: none;'
            .
            'padding: 2px 10px 2px 0;'
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
            '.number {'
            .
            'text-align: right;'
            .
            'white-space: nowrap;'
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
            '.warning-row td {'
            .
            'background: #fff3cd;'
            .
            'font-size: 7px;'
            .
            '}'
            .
            '.empty {'
            .
            'text-align: center;'
            .
            'color: #777;'
            .
            'padding: 16px;'
            .
            '}'
            .
            '.note {'
            .
            'margin-top: 6px;'
            .
            'font-size: 7px;'
            .
            'color: #555;'
            .
            '}'
            .
            '.signatures {'
            .
            'margin-top: 32px;'
            .
            'width: 100%;'
            .
            '}'
            .
            '.signature {'
            .
            'display: inline-block;'
            .
            'width: 46%;'
            .
            'margin-right: 3%;'
            .
            'vertical-align: top;'
            .
            '}'
            .
            '.signature-line {'
            .
            'border-top: 1px solid #333;'
            .
            'padding-top: 4px;'
            .
            'margin-top: 26px;'
            .
            '}'
            .
            '.footer {'
            .
            'margin-top: 16px;'
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
            '<div class="address">'
            .
            $companyAddress
            .
            '</div>'
            .
            '<h1>Employee Time Card</h1>'
            .
            '<div class="period">'
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
            $payrollPeriodSection
            .
            '<div class="employee-box">'
            .
            '<strong>Employee:</strong> '
            .
            $employeeName
            .
            ' &nbsp; | &nbsp; '
            .
            '<strong>Employee number:</strong> '
            .
            $employeeNumber
            .
            ' &nbsp; | &nbsp; '
            .
            '<strong>Department:</strong> '
            .
            (
                $department === ''
                    ? '—'
                    : $department
            )
            .
            ' &nbsp; | &nbsp; '
            .
            '<strong>Status:</strong> '
            .
            $this->escape(
                $employeeStatus
            )
            .
            '</div>'
            .
            '<h2>Employee Payroll Totals</h2>'
            .
            '<table class="totals-table">'
            .
            '<thead>'
            .
            '<tr>'
            .
            '<th>Gross</th>'
            .
            '<th>Regular</th>'
            .
            '<th>Daily OT</th>'
            .
            '<th>Weekly OT</th>'
            .
            '<th>Double Time</th>'
            .
            '<th>Total OT</th>'
            .
            '<th>Premium</th>'
            .
            '<th>Payable</th>'
            .
            '</tr>'
            .
            '</thead>'
            .
            '<tbody>'
            .
            '<tr class="totals">'
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
                $employeeWeeklyOvertime
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
            '</tr>'
            .
            '</tbody>'
            .
            '</table>'
            .
            '<h2>Daily Time Summary</h2>'
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
            '<th>Meal Min.</th>'
            .
            '<th>Unpaid Break Min.</th>'
            .
            '<th>Regular</th>'
            .
            '<th>Daily OT</th>'
            .
            '<th>Double Time</th>'
            .
            '<th>Premium</th>'
            .
            '<th>Payable</th>'
            .
            '<th>Status</th>'
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
            '</table>'
            .
            '<div class="note">'
            .
            'Daily premium hours include daily overtime plus double-time hours. '
            .
            'Weekly overtime is assigned after the complete payroll week is calculated.'
            .
            '</div>'
            .
            '<h2>Payroll Week Summary</h2>'
            .
            '<table>'
            .
            '<thead>'
            .
            '<tr>'
            .
            '<th>Payroll Week</th>'
            .
            '<th>Gross</th>'
            .
            '<th>Regular</th>'
            .
            '<th>Daily OT</th>'
            .
            '<th>Weekly OT</th>'
            .
            '<th>Double Time</th>'
            .
            '<th>Total OT</th>'
            .
            '<th>Premium</th>'
            .
            '<th>Payable</th>'
            .
            '</tr>'
            .
            '</thead>'
            .
            '<tbody>'
            .
            $weekRows
            .
            '</tbody>'
            .
            '</table>'
            .
            '<div class="signatures">'
            .
            '<div class="signature">'
            .
            '<div class="signature-line">'
            .
            'Employee Signature / Date'
            .
            '</div>'
            .
            '</div>'
            .
            '<div class="signature">'
            .
            '<div class="signature-line">'
            .
            'Supervisor Approval / Date'
            .
            '</div>'
            .
            '</div>'
            .
            '</div>'
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
            '. Review all records marked Needs Review before approval.'
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
                'This time card is not associated with a payroll period.'
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
                '<td>'
                .
                '<span class="payroll-period-label">Approval blocked:</span> '
                .
                $approvalBlocked
                .
                '</td>'
                .
                '</tr>'
                .
                '<tr>'
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
                'This time card does not carry payroll approval, lock, or exception-resolution status because its report range is not an exact match.'
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
            'This time card does not carry payroll review, approval, lock, or exception-resolution status.'
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


    /**
     * @param array<string,mixed> $company
     */
    private function companyAddress(
        array $company
    ): string
    {
        $lines = [];


        $street =
            trim(
                (string)(
                    $company['address']
                    ??
                    ''
                )
            );


        if ($street !== '') {

            $lines[] =
                $this->escape(
                    $street
                );
        }


        $cityStateZip =
            trim(
                implode(
                    ' ',
                    array_filter(
                        [
                            trim(
                                (string)(
                                    $company['city']
                                    ??
                                    ''
                                )
                            ),

                            trim(
                                (string)(
                                    $company['state']
                                    ??
                                    ''
                                )
                            ),

                            trim(
                                (string)(
                                    $company['zip']
                                    ??
                                    ''
                                )
                            )
                        ],
                        static fn (
                            string $value
                        ): bool =>
                            $value !== ''
                    )
                )
            );


        if ($cityStateZip !== '') {

            $lines[] =
                $this->escape(
                    $cityStateZip
                );
        }


        return implode(
            '<br>',
            $lines
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
