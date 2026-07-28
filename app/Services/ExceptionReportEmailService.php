<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Container;
use App\Logging\LoggerInterface;
use App\Repositories\CompanySettingsRepository;
use App\Repositories\PayrollExceptionResolutionRepository;
use App\Repositories\PayrollPeriodRepository;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final class ExceptionReportEmailService
{
    private PayrollPeriodRepository $periods;

    private PayrollExceptionResolutionRepository $exceptions;

    private CompanySettingsRepository $settings;

    private MailService $mail;

    private LoggerInterface $logger;


    public function __construct()
    {
        $this->periods =
            Container::payrollPeriodRepository();


        $this->exceptions =
            Container::payrollExceptionResolutionRepository();


        $this->settings =
            Container::companySettingsRepository();


        $this->mail =
            Container::mailService();


        $this->logger =
            Container::logger(
                'reports'
            );
    }


    public function sendOpenExceptionReport(): bool
    {
        try {

            $company =
                $this->settings
                    ->get()
                ??
                [];


            $companyName =
                trim(
                    (string)(
                        $company['company_name']
                        ??
                        ''
                    )
                );


            if ($companyName === '') {
                $companyName =
                    'IQwurksPunch';
            }


            $timezoneName =
                trim(
                    (string)(
                        $company['timezone']
                        ??
                        'America/Los_Angeles'
                    )
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


            $generatedAt =
                new DateTimeImmutable(
                    'now',
                    $timezone
                );


            $report =
                $this->openExceptionReportData();


            $periodCount =
                (int)$report['period_count'];


            $exceptionCount =
                (int)$report['exception_count'];


            if ($exceptionCount === 0) {

                $this->logger->info(
                    'Payroll exception report delivery skipped because no open exceptions exist.',
                    [
                        'report_type' =>
                            'exception_reports',

                        'eligible_period_count' =>
                            0,

                        'open_exception_count' =>
                            0,

                        'generated_at' =>
                            $generatedAt->format(
                                DATE_ATOM
                            )
                    ]
                );


                /*
                 * No email is necessary. Returning true means the scheduled
                 * exception check completed successfully and should not be
                 * retried repeatedly throughout the same delivery day.
                 */
                return true;
            }


            $subject =
                $companyName
                .
                ' Payroll Exception Report: '
                .
                $exceptionCount
                .
                ' Open '
                .
                (
                    $exceptionCount === 1
                        ? 'Exception'
                        : 'Exceptions'
                );


            $body =
                $this->buildBody(
                    $companyName,
                    $timezoneName,
                    $generatedAt,
                    $report
                );


            $this->logger->info(
                'Payroll exception report delivery started.',
                [
                    'report_type' =>
                        'exception_reports',

                    'eligible_period_count' =>
                        $periodCount,

                    'open_exception_count' =>
                        $exceptionCount,

                    'generated_at' =>
                        $generatedAt->format(
                            DATE_ATOM
                        )
                ]
            );


            $sent =
                $this->mail
                    ->send(
                        $subject,
                        $body,
                        'exception_reports'
                    );


            if (!$sent) {

                $this->logger->error(
                    'Payroll exception report delivery returned a failure result.',
                    [
                        'report_type' =>
                            'exception_reports',

                        'eligible_period_count' =>
                            $periodCount,

                        'open_exception_count' =>
                            $exceptionCount
                    ]
                );


                return false;
            }


            $this->logger->info(
                'Payroll exception report delivery completed successfully.',
                [
                    'report_type' =>
                        'exception_reports',

                    'eligible_period_count' =>
                        $periodCount,

                    'open_exception_count' =>
                        $exceptionCount
                ]
            );


            return true;

        } catch (Throwable $exception) {

            $this->logger->error(
                'Payroll exception report processing failed.',
                [
                    'report_type' =>
                        'exception_reports',

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


    /**
     * Return open exceptions from payroll periods that are still actionable.
     *
     * Approved and locked periods are intentionally excluded. Resolved and
     * accepted exceptions are excluded by openForPeriod().
     *
     * @return array{
     *     period_count:int,
     *     exception_count:int,
     *     periods:array<int,array{
     *         period:array<string,mixed>,
     *         exceptions:array<int,array<string,mixed>>
     *     }>
     * }
     */
    public function openExceptionReportData(): array
    {
        $reportPeriods = [];

        $exceptionCount =
            0;


        foreach (
            $this->periods->all()
            as
            $period
        ) {
            $status =
                (string)(
                    $period['status']
                    ??
                    ''
                );


            if (
                !in_array(
                    $status,
                    [
                        'open',
                        'under_review'
                    ],
                    true
                )
            ) {
                continue;
            }


            $periodId =
                (int)(
                    $period['id']
                    ??
                    0
                );


            if ($periodId < 1) {
                continue;
            }


            $openExceptions =
                $this->exceptions
                    ->openForPeriod(
                        $periodId
                    );


            if ($openExceptions === []) {
                continue;
            }


            $reportPeriods[] = [
                'period' =>
                    $period,

                'exceptions' =>
                    $openExceptions
            ];


            $exceptionCount +=
                count(
                    $openExceptions
                );
        }


        return [
            'period_count' =>
                count(
                    $reportPeriods
                ),

            'exception_count' =>
                $exceptionCount,

            'periods' =>
                $reportPeriods
        ];
    }


    /**
     * @param array{
     *     period_count:int,
     *     exception_count:int,
     *     periods:array<int,array{
     *         period:array<string,mixed>,
     *         exceptions:array<int,array<string,mixed>>
     *     }>
     * } $report
     */
    private function buildBody(
        string $companyName,
        string $timezoneName,
        DateTimeImmutable $generatedAt,
        array $report
    ): string
    {
        $periodCount =
            (int)$report['period_count'];


        $exceptionCount =
            (int)$report['exception_count'];


        $body =
            $companyName
            .
            " Payroll Exception Report\n"
            .
            str_repeat(
                '=',
                72
            )
            .
            "\n\n"
            .
            'Generated: '
            .
            $generatedAt->format(
                'Y-m-d H:i:s T'
            )
            .
            "\n"
            .
            'Company Timezone: '
            .
            $timezoneName
            .
            "\n"
            .
            'Actionable Payroll Periods: '
            .
            $periodCount
            .
            "\n"
            .
            'Open Payroll Exceptions: '
            .
            $exceptionCount
            .
            "\n\n"
            .
            "This report includes only open exceptions from payroll periods\n"
            .
            "whose workflow status is Open or Under Review.\n"
            .
            "Resolved or accepted exceptions and approved or locked periods\n"
            .
            "are not included.\n";


        foreach (
            $report['periods']
            as
            $periodGroup
        ) {
            $period =
                $periodGroup['period'];


            $openExceptions =
                $periodGroup['exceptions'];


            $periodName =
                trim(
                    (string)(
                        $period['period_name']
                        ??
                        ''
                    )
                );


            if ($periodName === '') {
                $periodName =
                    'Payroll Period #'
                    .
                    (int)(
                        $period['id']
                        ??
                        0
                    );
            }


            $status =
                $this->displayStatus(
                    (string)(
                        $period['status']
                        ??
                        ''
                    )
                );


            $body .=
                "\n\n"
                .
                str_repeat(
                    '-',
                    72
                )
                .
                "\n"
                .
                'Payroll Period: '
                .
                $periodName
                .
                "\n"
                .
                'Period ID: '
                .
                (int)(
                    $period['id']
                    ??
                    0
                )
                .
                "\n"
                .
                'Date Range: '
                .
                (string)(
                    $period['start_date']
                    ??
                    'unknown'
                )
                .
                ' through '
                .
                (string)(
                    $period['end_date']
                    ??
                    'unknown'
                )
                .
                "\n"
                .
                'Workflow Status: '
                .
                $status
                .
                "\n"
                .
                'Open Exceptions: '
                .
                count(
                    $openExceptions
                )
                .
                "\n";


            foreach (
                $openExceptions
                as
                $index =>
                $exception
            ) {
                $body .=
                    "\n"
                    .
                    (
                        $index + 1
                    )
                    .
                    '. '
                    .
                    $this->employeeLabel(
                        $exception
                    )
                    .
                    "\n"
                    .
                    '   Date: '
                    .
                    (
                        trim(
                            (string)(
                                $exception['exception_date']
                                ??
                                ''
                            )
                        )
                        ?:
                        'Not date-specific'
                    )
                    .
                    "\n"
                    .
                    '   Type: '
                    .
                    $this->displayStatus(
                        (string)(
                            $exception['exception_type']
                            ??
                            'unspecified'
                        )
                    )
                    .
                    "\n"
                    .
                    '   Description: '
                    .
                    trim(
                        (string)(
                            $exception['description']
                            ??
                            ''
                        )
                    )
                    .
                    "\n";
            }
        }


        $body .=
            "\n\n"
            .
            str_repeat(
                '=',
                72
            )
            .
            "\n"
            .
            "Review these items in IQwurksPunch before approving the affected\n"
            .
            "payroll periods.\n";


        return $body;
    }


    /**
     * @param array<string,mixed> $exception
     */
    private function employeeLabel(
        array $exception
    ): string
    {
        $employeeNumber =
            trim(
                (string)(
                    $exception['employee_number']
                    ??
                    ''
                )
            );


        $employeeName =
            trim(
                implode(
                    ' ',
                    array_filter(
                        [
                            trim(
                                (string)(
                                    $exception['first_name']
                                    ??
                                    ''
                                )
                            ),

                            trim(
                                (string)(
                                    $exception['last_name']
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


        if (
            $employeeNumber !== ''
            &&
            $employeeName !== ''
        ) {
            return
                'Employee '
                .
                $employeeNumber
                .
                ' — '
                .
                $employeeName;
        }


        if ($employeeNumber !== '') {
            return
                'Employee '
                .
                $employeeNumber;
        }


        if ($employeeName !== '') {
            return
                $employeeName;
        }


        return
            'Payroll period-level exception';
    }


    private function displayStatus(
        string $value
    ): string
    {
        $value =
            trim(
                $value
            );


        if ($value === '') {
            return 'Unknown';
        }


        return ucwords(
            str_replace(
                '_',
                ' ',
                $value
            )
        );
    }
}
