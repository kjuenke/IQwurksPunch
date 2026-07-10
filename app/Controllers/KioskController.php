<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Container;
use App\Core\Flash;
use App\Services\EmployeeService;
use App\Services\PunchService;

class KioskController extends Controller
{
    private EmployeeService $employees;
    private PunchService $punches;


    public function __construct()
    {
        $this->employees = Container::employeeService();
        $this->punches = Container::punchService();
    }


    public function index(): void
    {
        $this->render(
            'kiosk/index.twig',
            [
                'title' => 'Employee Clock'
            ]
        );
    }


    public function authenticate(): void
    {
        $employeeNumber =
            trim($_POST['employee_number'] ?? '');


        $employee =
            $this->employees->findByEmployeeNumber(
                $employeeNumber
            );


        if (!$employee) {

            Flash::error(
                'Employee not found.'
            );

            header('Location: /kiosk');
            exit;
        }


        $_SESSION['kiosk_employee_id'] =
            $employee['id'];


        $_SESSION['kiosk_employee_name'] =
            $employee['first_name'] . ' ' .
            $employee['last_name'];


        $this->render(
            'kiosk/pin.twig',
            [
                'title' => 'Enter PIN',
                'employee' => $employee
            ]
        );
    }


    public function verifyPin(): void
    {
        $employeeId =
            $_SESSION['kiosk_employee_id'] ?? null;


        if (!$employeeId) {

            header('Location: /kiosk');
            exit;
        }


        $employee =
            $this->employees->find(
                (int)$employeeId
            );


        $pin =
            $_POST['pin'] ?? '';


        if (
            !$this->employees->verifyPin(
                $employee,
                $pin
            )
        ) {

            Flash::error(
                'Invalid PIN.'
            );

            header('Location: /kiosk');
            exit;
        }


        $_SESSION['kiosk_authenticated'] =
            true;


        $status =
            $this->punches->status(
                (int)$employee['id']
            );


        $this->render(
            'kiosk/actions.twig',
            [
                'title' => 'Clock Options',
                'employee' => $employee,
                'status' => $status
            ]
        );
    }


    public function punch(): void
    {
        if (
            empty($_SESSION['kiosk_authenticated'])
        ) {
            header('Location: /kiosk');
            exit;
        }


        $employeeId =
            (int)$_SESSION['kiosk_employee_id'];


        $type =
            $_POST['type'] ?? '';


        $result =
            $this->punches->punch(
                $employeeId,
                $type
            );


        if (!$result['success']) {

            Flash::error(
                'Unable to record punch.'
            );

        }
        else {

            Flash::success(
                'Punch recorded.'
            );

        }


        header('Location: /kiosk');
        exit;
    }
}
