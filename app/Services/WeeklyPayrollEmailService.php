<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Container;
use App\Logging\LoggerInterface;
use App\Repositories\CompanySettingsRepository;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Throwable;

final class WeeklyPayrollEmailService
{
    private PunchReportService $reports;

    private MailService $mail;

    private CompanySettingsRepository $settings;

    private PayrollReportPeriodMetadataService $periodMetadata;

    private LoggerInterface $logger;


    public function __construct()
    {
        $this->reports =
            Container::punchReportService();


        $this->mail =
            Container::mailService();


        $this->settings =
            Container::companySettingsRepository();


        $this->periodMetadata =
            Container::payrollReportPeriodMetadataService();


        $this->logger =
            Container::logger(
                'reports'
            );
    }


    public function sendWeeklyPayrollReport(
        ?string $referenceDate = null
    ): bool
    {
        $company =
            $this->settings
                ->get()
            ??
            [];


        $timezoneName =
            (string)(
                $company['timezone']
                ??
                'America/Los_Angeles'
            );


        if (
            !in_array(
                $timezoneName,
                timezone_identifiers_list(),
                true
            )
        ) {
            $timezoneName =
                'America/Los_Angeles';
        }


        $timezone =
            new DateTimeZone(
                $timezoneName
            );


        $referenceDate =
            $this->validReferenceDate(
                $referenceDate,
                $timezone
            );


        $this->logger->info(
            'Weekly payroll report generation started.',
            [
                'report_type' =>
                    'weekly_payroll',

                'reference_date' =>
                    $referenceDate
            ]
        );


        try {

            $summary =
                $this->reports
                    ->weeklySummary(
                        $referenceDate
                    );


            $weekStart =
                (string)(
                    $summary['week_start']
                    ??
                    ''
                );


            $weekEnd =
                (string)(
                    $summary['week_end']
                    ??
                    ''
                );


            if (
                $weekStart === ''
                ||
                $weekEnd === ''
            ) {
                throw new InvalidArgumentException(
                    'The weekly payroll report did not return a valid date range.'
                );
            }


            $employees =
                is_array(
                    $summary['employees']
                    ??
                    null
                )
                    ? $summary['employees']
                    : [];


            $periodMetadata =
                $this->periodMetadata
                    ->forRange(
                        $weekStart,
                        $weekEnd
                    );


            $association =
                (string)(
                    $periodMetadata['association_status']
                    ??
                    'none'
                );


            $payrollPeriod =
                is_array(
                    $periodMetadata['payroll_period']
                    ??
                    null
                )
                    ? $periodMetadata['payroll_period']
                    : null;


            $companyName =
                trim(
                    (string)(
                        $company['company_name']
                        ??
                        'Company'
                    )
                );


            if ($companyName === '') {
                $companyName =
                    'Company';
            }


            $body =
                $companyName
                .
                " Weekly Payroll Report\n\n";


            $body .=
                'Week: '
                .
                $weekStart
                .
                ' through '
                .
                $weekEnd
                .
                "\n";


            $body .=
                'Timezone: '
                .
                $timezoneName
                .
                "\n\n";


            $body .=
                $this->payrollPeriodBody(
                    $periodMetadata
                );


            $grossTotal = 0.0;

            $regularTotal = 0.0;

            $dailyOvertimeTotal = 0.0;

            $weeklyOvertimeTotal = 0.0;

            $doubleTimeTotal = 0.0;

            $overtimeTotal = 0.0;

            $premiumTotal = 0.0;

            $payableTotal = 0.0;

            $issueCount = 0;


            if (empty($employees)) {

                $body .=
                    "No weekly payroll activity was found.\n";

            } else {

                foreach ($employees as $employee) {

                    if (!is_array($employee)) {
                        continue;
                    }


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
                        'Weekly Overtime Hours: '
                        .
                        $this->hours(
                            $weeklyOvertimeHours
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
                        'Total Payable Hours: '
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
                        $employees
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
                    'Weekly Overtime Hours: '
                    .
                    $this->hours(
                        $weeklyOvertimeTotal
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
                    'Total Payable Hours: '
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
                'Weekly payroll report generated.',
                [
                    'report_type' =>
                        'weekly_payroll',

                    'reference_date' =>
                        $referenceDate,

                    'week_start' =>
                        $weekStart,

                    'week_end' =>
                        $weekEnd,

                    'employee_count' =>
                        count(
                            $employees
                        ),

                    'issue_count' =>
                        $issueCount,

                    'regular_hours' =>
                        $regularTotal,

                    'daily_overtime_hours' =>
                        $dailyOvertimeTotal,

                    'weekly_overtime_hours' =>
                        $weeklyOvertimeTotal,

                    'double_time_hours' =>
                        $doubleTimeTotal,

                    'overtime_hours' =>
                        $overtimeTotal,

                    'premium_hours' =>
                        $premiumTotal,

                    'total_hours' =>
                        $payableTotal,

                    'payroll_period_association' =>
                        $association,

                    'payroll_period_id' =>
                        $payrollPeriod['id']
                        ??
                        null,

                    'payroll_period_status' =>
                        $payrollPeriod['status']
                        ??
                        null,

                    'open_payroll_exception_count' =>
                        $payrollPeriod['open_exception_count']
                        ??
                        null
                ]
            );


            $subject =
                $companyName
                .
                ' Weekly Payroll Report: '
                .
                $weekStart
                .
                ' through '
                .
                $weekEnd;


            $sent =
                $this->mail
                    ->send(
                        $subject,
                        $body,
                        'weekly_payroll'
                    );


            if (!$sent) {

                $this->logger->error(
                    'Weekly payroll report delivery returned a failure result.',
                    [
                        'report_type' =>
                            'weekly_payroll',

                        'reference_date' =>
                            $referenceDate,

                        'week_start' =>
                            $weekStart,

                        'week_end' =>
                            $weekEnd,

                        'employee_count' =>
                            count(
                                $employees
                            ),

                        'payroll_period_association' =>
                            $association
                    ]
                );


                return false;
            }


            $this->logger->info(
                'Weekly payroll report delivery completed successfully.',
                [
                    'report_type' =>
                        'weekly_payroll',

                    'reference_date' =>
                        $referenceDate,

                    'week_start' =>
                        $weekStart,

                    'week_end' =>
                        $weekEnd,

                    'employee_count' =>
                        count(
                            $employees
                        ),

                    'payroll_period_association' =>
                        $association
                ]
            );


            return true;

        } catch (Throwable $exception) {

            $this->logger->error(
                'Weekly payroll report processing failed.',
                [
                    'report_type' =>
                        'weekly_payroll',

                    'reference_date' =>
                        $referenceDate,

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


    private function validReferenceDate(
        ?string $referenceDate,
        DateTimeZone $timezone
    ): string
    {
        $referenceDate =
            trim(
                (string)(
                    $referenceDate
                    ??
                    ''
                )
            );


        if ($referenceDate === '') {

            return
                (
                    new DateTimeImmutable(
                        'now',
                        $timezone
                    )
                )->format(
                    'Y-m-d'
                );
        }


        $date =
            DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $referenceDate,
                $timezone
            );


        $errors =
            DateTimeImmutable::getLastErrors();


        if (
            !$date
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
            $date->format(
                'Y-m-d'
            )
            !==
            $referenceDate
        ) {
            throw new InvalidArgumentException(
                'Weekly report date must be a valid date in YYYY-MM-DD format.'
            );
        }


        return $referenceDate;
    }


    /**
     * @param array{
     *     association_status?:mixed,
     *     association_message?:mixed,
     *     payroll_period?:mixed
     * } $metadata
     */
    private function payrollPeriodBody(
        array $metadata
    ): string
    {
        $association =
            (string)(
                $metadata['association_status']
                ??
                'none'
            );


        $message =
            trim(
                (string)(
                    $metadata['association_message']
                    ??
                    'This report is not associated with a payroll period.'
                )
            );


        $payrollPeriod =
            $metadata['payroll_period']
            ??
            null;


        $body =
            "Payroll-Period Association\n"
            .
            "--------------------------\n";


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


            $body .=
                "Association: Exact Match\n";


            $body .=
                'Payroll Period ID: '
                .
                (int)(
                    $payrollPeriod['id']
                    ??
                    0
                )
                .
                "\n";


            $body .=
                'Payroll Period Name: '
                .
                (string)(
                    $payrollPeriod['period_name']
                    ??
                    ''
                )
                .
                "\n";


            $body .=
                'Payroll Period Range: '
                .
                (string)(
                    $payrollPeriod['start_date']
                    ??
                    ''
                )
                .
                ' through '
                .
                (string)(
                    $payrollPeriod['end_date']
                    ??
                    ''
                )
                .
                "\n";


            $body .=
                'Workflow Status: '
                .
                $this->workflowStatusLabel(
                    $status
                )
                .
                "\n";


            $body .=
                'Open Payroll Exceptions: '
                .
                (int)(
                    $payrollPeriod['open_exception_count']
                    ??
                    0
                )
                .
                "\n";


            $body .=
                'Total Payroll Exceptions: '
                .
                (int)(
                    $payrollPeriod['total_exception_count']
                    ??
                    0
                )
                .
                "\n";


            $body .=
                'Approval Blocked: '
                .
                (
                    !empty(
                        $payrollPeriod['approval_blocked']
                    )
                        ? 'Yes'
                        : 'No'
                )
                .
                "\n";


            $body .=
                'Created: '
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
                "\n";


            $body .=
                'Review Started: '
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
                "\n";


            $body .=
                'Approved: '
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
                "\n";


            $body .=
                'Locked: '
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
                "\n";


            if ($message !== '') {

                $body .=
                    'Association Note: '
                    .
                    $message
                    .
                    "\n";
            }


            if (
                in_array(
                    $status,
                    [
                        'approved',
                        'locked'
                    ],
                    true
                )
            ) {
                $body .=
                    "Punch Protection: Punch corrections in this period require reopening the payroll period.\n";
            }


            return
                $body
                .
                "\n";
        }


        if ($association === 'partial_overlap') {

            $body .=
                "Association: Partial Overlap\n";


            if ($message !== '') {

                $body .=
                    'Association Note: '
                    .
                    $message
                    .
                    "\n";
            }


            $body .=
                "This weekly report does not inherit payroll review, approval, "
                .
                "lock, or exception-resolution status because the report range "
                .
                "does not exactly match one payroll period.\n\n";


            return $body;
        }


        $body .=
            "Association: None\n";


        if ($message !== '') {

            $body .=
                'Association Note: '
                .
                $message
                .
                "\n";
        }


        $body .=
            "This weekly report does not carry payroll review, approval, "
            .
            "lock, or exception-resolution status.\n\n";


        return $body;
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
            return 'Not recorded';
        }


        $record =
            $timestamp === ''
                ? 'Time unavailable'
                : $timestamp;


        if ($username !== '') {

            $record .=
                ' by '
                .
                $username;
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
}
