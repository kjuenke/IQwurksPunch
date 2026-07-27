<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Container;
use App\Core\Flash;
use App\Services\AuditService;
use App\Services\AuthGuardService;
use App\Services\EmployeeService;
use App\Services\PunchCorrectionService;

final class PunchCorrectionController extends Controller
{
    private EmployeeService $employees;

    private PunchCorrectionService $corrections;

    private AuditService $audit;

    private AuthGuardService $authGuard;


    public function __construct()
    {
        $this->employees =
            Container::employeeService();


        $this->corrections =
            Container::punchCorrectionService();


        $this->audit =
            Container::auditService();


        $this->authGuard =
            new AuthGuardService(
                Container::userRepository()
            );
    }


    public function index(
        int $employeeId
    ): void
    {
        $this->requireSupervisor();


        $employee =
            $this->employees->find(
                $employeeId
            );


        if (!$employee) {

            Flash::error(
                'Employee not found.'
            );


            $this->redirectToEmployees();
        }


        $this->render(
            'employees/punches/index.twig',
            [
                'title' =>
                    'Manage Employee Punches',

                'activeMenu' =>
                    'employees',

                'employee' =>
                    $employee,

                'punches' =>
                    $this->corrections
                        ->punchesForEmployee(
                            $employeeId
                        ),

                'history' =>
                    $this->corrections
                        ->historyForEmployee(
                            $employeeId
                        ),

                'punchTypes' =>
                    $this->corrections
                        ->punchTypes(),

                'companyTimezone' =>
                    date_default_timezone_get()
            ]
        );
    }


    public function create(
        int $employeeId
    ): void
    {
        $this->requireSupervisor();


        $employee =
            $this->employees->find(
                $employeeId
            );


        if (!$employee) {

            Flash::error(
                'Employee not found.'
            );


            $this->redirectToEmployees();
        }


        $this->renderCreateForm(
            $employee,
            [
                'punch_time' =>
                    date(
                        'Y-m-d\TH:i'
                    ),

                'punch_type' =>
                    'clock_in',

                'notes' =>
                    '',

                'reason' =>
                    ''
            ]
        );
    }


    public function store(
        int $employeeId
    ): void
    {
        $userId =
            $this->requireSupervisor();


        $employee =
            $this->employees->find(
                $employeeId
            );


        if (!$employee) {

            Flash::error(
                'Employee not found.'
            );


            $this->redirectToEmployees();
        }


        $result =
            $this->corrections->create(
                $employeeId,
                $_POST,
                $userId
            );


        if (!$result['success']) {

            $this->renderCreateForm(
                $employee,
                $_POST,
                $result['errors']
                ??
                []
            );


            return;
        }


        $punchId =
            (int)(
                $result['punch_id']
                ??
                0
            );


        $this->audit->log(
            'punch.created_manually',
            $this->auditDetails(
                [
                    'punch_id' =>
                        $punchId,

                    'employee_id' =>
                        $employeeId,

                    'employee_number' =>
                        $employee['employee_number']
                        ??
                        '',

                    'employee_name' =>
                        $this->employeeName(
                            $employee
                        ),

                    'punch_time_local' =>
                        $_POST['punch_time']
                        ??
                        '',

                    'punch_type' =>
                        $_POST['punch_type']
                        ??
                        '',

                    'reason' =>
                        $_POST['reason']
                        ??
                        ''
                ]
            ),
            $userId
        );


        Flash::success(
            'Punch added successfully.'
        );


        $this->redirectToEmployeePunches(
            $employeeId
        );
    }


    public function edit(
        int $punchId
    ): void
    {
        $this->requireSupervisor();


        $punch =
            $this->corrections->find(
                $punchId
            );


        if (!$punch) {

            Flash::error(
                'Punch record not found.'
            );


            $this->redirectToEmployees();
        }


        $employee =
            $this->employees->find(
                (int)$punch['employee_id']
            );


        if (!$employee) {

            Flash::error(
                'Employee not found.'
            );


            $this->redirectToEmployees();
        }


        $this->renderEditForm(
            $employee,
            $punch,
            [
                'punch_time' =>
                    $punch['punch_time_input']
                    ??
                    '',

                'punch_type' =>
                    $punch['punch_type']
                    ??
                    '',

                'notes' =>
                    $punch['notes']
                    ??
                    '',

                'reason' =>
                    ''
            ]
        );
    }


    public function update(
        int $punchId
    ): void
    {
        $userId =
            $this->requireSupervisor();


        $existing =
            $this->corrections->find(
                $punchId
            );


        if (!$existing) {

            Flash::error(
                'Punch record not found.'
            );


            $this->redirectToEmployees();
        }


        $employeeId =
            (int)$existing['employee_id'];


        $employee =
            $this->employees->find(
                $employeeId
            );


        if (!$employee) {

            Flash::error(
                'Employee not found.'
            );


            $this->redirectToEmployees();
        }


        $result =
            $this->corrections->update(
                $punchId,
                $_POST,
                $userId
            );


        if (!$result['success']) {

            $this->renderEditForm(
                $employee,
                $existing,
                $_POST,
                $result['errors']
                ??
                []
            );


            return;
        }


        $this->audit->log(
            'punch.updated_manually',
            $this->auditDetails(
                [
                    'punch_id' =>
                        $punchId,

                    'employee_id' =>
                        $employeeId,

                    'employee_number' =>
                        $employee['employee_number']
                        ??
                        '',

                    'employee_name' =>
                        $this->employeeName(
                            $employee
                        ),

                    'old_punch_time_utc' =>
                        $existing['punch_time']
                        ??
                        '',

                    'old_punch_time_local' =>
                        $existing['punch_time_local']
                        ??
                        '',

                    'old_punch_type' =>
                        $existing['punch_type']
                        ??
                        '',

                    'new_punch_time_local' =>
                        $_POST['punch_time']
                        ??
                        '',

                    'new_punch_type' =>
                        $_POST['punch_type']
                        ??
                        '',

                    'reason' =>
                        $_POST['reason']
                        ??
                        ''
                ]
            ),
            $userId
        );


        Flash::success(
            'Punch updated successfully.'
        );


        $this->redirectToEmployeePunches(
            $employeeId
        );
    }


