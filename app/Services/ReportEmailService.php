<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Container;
use App\Logging\LoggerInterface;
use App\Repositories\CompanySettingsRepository;
use DateTimeImmutable;
use DateTimeZone;
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
            (string)(
                $company['timezone']
                ??
                'America/Los_Angeles'
            );


        if (
            !in_array(
                $timezone,
                timezone_identifiers_list(),
                true
            )
        ) {
            $timezone =
                'America/Los_Angeles';
        }


        $reportDate =
            (
                new DateTimeImmutable(
                    'now',
                    new DateTimeZone(
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
                (string)(
                    $company['company_name']
                    ??
                    'Company'
                );


            $body =
                $companyName
                .
                " Daily Payroll Report\n\n";


            $body .=
                'Date: '
                .
                $reportDate
                .
                "\n";


            $body .=
                'Timezone: '
                .
                $timezone
                .
                "\n\n";


            $body .=
                "Weekly overtime is calculated in the Weekly Payroll Summary "
                .
                "and is not assigned in this daily report.\n\n";


            $grossTotal = 0.0;

            $regularTotal = 0.0;

            $dailyOvertimeTotal = 0.0;

            $doubleTimeTotal = 0.0;

            $overtimeTotal = 0.0;

            $premiumTotal = 0.0;

            $payableTotal = 0.0;

            $issueCount = 0;


            if (empty($summary)) {

                $body .=
                    "No payroll data available.\n";

            } else {

                foreach ($summary as $employee) {

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
                            $employee['overtime_hours']
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
                            $dailyOvertimeHours
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


                    $recordedMealMinutes =
                        (int)(
                            $employee['recorded_meal_minutes']
                            ??
                            0
                        );


                    $automaticMealMinutes =
                        (int)(
                            $employee['automatic_meal_deduction_minutes']
                            ??
                            0
                        );


                    $mealMinutes =
                        $recordedMealMinutes
                        +
                        $automaticMealMinutes;


                    $unpaidBreakMinutes =
                        (int)(
                            $employee['unpaid_break_minutes']
                            ??
                            0
                        );


                    $complete =
                        !empty(
                            $employee['complete']
                        );


                    $errors =
                        is_array(
                            $employee['errors']
                            ??
                            null
                        )
                            ? $employee['errors']
                            : [];


                    $grossTotal +=
                        $grossHours;


                    $regularTotal +=
                        $regularHours;


                    $dailyOvertimeTotal +=
                        $dailyOvertimeHours;


                    $doubleTimeTotal +=
                        $doubleTimeHours;


                    $overtimeTotal +=
                        $overtimeHours;


                    $premiumTotal +=
                        $premiumHours;


                    $payableTotal +=
                        $payableHours;


                    if (!$complete) {

                        $issueCount++;
                    }


                    $body .=
                        (string)(
                            $employee['name']
                            ??
                            ''
                        )
                        .
                        ' ('
                        .
                        (string)(
                            $employee['employee_number']
                            ??
                            ''
                        )
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
                            (string)$employee['department']
                            .
                            "\n";
                    }


                    $body .=
                        'Gross Hours: '
                        .
                        $this->hours(
                            $grossHours
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
                        $unpaidBreakMinutes
                        .
                        " minutes\n";


                    $body .=
                        'Regular Hours: '
                        .
                        $this->hours(
                            $regularHours
                        )
                        .
                        "\n";


                    $body .=
                        'Daily Overtime Hours: '
                        .
                        $this->hours(
                            $dailyOvertimeHours
                        )
                        .
                        "\n";


                    $body .=
                        'Double-Time Hours: '
                        .
                        $this->hours(
                            $doubleTimeHours
                        )
                        .
                        "\n";


                    $body .=
                        'Total Overtime Hours: '
                        .
                        $this->hours(
                            $overtimeHours
                        )
                        .
                        "\n";


                    $body .=
                        'Premium Hours: '
                        .
                        $this->hours(
                            $premiumHours
                        )
                        .
                        "\n";


                    $body .=
                        'Payable Hours: '
                        .
                        $this->hours(
                            $payableHours
                        )
                        .
                        "\n";


                    $body .=
                        'Status: '
                        .
                        (
                            $complete
                                ? 'Complete'
                                : 'Needs Review'
                        )
                        .
                        "\n";


                    if (!$complete) {

                        foreach ($errors as $error) {

                            $body .=
                                '- '
                                .
                                (string)$error
                                .
                                "\n";
                        }
                    }


                    $body .=
                        "\n";
                }


                $body .=
                    "Report Totals\n";


                $body .=
                    "-------------\n";


                $body .=
                    'Employees: '
                    .
                    count(
                        $summary
                    )
                    .
                    "\n";


                $body .=
                    'Gross Hours: '
                    .
                    $this->hours(
                        $grossTotal
                    )
                    .
                    "\n";


                $body .=
                    'Regular Hours: '
                    .
                    $this->hours(
                        $regularTotal
                    )
                    .
                    "\n";


                $body .=
                    'Daily Overtime Hours: '
                    .
                    $this->hours(
                        $dailyOvertimeTotal
                    )
                    .
                    "\n";


                $body .=
                    'Double-Time Hours: '
                    .
                    $this->hours(
                        $doubleTimeTotal
                    )
                    .
                    "\n";


                $body .=
                    'Total Overtime Hours: '
                    .
                    $this->hours(
                        $overtimeTotal
                    )
                    .
                    "\n";


                $body .=
                    'Premium Hours: '
                    .
                    $this->hours(
                        $premiumTotal
                    )
                    .
                    "\n";


                $body .=
                    'Payable Hours: '
                    .
                    $this->hours(
                        $payableTotal
                    )
                    .
                    "\n";


                $body .=
                    'Employees Needing Review: '
                    .
                    $issueCount
                    .
                    "\n";
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
                        ),

                    'issue_count' =>
                        $issueCount,

                    'regular_hours' =>
                        $regularTotal,

                    'daily_overtime_hours' =>
                        $dailyOvertimeTotal,

                    'double_time_hours' =>
                        $doubleTimeTotal,

                    'overtime_hours' =>
                        $overtimeTotal,

                    'premium_hours' =>
                        $premiumTotal,

                    'total_hours' =>
                        $payableTotal
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
