<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Flash;
use App\Repositories\ReportScheduleRepository;
use App\Services\ReportEmailService;
use App\Services\ReportScheduleService;

class ReportEmailController extends Controller
{
    private ReportEmailService $reports;

    private ReportScheduleService $schedule;


    public function __construct()
    {
        $this->reports =
            new ReportEmailService();


        $this->schedule =
            new ReportScheduleService(
                new ReportScheduleRepository(
                    Database::connection()
                )
            );
    }


    public function index(): void
    {
        $mailConfig =
            require __DIR__
            . '/../../config/mail.php';


        $this->render(
            'reports/email.twig',
            [
                'title' =>
                    'Email Reports',

                'activeMenu' =>
                    'reports',

                'schedule' =>
                    $this->schedule->get(),

                'recipientCount' =>
                    count(
                        $mailConfig['recipients']
                        ?? []
                    )
            ]
        );
    }


    public function sendDaily(): void
    {
        try {

            $sent =
                $this->reports
                    ->sendDailyPayrollReport();


            if ($sent) {

                Flash::success(
                    'Daily payroll report sent successfully.'
                );

            } else {

                Flash::error(
                    'Daily payroll report failed to send.'
                );
            }


        } catch (\Throwable $e) {

            Flash::error(
                'Report email failed: '
                .
                $e->getMessage()
            );
        }


        header(
            'Location: /reports/email'
        );

        exit;
    }


    public function updateSchedule(): void
    {
        $result =
            $this->schedule->update(
                $_POST
            );


        if ($result['success']) {

            Flash::success(
                'Automatic report schedule saved successfully.'
            );

        } else {

            $message =
                $result['errors']['send_time']
                ??
                'Unable to save the report schedule.';


            Flash::error(
                $message
            );
        }


        header(
            'Location: /reports/email'
        );

        exit;
    }
}