    public function delete(
        int $punchId
    ): void
    {
        $userId =
            $this->requireSupervisor();


        $existing =
            $this->corrections->find(
                $punchId
            );


        if (!$existing) {

            Flash::error(
                'Punch record not found.'
            );


            $this->redirectToEmployees();
        }


        $employeeId =
            (int)$existing['employee_id'];


        $confirmation =
            trim(
                (string)(
                    $_POST['confirmation']
                    ??
                    ''
                )
            );


        if ($confirmation !== 'DELETE') {

            Flash::error(
                'Punch deletion was not confirmed. Enter DELETE exactly.'
            );


            $this->redirectToEmployeePunches(
                $employeeId
            );
        }


        $reason =
            trim(
                (string)(
                    $_POST['reason']
                    ??
                    ''
                )
            );


        $result =
            $this->corrections->delete(
                $punchId,
                $reason,
                $userId
            );


        if (!$result['success']) {

            Flash::error(
                $this->errorMessage(
                    $result,
                    'Unable to delete the punch.'
                )
            );


            $this->redirectToEmployeePunches(
                $employeeId
            );
        }


        $this->audit->log(
            'punch.deleted_manually',
            $this->auditDetails(
                [
                    'punch_id' =>
                        $punchId,

                    'employee_id' =>
                        $employeeId,

                    'employee_number' =>
                        $existing['employee_number']
                        ??
                        '',

                    'employee_name' =>
                        trim(
                            (string)(
                                $existing['first_name']
                                ??
                                ''
                            )
                            .
                            ' '
                            .
                            (string)(
                                $existing['last_name']
                                ??
                                ''
                            )
                        ),

                    'deleted_punch_time_utc' =>
                        $existing['punch_time']
                        ??
                        '',

                    'deleted_punch_time_local' =>
                        $existing['punch_time_local']
                        ??
                        '',

                    'deleted_punch_type' =>
                        $existing['punch_type']
                        ??
                        '',

                    'reason' =>
                        $reason
                ]
            ),
            $userId
        );


        Flash::success(
            'Punch deleted successfully. The correction history was preserved.'
        );


        $this->redirectToEmployeePunches(
            $employeeId
        );
    }


    /**
     * @param array<string,mixed> $employee
     * @param array<string,mixed> $old
     * @param array<string,string> $errors
     */
    private function renderCreateForm(
        array $employee,
        array $old,
        array $errors = []
    ): void
    {
        $this->render(
            'employees/punches/create.twig',
            [
                'title' =>
                    'Add Employee Punch',

                'activeMenu' =>
                    'employees',

                'employee' =>
                    $employee,

                'punchTypes' =>
                    $this->corrections
                        ->punchTypes(),

                'companyTimezone' =>
                    date_default_timezone_get(),

                'old' =>
                    $old,

                'errors' =>
                    $errors
            ]
        );
    }


    /**
     * @param array<string,mixed> $employee
     * @param array<string,mixed> $punch
     * @param array<string,mixed> $old
     * @param array<string,string> $errors
     */
    private function renderEditForm(
        array $employee,
        array $punch,
        array $old,
        array $errors = []
    ): void
    {
        $this->render(
            'employees/punches/edit.twig',
            [
                'title' =>
                    'Edit Employee Punch',

                'activeMenu' =>
                    'employees',

                'employee' =>
                    $employee,

                'punch' =>
                    $punch,

                'punchTypes' =>
                    $this->corrections
                        ->punchTypes(),

                'companyTimezone' =>
                    date_default_timezone_get(),

                'old' =>
                    $old,

                'errors' =>
                    $errors
            ]
        );
    }


    private function requireSupervisor(): int
    {
        return
            $this->authGuard
                ->requireAuthorizedUserId(
                    (string)(
                        $_SERVER['REQUEST_URI']
                        ??
                        '/employees'
                    ),
                    (string)(
                        $_SERVER['REQUEST_METHOD']
                        ??
                        'GET'
                    )
                );
    }


    /**
     * @param array<string,mixed> $employee
     */
    private function employeeName(
        array $employee
    ): string
    {
        return trim(
            (string)(
                $employee['first_name']
                ??
                ''
            )
            .
            ' '
            .
            (string)(
                $employee['last_name']
                ??
                ''
            )
        );
    }


    /**
     * @param array<string,mixed> $details
     */
    private function auditDetails(
        array $details
    ): string
    {
        $encoded =
            json_encode(
                $details,
                JSON_UNESCAPED_SLASHES
                |
                JSON_UNESCAPED_UNICODE
            );


        return
            $encoded === false
                ? 'Punch correction details could not be encoded.'
                : $encoded;
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


    private function redirectToEmployeePunches(
        int $employeeId
    ): never
    {
        header(
            'Location: /employees/punches/'
            .
            $employeeId
        );


        exit;
    }


    private function redirectToEmployees(): never
    {
        header(
            'Location: /employees'
        );


        exit;
    }
}
