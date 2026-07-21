<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Container;
use App\Core\Flash;
use App\Services\CompanySettingsService;
use App\Services\EmployeeService;
use App\Services\PunchService;

final class KioskController extends Controller
{
    private const DEFAULT_TIMEOUT_SECONDS = 60;

    private const MINIMUM_TIMEOUT_SECONDS = 15;

    private const MAXIMUM_TIMEOUT_SECONDS = 600;


    private EmployeeService $employees;

    private PunchService $punches;

    private CompanySettingsService $settings;


    public function __construct()
    {
        $this->employees =
            Container::employeeService();


        $this->punches =
            Container::punchService();


        $this->settings =
            Container::companySettingsService();
    }


    public function index(): void
    {
        if ($this->isActivityHeartbeatRequest()) {

            $this->handleActivityHeartbeat();


            return;
        }


        $resetReason =
            trim(
                (string)(
                    $_GET['reset']
                    ??
                    ''
                )
            );


        /*
         * Loading the starting kiosk page always ends any unfinished
         * employee interaction.
         */
        $this->clearKioskSession();


        $this->render(
            'kiosk/index.twig',
            [
                'title' =>
                    'Employee Clock',

                'kioskTimezone' =>
                    $this->kioskTimezone(),

                'kioskTimeoutSeconds' =>
                    $this->kioskTimeoutSeconds(),

                'kioskResetReason' =>
                    $resetReason
            ]
        );
    }


    public function authenticate(): void
    {
        /*
         * A new employee-number submission always replaces any stale kiosk
         * transaction left in the current browser session.
         */
        $this->clearKioskSession();


        $employeeNumber =
            trim(
                (string)(
                    $_POST['employee_number']
                    ??
                    ''
                )
            );


        $employee =
            $this->employees
                ->findByEmployeeNumber(
                    $employeeNumber
                );


        if (!$employee) {

            $this->redirectToKiosk(
                'employee-not-found'
            );
        }


        $_SESSION['kiosk_employee_id'] =
            (int)$employee['id'];


        $_SESSION['kiosk_employee_name'] =
            trim(
                (string)$employee['first_name']
                .
                ' '
                .
                (string)$employee['last_name']
            );


        $_SESSION['kiosk_authenticated'] =
            false;


        $this->touchKioskActivity();


        $this->render(
            'kiosk/pin.twig',
            [
                'title' =>
                    'Enter PIN',

                'employee' =>
                    $employee,

                'kioskTimezone' =>
                    $this->kioskTimezone(),

                'kioskTimeoutSeconds' =>
                    $this->kioskTimeoutSeconds()
            ]
        );
    }


    public function verifyPin(): void
    {
        $employeeId =
            $this->requireActiveKioskTransaction();


        $employee =
            $this->employees->find(
                $employeeId
            );


        if (!$employee) {

            $this->clearKioskSession();


            $this->redirectToKiosk(
                'employee-not-found'
            );
        }


        $pin =
            (string)(
                $_POST['pin']
                ??
                ''
            );


        if (
            !$this->employees->verifyPin(
                $employee,
                $pin
            )
        ) {

            $this->clearKioskSession();


            $this->redirectToKiosk(
                'invalid-pin'
            );
        }


        $_SESSION['kiosk_authenticated'] =
            true;


        $this->touchKioskActivity();


        $status =
            $this->punches->status(
                $employeeId
            );


        $this->render(
            'kiosk/actions.twig',
            [
                'title' =>
                    'Clock Options',

                'employee' =>
                    $employee,

                'status' =>
                    $status,

                'kioskTimezone' =>
                    $this->kioskTimezone(),

                'kioskTimeoutSeconds' =>
                    $this->kioskTimeoutSeconds()
            ]
        );
    }


