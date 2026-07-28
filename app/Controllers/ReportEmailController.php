<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Container;
use App\Core\Flash;
use App\Repositories\ReportDeliveryScheduleRepository;
use App\Services\NotificationRecipientService;
use App\Services\ReportDeliveryScheduleService;
use App\Services\ReportEmailService;
use App\Services\WeeklyPayrollEmailService;
use Throwable;

class ReportEmailController extends Controller
{
    private ReportEmailService $reports;

    private WeeklyPayrollEmailService $weeklyReports;

    private ReportDeliveryScheduleService $schedules;

    private NotificationRecipientService $recipients;


    public function __construct()
    {
        $this->reports =
            Container::reportEmailService();


        $this->weeklyReports =
            new WeeklyPayrollEmailService();


        $this->schedules =
            new ReportDeliveryScheduleService(
                new ReportDeliveryScheduleRepository(
                    Container::db()
                )
            );


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


        $dailySchedule =
            $this->schedules->get(
                ReportDeliveryScheduleService::DAILY_PAYROLL
            )
            ??
            [];


        $weeklySchedule =
            $this->schedules->get(
                ReportDeliveryScheduleService::WEEKLY_PAYROLL
            )
            ??
            [];


        $this->render(
            'reports/email.twig',
            [
                'title' =>
                    'Email Reports',

                'activeMenu' =>
                    'reports',

                /*
                 * Retained until the view is updated in the next step.
                 */
                'schedule' =>
                    $dailySchedule,

                'dailySchedule' =>
                    $dailySchedule,

                'weeklySchedule' =>
                    $weeklySchedule,

                'weeklyDayOptions' =>
                    $this->schedules->dayOptions(),

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
        $reportType =
            trim(
                (string)(
                    $_POST['report_type']
                    ??
                    ReportDeliveryScheduleService::DAILY_PAYROLL
                )
            );


        try {

            if (
                $reportType
                ===
                ReportDeliveryScheduleService::DAILY_PAYROLL
            ) {
                $result =
                    $this->schedules->updateDaily(
                        $_POST
                    );


                $successMessage =
                    'Automatic daily payroll schedule saved successfully.';

            } elseif (
                $reportType
                ===
                ReportDeliveryScheduleService::WEEKLY_PAYROLL
            ) {
                $result =
                    $this->schedules->updateWeekly(
                        $_POST
                    );


                $successMessage =
                    'Automatic weekly payroll schedule saved successfully.';

            } else {

                Flash::error(
                    'Unsupported report schedule type.'
                );


                $this->redirect();
            }


            if ($result['success']) {

                Flash::success(
                    $successMessage
                );

            } else {

                $message =
                    $result['errors']['send_time']
                    ??
                    $result['errors']['send_day_of_week']
                    ??
                    $result['errors']['schedule']
                    ??
                    'Unable to save the report schedule.';


                Flash::error(
                    $message
                );
            }

        } catch (Throwable $exception) {

            Flash::error(
                'Unable to save the report schedule: '
                .
                $exception->getMessage()
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
