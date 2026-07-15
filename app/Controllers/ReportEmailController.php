<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Container;
use App\Core\Flash;
use App\Services\NotificationRecipientService;
use App\Services\ReportEmailService;
use App\Services\ReportScheduleService;
use Throwable;

class ReportEmailController extends Controller
{
    private ReportEmailService $reports;

    private ReportScheduleService $schedule;

    private NotificationRecipientService $recipients;


    public function __construct()
    {
        $this->reports =
            Container::reportEmailService();


        $this->schedule =
            Container::reportScheduleService();


        $this->recipients =
            Container::notificationRecipientService();
    }


    public function index(): void
    {
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
                    $this->recipients->countActiveFor(
                        'daily_payroll'
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

        } catch (Throwable $exception) {

            Flash::error(
                'Report email failed: '
                .
                $exception->getMessage()
            );
        }


        $this->redirect();
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


        $this->redirect();
    }


    private function redirect(): never
    {
        header(
            'Location: /reports/email'
        );


        exit;
    }
}
