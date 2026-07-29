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

class ReportEmailService
{
    private PunchReportService $reports;

    private MailService $mail;

    private CompanySettingsRepository $settings;

    private PayrollReportPeriodMetadataService $periodMetadata;

    private PayrollEmailAttachmentService $attachments;

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


        $this->attachments =
            new PayrollEmailAttachmentService();


        $this->logger =
            Container::logger(
                'reports'
            );
    }


    public function sendDailyPayrollReport(
        string $source = 'manual',
        ?int $scheduleId = null,
        int $attemptNumber = 1,
        int $maxAttempts = 1,
        ?int $retryOfId = null,
        ?string $reportDate = null
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


        $reportDate =
            $this->validReportDate(
                $reportDate,
                $timezone
            );


        $this->logger->info(
            'Daily payroll report generation started.',
            [
                'report_type' =>
                    'daily_payroll',

                'report_date' =>
                    $reportDate,

                'delivery_source' =>
                    $source,

                'schedule_id' =>
                    $scheduleId,

                'attempt_number' =>
                    $attemptNumber,

                'max_attempts' =>
                    $maxAttempts,

                'retry_of_id' =>
                    $retryOfId
            ]
        );


        try {

            $summary =
                $this->reports
                    ->dailySummary(
                        $reportDate
                    );


            $periodMetadata =
                $this->periodMetadata
                    ->forRange(
                        $reportDate,
                        $reportDate
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
                $timezoneName
                .
                "\n\n";


            $body .=
                $this->payrollPeriodBody(
                    $periodMetadata
                );


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
                        null,

                    'delivery_source' =>
                        $source,

                    'schedule_id' =>
                        $scheduleId
                ]
            );


            $csvAttachment =
                $this->attachments
                    ->dailyCsv(
                        $summary,
                        $reportDate
                    );


            $pdfAttachment =
                $this->attachments
                    ->dailyPdf(
                        $summary,
                        $company,
                        $reportDate
                    );


            $emailAttachments = [
                $csvAttachment,
                $pdfAttachment
            ];


            $this->logger->info(
                'Daily payroll email attachments generated.',
                [
                    'report_type' =>
                        'daily_payroll',

                    'report_date' =>
                        $reportDate,

                    'attachment_count' =>
                        count(
                            $emailAttachments
                        ),

                    'attachment_names' => [
                        $csvAttachment['filename'],
                        $pdfAttachment['filename']
                    ],

                    'csv_size_bytes' =>
                        strlen(
                            $csvAttachment['contents']
                        ),

                    'pdf_size_bytes' =>
                        strlen(
                            $pdfAttachment['contents']
                        ),

                    'attachment_size_bytes' =>
                        strlen(
                            $csvAttachment['contents']
                        )
                        +
                        strlen(
                            $pdfAttachment['contents']
                        ),

                    'delivery_source' =>
                        $source,

                    'schedule_id' =>
                        $scheduleId
                ]
            );


            $sent =
                $this->mail
                    ->send(
                        $companyName
                        .
                        ' Daily Payroll Report',
                        $body,
                        'daily_payroll',
                        $emailAttachments,
                        $source,
                        $scheduleId,
                        $attemptNumber,
                        $maxAttempts,
                        $retryOfId
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
                            ),

                        'payroll_period_association' =>
                            $association,

                        'attachment_count' =>
                            count(
                                $emailAttachments
                            ),

                        'attachment_names' => [
                            $csvAttachment['filename'],
                            $pdfAttachment['filename']
                        ],

                        'delivery_source' =>
                            $source,

                        'schedule_id' =>
                            $scheduleId,

                        'attempt_number' =>
                            $attemptNumber,

                        'max_attempts' =>
                            $maxAttempts,

                        'retry_of_id' =>
                            $retryOfId
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
                        ),

                    'payroll_period_association' =>
                        $association,

                    'attachment_count' =>
                        count(
                            $emailAttachments
                        ),

                    'attachment_names' => [
                        $csvAttachment['filename'],
                        $pdfAttachment['filename']
                    ],

                    'attachment_size_bytes' =>
                        strlen(
                            $csvAttachment['contents']
                        )
                        +
                        strlen(
                            $pdfAttachment['contents']
                        ),

                    'delivery_source' =>
                        $source,

                    'schedule_id' =>
                        $scheduleId,

                    'attempt_number' =>
                        $attemptNumber,

                    'max_attempts' =>
                        $maxAttempts,

                    'retry_of_id' =>
                        $retryOfId
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

                    'delivery_source' =>
                        $source,

                    'schedule_id' =>
                        $scheduleId,

                    'attempt_number' =>
                        $attemptNumber,

                    'max_attempts' =>
                        $maxAttempts,

                    'retry_of_id' =>
                        $retryOfId,

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


    private function validReportDate(
        ?string $reportDate,
        DateTimeZone $timezone
    ): string
    {
        $reportDate =
            trim(
                (string)(
                    $reportDate
                    ??
                    ''
                )
            );


        if ($reportDate === '') {

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
                $reportDate,
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
            $reportDate
        ) {
            throw new InvalidArgumentException(
                'Daily report date must be a valid date in YYYY-MM-DD format.'
            );
        }


        return $reportDate;
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
                "This daily report does not inherit payroll review, approval, "
                .
                "lock, or exception-resolution status because the one-day "
                .
                "report range is not an exact payroll-period match.\n\n";


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
            "This daily report does not carry payroll review, approval, "
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
