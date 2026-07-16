<?php
declare(strict_types=1);

namespace App\Exports\Pdf;

use Dompdf\Dompdf;
use Dompdf\Options;
use RuntimeException;

final class DailyPayrollPdfExporter
{
    /**
     * @param array<int,array<string,mixed>> $summary
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
                'The daily payroll PDF could not be generated.'
            );
        }


        return $output;
    }


    /**
     * @param array<int,array<string,mixed>> $summary
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


        $date =
            $this->escape(
                (string)(
                    $summary[0]['date']
                    ??
                    date(
                        'Y-m-d'
                    )
                )
            );


        $timezone =
            $this->escape(
                (string)(
                    $company['timezone']
                    ??
                    date_default_timezone_get()
                )
            );


        $generatedAt =
            $this->escape(
                date(
                    'Y-m-d h:i A T'
                )
            );


        $rows = '';

        $grossTotal = 0.0;

        $mealTotal = 0;

        $unpaidBreakTotal = 0;

        $regularTotal = 0.0;

        $overtimeTotal = 0.0;

        $payableTotal = 0.0;


        foreach ($summary as $employee) {

            $grossHours =
                (float)(
                    $employee['gross_hours']
                    ??
                    0
                );


            $mealMinutes =
                (int)(
                    $employee['recorded_meal_minutes']
                    ??
                    0
                )
                +
                (int)(
                    $employee['automatic_meal_deduction_minutes']
                    ??
                    0
                );


            $unpaidBreakMinutes =
                (int)(
                    $employee['unpaid_break_minutes']
                    ??
                    0
                );


            $regularHours =
                (float)(
                    $employee['regular_hours']
                    ??
                    0
                );


            $overtimeHours =
                (float)(
                    $employee['overtime_hours']
                    ??
                    0
                );


            $payableHours =
                (float)(
                    $employee['total_hours']
                    ??
                    0
                );


            $grossTotal +=
                $grossHours;


            $mealTotal +=
                $mealMinutes;


            $unpaidBreakTotal +=
                $unpaidBreakMinutes;


            $regularTotal +=
                $regularHours;


            $overtimeTotal +=
                $overtimeHours;


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
                    $employee['errors']
                    ??
                    []
                );


            $rows .=
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
                $mealMinutes
                .
                ' min'
                .
                '</td>'
                .
                '<td class="number">'
                .
                $unpaidBreakMinutes
                .
                ' min'
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
                    $overtimeHours
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


        if ($rows === '') {

            $rows =
                '<tr>'
                .
                '<td colspan="11" class="empty">'
                .
                'No daily payroll data is available.'
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
            '@page { margin: 28px; }'
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
            '.warnings {'
            .
            'font-size: 8px;'
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
            '<h1>Daily Payroll Summary</h1>'
            .
            '<div class="meta">'
            .
            'Payroll date: '
            .
            $date
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
            '<th>Meal</th>'
            .
            '<th>Unpaid Break</th>'
            .
            '<th>Regular</th>'
            .
            '<th>Overtime</th>'
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
            $rows
            .
            '</tbody>'
            .
            '<tfoot>'
            .
            '<tr class="totals">'
            .
            '<td colspan="3">Daily Totals</td>'
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
            $mealTotal
            .
            ' min'
            .
            '</td>'
            .
            '<td class="number">'
            .
            $unpaidBreakTotal
            .
            ' min'
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
                $overtimeTotal
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