    public function punch(): void
    {
        $employeeId =
            $this->requireActiveKioskTransaction(
                true
            );


        $type =
            (string)(
                $_POST['type']
                ??
                ''
            );


        $result =
            $this->punches->punch(
                $employeeId,
                $type
            );


        /*
         * The kiosk transaction ends immediately after the punch attempt,
         * whether the punch succeeds or fails.
         */
        $this->clearKioskSession();


        if (!$result['success']) {

            Flash::error(
                'Unable to record punch.'
            );

        } else {

            Flash::success(
                'Punch recorded.'
            );
        }


        header(
            'Location: /kiosk'
        );


        exit;
    }


    private function requireActiveKioskTransaction(
        bool $requireAuthentication = false
    ): int
    {
        $employeeId =
            (int)(
                $_SESSION['kiosk_employee_id']
                ??
                0
            );


        if (
            $employeeId <= 0
            ||
            $this->kioskTransactionExpired()
        ) {

            $this->clearKioskSession();


            $this->redirectToKiosk(
                'timeout'
            );
        }


        if (
            $requireAuthentication
            &&
            empty(
                $_SESSION['kiosk_authenticated']
            )
        ) {

            $this->clearKioskSession();


            $this->redirectToKiosk(
                'timeout'
            );
        }


        /*
         * Submitting a valid form is itself kiosk activity.
         */
        $this->touchKioskActivity();


        return $employeeId;
    }


    private function isActivityHeartbeatRequest(): bool
    {
        return
            (string)(
                $_GET['activity']
                ??
                ''
            )
            ===
            '1';
    }


    private function handleActivityHeartbeat(): void
    {
        header(
            'Cache-Control: no-store, no-cache, must-revalidate'
        );


        if (
            (int)(
                $_SESSION['kiosk_employee_id']
                ??
                0
            )
            <=
            0
        ) {

            http_response_code(
                204
            );


            return;
        }


        if ($this->kioskTransactionExpired()) {

            $this->clearKioskSession();


            http_response_code(
                409
            );


            return;
        }


        $this->touchKioskActivity();


        http_response_code(
            204
        );
    }


    private function kioskTransactionExpired(): bool
    {
        $lastActivity =
            (int)(
                $_SESSION['kiosk_last_activity']
                ??
                0
            );


        if ($lastActivity <= 0) {

            return true;
        }


        return
            (
                time()
                -
                $lastActivity
            )
            >=
            $this->kioskTimeoutSeconds();
    }


    private function touchKioskActivity(): void
    {
        $_SESSION['kiosk_last_activity'] =
            time();
    }


    private function clearKioskSession(): void
    {
        unset(
            $_SESSION['kiosk_employee_id'],
            $_SESSION['kiosk_employee_name'],
            $_SESSION['kiosk_authenticated'],
            $_SESSION['kiosk_last_activity']
        );
    }


    private function redirectToKiosk(
        string $reason = ''
    ): never
    {
        $location =
            '/kiosk';


        if ($reason !== '') {

            $location .=
                '?reset='
                .
                rawurlencode(
                    $reason
                );
        }


        header(
            'Location: '
            .
            $location
        );


        exit;
    }


    private function kioskTimeoutSeconds(): int
    {
        $settings =
            $this->settings->get();


        $timeout =
            (int)(
                $settings['kiosk_inactivity_timeout_seconds']
                ??
                self::DEFAULT_TIMEOUT_SECONDS
            );


        if (
            $timeout < self::MINIMUM_TIMEOUT_SECONDS
            ||
            $timeout > self::MAXIMUM_TIMEOUT_SECONDS
        ) {

            return self::DEFAULT_TIMEOUT_SECONDS;
        }


        return $timeout;
    }


    private function kioskTimezone(): string
    {
        $settings =
            $this->settings->get();


        $timezone =
            (string)(
                $settings['timezone']
                ??
                'America/Los_Angeles'
            );


        if (
            !in_array(
                $timezone,
                timezone_identifiers_list(),
                true
            )
        ) {

            return 'America/Los_Angeles';
        }


        return $timezone;
    }
}
