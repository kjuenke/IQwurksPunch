<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Container;
use App\Exports\DailyPayrollCsvExporter;
use App\Exports\WeeklyPayrollCsvExporter;
use InvalidArgumentException;
use RuntimeException;

final class PayrollEmailAttachmentService
{
    private DailyPayrollCsvExporter $dailyCsv;

    private WeeklyPayrollCsvExporter $weeklyCsv;


    public function __construct(
        ?DailyPayrollCsvExporter $dailyCsv = null,
        ?WeeklyPayrollCsvExporter $weeklyCsv = null
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
