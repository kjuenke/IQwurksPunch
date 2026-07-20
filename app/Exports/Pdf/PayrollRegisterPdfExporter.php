<?php
declare(strict_types=1);

namespace App\Exports\Pdf;

use Dompdf\Dompdf;
use Dompdf\Options;
use RuntimeException;

final class PayrollRegisterPdfExporter
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
                'The payroll register PDF could not be generated.'
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


        $weekStart =
            $this->escape(
                (string)(
                    $summary['week_start']
                    ??
                    ''
                )
            );


        $weekEnd =
            $this->escape(
                (string)(
                    $summary['week_end']
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


        $generatedAt =
            $this->escape(
                date(
                    'Y-m-d h:i A T'
                )
            );


        $employeeRows = '';

        $grossTotal = 0.0;

        $regularTotal = 0.0;

        $dailyOvertimeTotal = 0.0;

        $weeklyOvertimeTotal = 0.0;

        $doubleTimeTotal = 0.0;

        $overtimeTotal = 0.0;

        $premiumTotal = 0.0;

        $payableTotal = 0.0;


        foreach (
            $summary['employees']
            ??
            []
            as $employee
        ) {
            $grossHours =
                (float)(
                    $employee['gross_hours']
                    ??
                    0
                );


            $regularHours =
                (float)(
                    $employee['regular_hours']
                    ??
                    0
                );


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


            $overtimeHours =
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
                        $overtimeHours
                        +
                        $doubleTimeHours
                    )
                );


            $payableHours =
                (float)(
                    $employee['total_hours']
                    ??
                    0
                );


            $grossTotal +=
                $grossHours;


            $regularTotal +=
                $regularHours;


            $dailyOvertimeTotal +=
                $dailyOvertimeHours;


            $weeklyOvertimeTotal +=
                $weeklyOvertimeHours;


            $doubleTimeTotal +=
                $doubleTimeHours;


            $overtimeTotal +=
                $overtimeHours;


            $premiumTotal +=
                $premiumHours;


            $payableTotal +=
                $payableHours;


            $status =
                !empty(
                    $employee['complete']
                )
                    ? 'Complete'
                    : 'Needs Review';


            $warnings =
                $this->warningText(
                    is_array(
                        $employee['errors']
                        ??
                        null
                    )
                        ? $employee['errors']
                        : []
                );


            $employeeRows .=
                '<tr>'
                .
                '<td>'
                .
                $this->escape(
                    (string)(
                        $employee['employee_number']
                        ??
                        ''
                    )
                )
                .
                '</td>'
                .
                '<td>'
                .
                $this->escape(
                    (string)(
                        $employee['name']
                        ??
                        ''
                    )
                )
                .
                '</td>'
                .
                '<td>'
                .
                $this->escape(
                    (string)(
                        $employee['department']
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
                    $grossHours
                )
                .
                '</td>'
                .
                '<td class="number">'
                .
                $this->hours(
                    $regularHours
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
                    $overtimeHours
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
                    $payableHours
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


        if ($employeeRows === '') {

            $employeeRows =
                '<tr>'
                .
                '<td colspan="13" class="empty">'
                .
                'No weekly payroll data is available.'
                .
                '</td>'
                .
                '</tr>';
        }


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
            'margin-bottom: 16px;'
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
            'font-size: 6.5px;'
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
            'font-size: 6.5px;'
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
            '.empty {'
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
            '<h1>Weekly Payroll Register</h1>'
            .
            '<div class="meta">'
            .
            'Pay period: '
            .
            $weekStart
            .
            ' through '
            .
            $weekEnd
            .
            ' &nbsp; | &nbsp; Timezone: '
            .
            $timezone
            .
            '</div>'
            .
            '<table>'
            .
            '<thead>'
            .
            '<tr>'
            .
            '<th>Employee #</th>'
            .
            '<th>Employee</th>'
            .
            '<th>Department</th>'
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
            $employeeRows
            .
            '</tbody>'
            .
            '<tfoot>'
            .
            '<tr class="totals">'
            .
            '<td colspan="3">Register Totals</td>'
            .
            '<td class="number">'
            .
            $this->hours(
                $grossTotal
            )
            .
            '</td>'
            .
            '<td class="number">'
            .
            $this->hours(
                $regularTotal
            )
            .
            '</td>'
            .
            '<td class="number">'
            .
            $this->hours(
                $dailyOvertimeTotal
            )
            .
            '</td>'
            .
            '<td class="number">'
            .
            $this->hours(
                $weeklyOvertimeTotal
            )
            .
            '</td>'
            .
            '<td class="number">'
            .
            $this->hours(
                $doubleTimeTotal
            )
            .
            '</td>'
            .
            '<td class="number">'
            .
            $this->hours(
                $overtimeTotal
            )
            .
            '</td>'
            .
            '<td class="number">'
            .
            $this->hours(
                $premiumTotal
            )
            .
            '</td>'
            .
            '<td class="number">'
            .
            $this->hours(
                $payableTotal
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
