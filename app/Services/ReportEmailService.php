<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\CompanySettingsRepository;
use App\Repositories\EmailRepository;
use App\Repositories\PunchRepository;

class ReportEmailService
{
    private PunchReportService $reports;
    private MailService $mail;
    private CompanySettingsRepository $settings;
    private EmailRepository $emails;


    public function __construct()
    {
        $db =
            Database::connection();


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


        $this->emails =
            new EmailRepository(
                $db
            );
    }



    public function sendDailyPayrollReport(): bool
    {
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
            "Date: "
            .
            date('Y-m-d')
            .
            "\n\n";


        foreach ($summary as $employee) {

            $body .=
                $employee['name']
                .
                " ("
                .
                $employee['employee_number']
                .
                ")\n";


            $body .=
                "Hours: "
                .
                $employee['hours']
                .
                "\n\n";
        }


        $config =
            require __DIR__
            .
            '/../../config/mail.php';


        $recipients =
            implode(
                ', ',
                $config['recipients']
            );


        try {

            $sent =
                $this->mail->send(
                    $companyName . ' Daily Payroll Report',
                    $body
                );


            $this->emails->create(
                'Daily Payroll Report',
                $recipients,
                'sent'
            );


            return $sent;


        } catch (\Throwable $e) {


            $this->emails->create(
                'Daily Payroll Report',
                $recipients,
                'failed'
            );


            throw $e;
        }
    }
}
