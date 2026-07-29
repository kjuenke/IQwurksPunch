<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Container;
use App\Exports\DailyPayrollCsvExporter;
use App\Exports\Pdf\DailyPayrollPdfExporter;
use App\Exports\Pdf\PayrollRegisterPdfExporter;
use App\Exports\WeeklyPayrollCsvExporter;
use InvalidArgumentException;
use RuntimeException;

final class PayrollEmailAttachmentService
{
    private DailyPayrollCsvExporter $dailyCsv;

    private WeeklyPayrollCsvExporter $weeklyCsv;

    private DailyPayrollPdfExporter $dailyPdf;

    private PayrollRegisterPdfExporter $weeklyPdf;


    public function __construct(
        ?DailyPayrollCsvExporter $dailyCsv = null,
        ?WeeklyPayrollCsvExporter $weeklyCsv = null,
        ?DailyPayrollPdfExporter $dailyPdf = null,
        ?PayrollRegisterPdfExporter $weeklyPdf = null
    )
    {
        $this->dailyCsv =
            $dailyCsv
            ??
            Container::dailyPayrollCsvExporter();


        $this->weeklyCsv =
            $weeklyCsv
            ??
            Container::weeklyPayrollCsvExporter();


        $this->dailyPdf =
            $dailyPdf
            ??
            Container::dailyPayrollPdfExporter();


        $this->weeklyPdf =
            $weeklyPdf
            ??
            Container::payrollRegisterPdfExporter();
    }


    /**
     * @param array<int,array<string,mixed>> $summary
     *
     * @return array{
     *     filename:string,
     *     content_type:string,
     *     contents:string
     * }
     */
    public function dailyCsv(
        array $summary,
        string $reportDate
    ): array
    {
        $reportDate =
            $this->validDate(
                $reportDate,
                'daily payroll report date'
            );


        $contents =
            $this->dailyCsv
                ->export(
                    $summary
                );


        return
            $this->attachment(
                'iqwurkspunch-daily-payroll-'
                .
                $reportDate
                .
                '.csv',
                'text/csv',
                $contents
            );
    }


    /**
     * @param array<int,array<string,mixed>> $summary
     * @param array<string,mixed> $company
     *
     * @return array{
     *     filename:string,
     *     content_type:string,
     *     contents:string
     * }
     */
    public function dailyPdf(
        array $summary,
        array $company,
        string $reportDate
    ): array
    {
        $reportDate =
            $this->validDate(
                $reportDate,
                'daily payroll report date'
            );


        $contents =
            $this->dailyPdf
                ->export(
                    $summary,
                    $company
                );


        return
            $this->attachment(
                'iqwurkspunch-daily-payroll-'
                .
                $reportDate
                .
                '.pdf',
                'application/pdf',
                $contents
            );
    }


    /**
     * @param array<string,mixed> $summary
     *
     * @return array{
     *     filename:string,
     *     content_type:string,
     *     contents:string
     * }
     */
    public function weeklyCsv(
        array $summary,
        string $weekStart,
        string $weekEnd
    ): array
    {
        [
            $weekStart,
            $weekEnd
        ] =
            $this->validWeeklyRange(
                $weekStart,
                $weekEnd
            );


        $contents =
            $this->weeklyCsv
                ->export(
                    $summary
                );


        return
            $this->attachment(
                'iqwurkspunch-weekly-payroll-'
                .
                $weekStart
                .
                '-to-'
                .
                $weekEnd
                .
                '.csv',
                'text/csv',
                $contents
            );
    }


    /**
     * @param array<string,mixed> $summary
     * @param array<string,mixed> $company
     *
     * @return array{
     *     filename:string,
     *     content_type:string,
     *     contents:string
     * }
     */
    public function weeklyPdf(
        array $summary,
        array $company,
        string $weekStart,
        string $weekEnd
    ): array
    {
        [
            $weekStart,
            $weekEnd
        ] =
            $this->validWeeklyRange(
                $weekStart,
                $weekEnd
            );


        $contents =
            $this->weeklyPdf
                ->export(
                    $summary,
                    $company
                );


        return
            $this->attachment(
                'iqwurkspunch-weekly-payroll-'
                .
                $weekStart
                .
                '-to-'
                .
                $weekEnd
                .
                '.pdf',
                'application/pdf',
                $contents
            );
    }


    /**
     * @return array{
     *     filename:string,
     *     content_type:string,
     *     contents:string
     * }
     */
    private function attachment(
        string $filename,
        string $contentType,
        string $contents
    ): array
    {
        if ($contents === '') {

            throw new RuntimeException(
                'The payroll attachment exporter returned empty content.'
            );
        }


        return [
            'filename' =>
                $filename,

            'content_type' =>
                $contentType,

            'contents' =>
                $contents
        ];
    }


    /**
     * @return array{0:string,1:string}
     */
    private function validWeeklyRange(
        string $weekStart,
        string $weekEnd
    ): array
    {
        $weekStart =
            $this->validDate(
                $weekStart,
                'weekly payroll start date'
            );


        $weekEnd =
            $this->validDate(
                $weekEnd,
                'weekly payroll end date'
            );


        if ($weekEnd < $weekStart) {

            throw new InvalidArgumentException(
                'The weekly payroll end date cannot be earlier than the start date.'
            );
        }


        return [
            $weekStart,
            $weekEnd
        ];
    }


    private function validDate(
        string $date,
        string $label
    ): string
    {
        $date =
            trim(
                $date
            );


        $parsed =
            \DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $date
            );


        $errors =
            \DateTimeImmutable::getLastErrors();


        if (
            !$parsed
            ||
            (
                is_array(
                    $errors
                )
                &&
                (
                    $errors['warning_count'] > 0
                    ||
                    $errors['error_count'] > 0
                )
            )
            ||
            $parsed->format(
                'Y-m-d'
            )
            !==
            $date
        ) {
            throw new InvalidArgumentException(
                'The '
                .
                $label
                .
                ' must be a valid date in YYYY-MM-DD format.'
            );
        }


        return $date;
    }
}
