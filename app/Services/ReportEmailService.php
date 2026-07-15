<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Container;
use App\Logging\LoggerInterface;
use App\Repositories\CompanySettingsRepository;
use Throwable;

class ReportEmailService
{
    private PunchReportService $reports;

    private MailService $mail;

    private CompanySettingsRepository $settings;

    private LoggerInterface $logger;


    public function __construct()
    {
        $this->reports =
            Container::punchReportService();


        $this->mail =
            Container::mailService();


        $this->settings =
            Container::companySettingsRepository();


        $this->logger =
            Container::logger(
                'reports'
            );
    }


    public function sendDailyPayrollReport(): bool
    {
        $company =
            $this->settings->get();


        $timezone =
            $company['timezone']
            ??
            'America/Los_Angeles';


        $reportDate =
            (
                new \DateTimeImmutable(
                    'now',
                    new \DateTimeZone(
                        $timezone
                    )
                )
            )->format(
                'Y-m-d'
            );


        $this->logger->info(
            'Daily payroll report generation started.',
            [
                'report_type' =>
                    'daily_payroll',

                'report_date' =>
                    $reportDate
            ]
        );


        try {

            $summary =
                $this->reports->dailySummary(
                    $reportDate
                );


            $companyName =
                $company['company_name']
                ??
                'Company';


            $body =
                $companyName
                .
                " Daily Payroll Report\n\n";


            $body .=
                'Date: '
                .
                $reportDate
                .
                "\n\n";


            if (empty($summary)) {

                $body .=
                    "No payroll data available.\n";

            } else {

                foreach ($summary as $employee) {

                    $body .=
                        $employee['name']
                        .
                        ' ('
                        .
                        $employee['employee_number']
                        .
                        ")\n";


                    if (
                        !empty(
                            $employee['department']
                        )
                    ) {
                        $body .=
                            'Department: '
                            .
                            $employee['department']
                            .
                            "\n";
                    }


                    $mealMinutes =
                        (int)$employee['recorded_meal_minutes']
                        +
                        (int)$employee['automatic_meal_deduction_minutes'];


                    $body .=
                        'Gross Hours: '
                        .
                        number_format(
                            (float)$employee['gross_hours'],
                            2
                        )
                        .
                        "\n";


                    $body .=
                        'Meal Deduction: '
                        .
                        $mealMinutes
                        .
                        " minutes\n";


                    $body .=
                        'Unpaid Break: '
                        .
                        (int)$employee['unpaid_break_minutes']
                        .
                        " minutes\n";


                    $body .=
                        'Regular Hours: '
                        .
                        number_format(
                            (float)$employee['regular_hours'],
                            2
                        )
                        .
                        "\n";


                    $body .=
                        'Overtime Hours: '
                        .
                        number_format(
                            (float)$employee['overtime_hours'],
                            2
                        )
                        .
                        "\n";


                    $body .=
                        'Payable Hours: '
                        .
                        number_format(
                            (float)$employee['total_hours'],
                            2
                        )
                        .
                        "\n";


                    $body .=
                        'Status: '
                        .
                        (
                            $employee['complete']
                                ? 'Complete'
                                : 'Needs Review'
                        )
                        .
                        "\n";


                    if (!$employee['complete']) {

                        foreach (
                            $employee['errors']
                            as $error
                        ) {
                            $body .=
                                '- '
                                .
                                $error
                                .
                                "\n";
                        }
                    }


                    $body .=
                        "\n";
                }
            }


            $this->logger->info(
                'Daily payroll report generated.',
                [
                    'report_type' =>
                        'daily_payroll',

                    'report_date' =>
                        $reportDate,

                    'employee_count' =>
                        count(
                            $summary
                        )
                ]
            );


            $sent =
                $this->mail->send(
                    $companyName
                    .
                    ' Daily Payroll Report',
                    $body
                );


            if (!$sent) {

                $this->logger->error(
                    'Daily payroll report delivery returned a failure result.',
                    [
                        'report_type' =>
                            'daily_payroll',

                        'report_date' =>
                            $reportDate,

                        'employee_count' =>
                            count(
                                $summary
                            )
                    ]
                );


                return false;
            }


            $this->logger->info(
                'Daily payroll report delivery completed successfully.',
                [
                    'report_type' =>
                        'daily_payroll',

                    'report_date' =>
                        $reportDate,

                    'employee_count' =>
                        count(
                            $summary
                        )
                ]
            );


            return true;

        } catch (Throwable $exception) {

            $this->logger->error(
                'Daily payroll report processing failed.',
                [
                    'report_type' =>
                        'daily_payroll',

                    'report_date' =>
                        $reportDate,

                    'exception_class' =>
                        $exception::class,

                    'exception_message' =>
                        $exception->getMessage(),

                    'exception_file' =>
                        $exception->getFile(),

                    'exception_line' =>
                        $exception->getLine()
                ]
            );


            throw $exception;
        }
    }
}
