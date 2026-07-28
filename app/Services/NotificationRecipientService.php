<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\NotificationRecipientRepository;

class NotificationRecipientService
{
    private NotificationRecipientRepository $recipients;


    public function __construct(
        NotificationRecipientRepository $recipients
    )
    {
        $this->recipients =
            $recipients;
    }


    /**
     * @return array<int,array<string,mixed>>
     */
    public function all(): array
    {
        return $this->recipients->all();
    }


    public function find(
        int $id
    ): ?array
    {
        return $this->recipients->find(
            $id
        );
    }


    /**
     * @return array<string,mixed>
     */
    public function create(
        array $data
    ): array
    {
        $validated =
            $this->validate(
                $data
            );


        if (!$validated['success']) {

            return $validated;
        }


        $existing =
            $this->recipients->findByEmail(
                $validated['data']['email']
            );


        if ($existing) {

            return [
                'success' =>
                    false,

                'errors' => [
                    'email' =>
                        'That email address is already configured.'
                ]
            ];
        }


        $id =
            $this->recipients->create(
                $validated['data']
            );


        return [
            'success' =>
                $id > 0,

            'id' =>
                $id,

            'errors' =>
                []
        ];
    }


    /**
     * @return array<string,mixed>
     */
    public function update(
        int $id,
        array $data
    ): array
    {
        $recipient =
            $this->recipients->find(
                $id
            );


        if (!$recipient) {

            return [
                'success' =>
                    false,

                'errors' => [
                    'recipient' =>
                        'Notification recipient was not found.'
                ]
            ];
        }


        $validated =
            $this->validate(
                $data
            );


        if (!$validated['success']) {

            return $validated;
        }


        $existing =
            $this->recipients->findByEmail(
                $validated['data']['email']
            );


        if (
            $existing
            &&
            (int)$existing['id'] !== $id
        ) {
            return [
                'success' =>
                    false,

                'errors' => [
                    'email' =>
                        'That email address is already configured.'
                ]
            ];
        }


        $updated =
            $this->recipients->update(
                $id,
                $validated['data']
            );


        return [
            'success' =>
                $updated,

            'errors' =>
                $updated
                    ? []
                    : [
                        'recipient' =>
                            'Unable to update the notification recipient.'
                    ]
        ];
    }


    /**
     * @return array<string,mixed>
     */
    public function activate(
        int $id
    ): array
    {
        return $this->setActive(
            $id,
            true
        );
    }


    /**
     * @return array<string,mixed>
     */
    public function deactivate(
        int $id
    ): array
    {
        return $this->setActive(
            $id,
            false
        );
    }


    /**
     * @return array<string,mixed>
     */
    public function delete(
        int $id
    ): array
    {
        $recipient =
            $this->recipients->find(
                $id
            );


        if (!$recipient) {

            return [
                'success' =>
                    false,

                'errors' => [
                    'recipient' =>
                        'Notification recipient was not found.'
                ]
            ];
        }


        $deleted =
            $this->recipients->delete(
                $id
            );


        return [
            'success' =>
                $deleted,

            'errors' =>
                $deleted
                    ? []
                    : [
                        'recipient' =>
                            'Unable to delete the notification recipient.'
                    ]
        ];
    }


    /**
     * @return array<int,string>
     */
    public function activeEmailsFor(
        string $notificationType
    ): array
    {
        return $this->recipients->activeEmailsFor(
            $notificationType
        );
    }


    public function countActiveFor(
        string $notificationType
    ): int
    {
        return $this->recipients->countActiveFor(
            $notificationType
        );
    }


    /**
     * @return array<string,mixed>
     */
    private function setActive(
        int $id,
        bool $active
    ): array
    {
        $recipient =
            $this->recipients->find(
                $id
            );


        if (!$recipient) {

            return [
                'success' =>
                    false,

                'errors' => [
                    'recipient' =>
                        'Notification recipient was not found.'
                ]
            ];
        }


        $updated =
            $this->recipients->setActive(
                $id,
                $active
            );


        return [
            'success' =>
                $updated,

            'errors' =>
                $updated
                    ? []
                    : [
                        'recipient' =>
                            'Unable to update the recipient status.'
                    ]
        ];
    }


    /**
     * @return array<string,mixed>
     */
    private function validate(
        array $data
    ): array
    {
        $errors = [];


        $name =
            trim(
                (string)(
                    $data['name']
                    ??
                    ''
                )
            );


        $email =
            strtolower(
                trim(
                    (string)(
                        $data['email']
                        ??
                        ''
                    )
                )
            );


        if ($email === '') {

            $errors['email'] =
                'Email address is required.';

        } elseif (
            !filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {

            $errors['email'] =
                'Enter a valid email address.';
        }


        if (
            strlen(
                $name
            ) > 150
        ) {

            $errors['name'] =
                'Name cannot exceed 150 characters.';
        }


        if (
            strlen(
                $email
            ) > 254
        ) {

            $errors['email'] =
                'Email address cannot exceed 254 characters.';
        }


        $dailyPayroll =
            isset(
                $data['daily_payroll']
            )
                ? 1
                : 0;


        $weeklyPayroll =
            isset(
                $data['weekly_payroll']
            )
                ? 1
                : 0;


        $exceptionReports =
            isset(
                $data['exception_reports']
            )
                ? 1
                : 0;


        $approvalNotifications =
            isset(
                $data['approval_notifications']
            )
                ? 1
                : 0;


        $operationalFailures =
            isset(
                $data['operational_failures']
            )
                ? 1
                : 0;


        $active =
            isset(
                $data['active']
            )
                ? 1
                : 0;


        if (
            $dailyPayroll === 0
            &&
            $weeklyPayroll === 0
            &&
            $exceptionReports === 0
            &&
            $approvalNotifications === 0
            &&
            $operationalFailures === 0
        ) {

            $errors['subscriptions'] =
                'Select at least one notification type.';
        }


        if (!empty($errors)) {

            return [
                'success' =>
                    false,

                'errors' =>
                    $errors
            ];
        }


        return [
            'success' =>
                true,

            'errors' =>
                [],

            'data' => [
                'name' =>
                    $name,

                'email' =>
                    $email,

                'daily_payroll' =>
                    $dailyPayroll,

                'weekly_payroll' =>
                    $weeklyPayroll,

                'exception_reports' =>
                    $exceptionReports,

                'approval_notifications' =>
                    $approvalNotifications,

                'operational_failures' =>
                    $operationalFailures,

                'active' =>
                    $active
            ]
        ];
    }
}
