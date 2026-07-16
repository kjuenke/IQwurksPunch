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
            'portrait'
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
                    $day['overtime_hours']
                    ??
                    0
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
                    '<td colspan="8">'
                    .
                    '<strong>'
                    .
                    'Warning: '
                    .
                    '</strong>'
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
                '<td colspan="8" class="empty">'
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
                    $week['daily_overtime_hours']
                    ??
                    0
                )
                .
                '</td>'
                .
                '<td class="number">'
                .
                $this->hours(
                    $week['weekly_overtime_hours']
                    ??
                    0
                )
                .
                '</td>'
                .
                '<td class="number">'
                .
                $this->hours(
                    $week['overtime_hours']
                    ??
                    0
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
                '<td colspan="7" class="empty">'
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
            '@page { margin: 32px; }'
            .
            'body {'
            .
            'font-family: "DejaVu Sans", sans-serif;'
            .
            'font-size: 9px;'
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
            'margin: 18px 0 6px 0;'
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
            'margin-bottom: 14px;'
            .
            '}'
            .
            '.period {'
            .
            'color: #555;'
            .
            'margin-bottom: 14px;'
            .
            '}'
            .
            '.employee-box {'
            .
            'border: 1px solid #888;'
            .
            'padding: 8px;'
            .
            'margin-bottom: 14px;'
            .
            'background: #f7f7f7;'
            .
            '}'
            .
            'table {'
            .
            'width: 100%;'
            .
            'border-collapse: collapse;'
            .
            '}'
            .
            'th {'
            .
            'background: #e9ecef;'
            .
            'border: 1px solid #888;'
            .
            'padding: 5px;'
            .
            'text-align: left;'
            .
            '}'
            .
            'td {'
            .
            'border: 1px solid #aaa;'
            .
            'padding: 5px;'
            .
            'vertical-align: top;'
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
            'font-size: 8px;'
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
            '.signatures {'
            .
            'margin-top: 36px;'
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
            'margin-top: 28px;'
            .
            '}'
            .
            '.footer {'
            .
            'margin-top: 18px;'
            .
            'font-size: 8px;'
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
            '<div class="employee-box">'
            .
            '<strong>Employee:</strong> '
            .
            $employeeName
            .
            '<br>'
            .
            '<strong>Employee number:</strong> '
            .
            $employeeNumber
            .
            '<br>'
            .
            '<strong>Department:</strong> '
            .
            (
                $department === ''
                    ? '—'
                    : $department
            )
            .
            '</div>'
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
                $employee['overtime_hours']
                ??
                0
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
            '<td>'
            .
            (
                !empty(
                    $employee['complete']
                )
                    ? 'Complete'
                    : 'Needs Review'
            )
            .
            '</td>'
            .
            '</tr>'
            .
            '</tfoot>'
            .
            '</table>'
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
            '<th>Total OT</th>'
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
