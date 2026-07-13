<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Container;
use App\Logging\LoggerInterface;
use App\Repositories\CompanySettingsRepository;
use App\Repositories\PunchRepository;
use Throwable;

class ReportEmailService
{
    private PunchReportService $reports;

    private MailService $mail;

    private CompanySettingsRepository $settings;

    private LoggerInterface $logger;


    public function __construct()
    {
        $db =
            Container::db();


        $this->reports =
            new PunchReportService(
                new PunchRepository(
                    $db
                )
            );


        $this->mail =
            new MailService();


        $this->settings =
            new CompanySettingsRepository(
                $db
            );


        $this->logger =
            Container::logger(
                'reports'
            );
    }


    public function sendDailyPayrollReport(): bool
    {
        $reportDate =
            date(
                'Y-m-d'
            );


        $this->logger->info(
            'Daily payroll report generation started.',
            [
                'report_type' =>
                    'daily_payroll',

                'report_date' =>
                    $reportDate,
            ]
        );


        try {

            $summary =
                $this->reports->dailySummary();


            $company =
                $this->settings->get();


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


            foreach ($summary as $employee) {

                $body .=
                    $employee['name']
                    .
                    ' ('
                    .
                    $employee['employee_number']
                    .
                    ")\n";


                $body .=
                    'Hours: '
                    .
                    $employee['hours']
                    .
                    "\n\n";
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
                            ),
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
                        $exception->getLine(),
                ]
            );


            throw $exception;
        }
    }
}
