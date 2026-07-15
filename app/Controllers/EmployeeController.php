<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Container;
use App\Core\Flash;
use App\Services\AuditService;
use App\Services\EmployeeService;

class EmployeeController extends Controller
{
    private EmployeeService $employees;

    private AuditService $audit;


    public function __construct()
    {
        $this->employees =
            Container::employeeService();


        $this->audit =
            Container::auditService();
    }


    public function index(): void
    {
        $this->render(
            'employees/index.twig',
            [
                'title' =>
                    'Employees',

                'activeMenu' =>
                    'employees',

                'employees' =>
                    $this->employees->all()
            ]
        );
    }


    public function create(): void
    {
        $this->render(
            'employees/create.twig',
            [
                'title' =>
                    'Add Employee',

                'activeMenu' =>
                    'employees'
            ]
        );
    }


    public function store(): void
    {
        $result =
            $this->employees->create(
                $_POST
            );


        if (!$result['success']) {

            $this->render(
                'employees/create.twig',
                [
                    'title' =>
                        'Add Employee',

                    'activeMenu' =>
                        'employees',

                    'errors' =>
                        $result['errors']
                        ??
                        [],

                    'old' =>
                        $_POST
                ]
            );


            return;
        }


        $employeeId =
            (int)(
                $result['employee_id']
                ??
                0
            );


        $this->audit->log(
            'employee.created',
            'Created employee ID '
            .
            $employeeId
            .
            ' with employee number '
            .
            (
                $_POST['employee_number']
                ??
                ''
            )
        );


        Flash::success(
            'Employee created successfully.'
        );


        $this->redirectToIndex();
    }


    public function edit(
        int $id
    ): void
    {
        $employee =
            $this->employees->find(
                $id
            );


        if (!$employee) {

            Flash::error(
                'Employee not found.'
            );


            $this->redirectToIndex();
        }


        $this->render(
            'employees/edit.twig',
            [
                'title' =>
                    'Edit Employee',

                'activeMenu' =>
                    'employees',

                'employee' =>
                    $employee
            ]
        );
    }


    public function update(
        int $id
    ): void
    {
        $result =
            $this->employees->update(
                $id,
                $_POST
            );


        if (!$result['success']) {

            $employee = [
                'id' =>
                    $id,

                ...$_POST
            ];


            $existing =
                $this->employees->find(
                    $id
                );


            if ($existing) {

                $employee['punch_count'] =
                    $existing['punch_count']
                    ??
                    0;


                $employee['can_delete'] =
                    $existing['can_delete']
                    ??
                    false;
            }


            $this->render(
                'employees/edit.twig',
                [
                    'title' =>
                        'Edit Employee',

                    'activeMenu' =>
                        'employees',

                    'employee' =>
                        $employee,

                    'errors' =>
                        $result['errors']
                        ??
                        []
                ]
            );


            return;
        }


        $this->audit->log(
            'employee.updated',
            'Updated employee ID: '
            .
            $id
        );


        Flash::success(
            'Employee updated successfully.'
        );


        $this->redirectToIndex();
    }


    public function pin(
        int $id
    ): void
    {
        $employee =
            $this->employees->find(
                $id
            );


        if (!$employee) {

            Flash::error(
                'Employee not found.'
            );


            $this->redirectToIndex();
        }


        $this->render(
            'employees/pin.twig',
            [
                'title' =>
                    'Change Employee PIN',

                'activeMenu' =>
                    'employees',

                'employee' =>
                    $employee
            ]
        );
    }


    public function updatePin(
        int $id
    ): void
    {
        $result =
            $this->employees->updatePin(
                $id,
                $_POST['pin']
                ??
                '',
                $_POST['confirmation']
                ??
                ''
            );


        if (!$result['success']) {

            $employee =
                $this->employees->find(
                    $id
                );


            $this->render(
                'employees/pin.twig',
                [
                    'title' =>
                        'Change Employee PIN',

                    'activeMenu' =>
                        'employees',

                    'employee' =>
                        $employee,

                    'errors' =>
                        $result['errors']
                        ??
                        []
                ]
            );


            return;
        }


        $this->audit->log(
            'employee.pin_updated',
            'Updated PIN for employee ID: '
            .
            $id
        );


        Flash::success(
            'Employee PIN updated successfully.'
        );


        $this->redirectToIndex();
    }


    public function activate(): void
    {
        $id =
            $this->postedEmployeeId();


        if ($id <= 0) {

            Flash::error(
                'Invalid employee.'
            );


            $this->redirectToIndex();
        }


        $result =
            $this->employees->activate(
                $id
            );


        if ($result['success']) {

            $this->audit->log(
                'employee.activated',
                'Activated employee ID: '
                .
                $id
            );


            Flash::success(
                'Employee activated successfully.'
            );

        } else {

            Flash::error(
                $this->errorMessage(
                    $result,
                    'Unable to activate the employee.'
                )
            );
        }


        $this->redirectToIndex();
    }


    public function deactivate(): void
    {
        $id =
            $this->postedEmployeeId();


        if ($id <= 0) {

            Flash::error(
                'Invalid employee.'
            );


            $this->redirectToIndex();
        }


        $result =
            $this->employees->deactivate(
                $id
            );


        if ($result['success']) {

            $this->audit->log(
                'employee.deactivated',
                'Deactivated employee ID: '
                .
                $id
            );


            Flash::success(
                'Employee deactivated successfully.'
            );

        } else {

            Flash::error(
                $this->errorMessage(
                    $result,
                    'Unable to deactivate the employee.'
                )
            );
        }


        $this->redirectToIndex();
    }


    public function delete(): void
    {
        $id =
            $this->postedEmployeeId();


        if ($id <= 0) {

            Flash::error(
                'Invalid employee.'
            );


            $this->redirectToIndex();
        }


        $employee =
            $this->employees->find(
                $id
            );


        $result =
            $this->employees->delete(
                $id
            );


        if ($result['success']) {

            $this->audit->log(
                'employee.deleted',
                'Permanently deleted employee ID '
                .
                $id
                .
                (
                    $employee
                        ? ' ('
                            .
                            $employee['employee_number']
                            .
                            ' - '
                            .
                            $employee['first_name']
                            .
                            ' '
                            .
                            $employee['last_name']
                            .
                            ')'
                        : ''
                )
            );


            Flash::success(
                'Employee permanently deleted.'
            );

        } else {

            $this->audit->log(
                'employee.delete_blocked',
                'Blocked permanent deletion of employee ID '
                .
                $id
                .
                '.'
            );


            Flash::error(
                $this->errorMessage(
                    $result,
                    'Unable to delete the employee.'
                )
            );
        }


        $this->redirectToIndex();
    }


    private function postedEmployeeId(): int
    {
        return (int)(
            $_POST['id']
            ??
            0
        );
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


    private function redirectToIndex(): never
    {
        header(
            'Location: /employees'
        );


        exit;
    }
}
