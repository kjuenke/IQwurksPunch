<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Container;
use App\Core\Flash;
use App\Services\NotificationRecipientService;

class NotificationRecipientController extends Controller
{
    private NotificationRecipientService $recipients;


    public function __construct()
    {
        $this->recipients =
            Container::notificationRecipientService();
    }


    public function index(): void
    {
        $this->render(
            'notifications/index.twig',
            [
                'title' =>
                    'Notification Center',

                'activeMenu' =>
                    'reports',

                'recipients' =>
                    $this->recipients->all(),

                'dailyRecipientCount' =>
                    $this->recipients->countActiveFor(
                        'daily_payroll'
                    ),

                'weeklyRecipientCount' =>
                    $this->recipients->countActiveFor(
                        'weekly_payroll'
                    ),

                'exceptionRecipientCount' =>
                    $this->recipients->countActiveFor(
                        'exception_reports'
                    ),

                'approvalRecipientCount' =>
                    $this->recipients->countActiveFor(
                        'approval_notifications'
                    )
            ]
        );
    }


    public function create(): void
    {
        $result =
            $this->recipients->create(
                $_POST
            );


        if ($result['success']) {

            Flash::success(
                'Notification recipient added successfully.'
            );

        } else {

            Flash::error(
                $this->errorMessage(
                    $result,
                    'Unable to add the notification recipient.'
                )
            );
        }


        $this->redirect();
    }


    public function update(): void
    {
        $id =
            (int)(
                $_POST['id']
                ??
                0
            );


        if ($id <= 0) {

            Flash::error(
                'Invalid notification recipient.'
            );


            $this->redirect();
        }


        $result =
            $this->recipients->update(
                $id,
                $_POST
            );


        if ($result['success']) {

            Flash::success(
                'Notification recipient updated successfully.'
            );

        } else {

            Flash::error(
                $this->errorMessage(
                    $result,
                    'Unable to update the notification recipient.'
                )
            );
        }


        $this->redirect();
    }


    public function activate(): void
    {
        $id =
            (int)(
                $_POST['id']
                ??
                0
            );


        $result =
            $this->recipients->activate(
                $id
            );


        if ($result['success']) {

            Flash::success(
                'Notification recipient activated.'
            );

        } else {

            Flash::error(
                $this->errorMessage(
                    $result,
                    'Unable to activate the notification recipient.'
                )
            );
        }


        $this->redirect();
    }


    public function deactivate(): void
    {
        $id =
            (int)(
                $_POST['id']
                ??
                0
            );


        $result =
            $this->recipients->deactivate(
                $id
            );


        if ($result['success']) {

            Flash::success(
                'Notification recipient deactivated.'
            );

        } else {

            Flash::error(
                $this->errorMessage(
                    $result,
                    'Unable to deactivate the notification recipient.'
                )
            );
        }


        $this->redirect();
    }


    public function delete(): void
    {
        $id =
            (int)(
                $_POST['id']
                ??
                0
            );


        $result =
            $this->recipients->delete(
                $id
            );


        if ($result['success']) {

            Flash::success(
                'Notification recipient deleted.'
            );

        } else {

            Flash::error(
                $this->errorMessage(
                    $result,
                    'Unable to delete the notification recipient.'
                )
            );
        }


        $this->redirect();
    }


    /**
     * @param array<string,mixed> $result
     */
    private function errorMessage(
        array $result,
        string $default
    ): string
    {
        $errors =
            $result['errors']
            ??
            [];


        if (!is_array($errors)) {

            return $default;
        }


        foreach ($errors as $error) {

            if (
                is_string(
                    $error
                )
                &&
                $error !== ''
            ) {
                return $error;
            }
        }


        return $default;
    }


    private function redirect(): never
    {
        header(
            'Location: /admin/notifications'
        );


        exit;
    }
}
