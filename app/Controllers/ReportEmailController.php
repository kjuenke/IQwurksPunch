<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Container;
use App\Core\Flash;
use App\Services\NotificationRecipientService;
use App\Services\ReportEmailService;
use App\Services\ReportScheduleService;
use App\Services\WeeklyPayrollEmailService;
use Throwable;

class ReportEmailController extends Controller
{
    private ReportEmailService $reports;

    private WeeklyPayrollEmailService $weeklyReports;

    private ReportScheduleService $schedule;

    private NotificationRecipientService $recipients;


    public function __construct()
    {
        $this->reports =
            Container::reportEmailService();


        $this->weeklyReports =
            new WeeklyPayrollEmailService();


        $this->schedule =
            Container::reportScheduleService();


        $this->recipients =
            Container::notificationRecipientService();
    }


    public function index(): void
    {
        $dailyRecipientCount =
            $this->recipients->countActiveFor(
                'daily_payroll'
            );


        $weeklyRecipientCount =
            $this->recipients->countActiveFor(
                'weekly_payroll'
            );


        $this->render(
            'reports/email.twig',
            [
                'title' =>
                    'Email Reports',

                'activeMenu' =>
                    'reports',

                'schedule' =>
                    $this->schedule->get(),

                /*
                 * Retained temporarily for compatibility with the current
                 * daily-report view.
                 */
                'recipientCount' =>
                    $dailyRecipientCount,

                'dailyRecipientCount' =>
                    $dailyRecipientCount,

                'weeklyRecipientCount' =>
                    $weeklyRecipientCount
            ]
        );
    }


    public function sendDaily(): void
    {
        $reportType =
            trim(
                (string)(
                    $_POST['report_type']
                    ??
                    'daily'
                )
            );


        if ($reportType === 'weekly') {

            $this->sendWeekly();


            return;
        }


        if ($reportType !== 'daily') {

            Flash::error(
                'Unsupported payroll report type.'
            );


            $this->redirect();
        }


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


    private function sendWeekly(): never
    {
        $referenceDate =
            trim(
                (string)(
                    $_POST['reference_date']
                    ??
                    ''
                )
            );


        try {

            $sent =
                $this->weeklyReports
                    ->sendWeeklyPayrollReport(
                        $referenceDate === ''
                            ? null
                            : $referenceDate
                    );


            if ($sent) {

                Flash::success(
                    'Weekly payroll report sent successfully.'
                );

            } else {

                Flash::error(
                    'Weekly payroll report failed to send.'
                );
            }

        } catch (Throwable $exception) {

            Flash::error(
                'Weekly report email failed: '
                .
                $exception->getMessage()
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
