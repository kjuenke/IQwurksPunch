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
        $this->employees = Container::employeeService();
        $this->audit = Container::auditService();
    }


    public function index(): void
    {
        $this->render(
            'employees/index.twig',
            [
                'title' => 'Employees',
                'activeMenu' => 'employees',
                'employees' => $this->employees->all()
            ]
        );
    }


    public function create(): void
    {
        $this->render(
            'employees/create.twig',
            [
                'title' => 'Add Employee',
                'activeMenu' => 'employees'
            ]
        );
    }


    public function store(): void
    {
        $result = $this->employees->create($_POST);


        if (!$result['success']) {

            $this->render(
                'employees/create.twig',
                [
                    'title' => 'Add Employee',
                    'activeMenu' => 'employees',
                    'errors' => $result['errors'] ?? [],
                    'old' => $_POST
                ]
            );

            return;
        }


        $this->audit->log(
            'employee.created',
            'Created employee: ' .
            ($_POST['employee_number'] ?? '')
        );


        Flash::success(
            'Employee created successfully.'
        );


        header('Location: /employees');
        exit;
    }


    public function edit(int $id): void
    {
        $employee = $this->employees->find($id);


        if (!$employee) {

            Flash::error(
                'Employee not found.'
            );

            header('Location: /employees');
            exit;
        }


        $this->render(
            'employees/edit.twig',
            [
                'title' => 'Edit Employee',
                'activeMenu' => 'employees',
                'employee' => $employee
            ]
        );
    }


    public function update(int $id): void
    {
        $result = $this->employees->update(
            $id,
            $_POST
        );


        if (!$result['success']) {

            $employee = [
                'id' => $id,
                ...$_POST
            ];


            $this->render(
                'employees/edit.twig',
                [
                    'title' => 'Edit Employee',
                    'activeMenu' => 'employees',
                    'employee' => $employee,
                    'errors' => $result['errors'] ?? []
                ]
            );

            return;
        }


        $this->audit->log(
            'employee.updated',
            'Updated employee ID: ' . $id
        );


        Flash::success(
            'Employee updated successfully.'
        );


        header('Location: /employees');
        exit;
    }


    public function pin(int $id): void
    {
        $employee = $this->employees->find($id);


        if (!$employee) {

            Flash::error(
                'Employee not found.'
            );

            header('Location: /employees');
            exit;
        }


        $this->render(
            'employees/pin.twig',
            [
                'title' => 'Change Employee PIN',
                'activeMenu' => 'employees',
                'employee' => $employee
            ]
        );
    }


    public function updatePin(int $id): void
    {
        $result = $this->employees->updatePin(
            $id,
            $_POST['pin'] ?? '',
            $_POST['confirmation'] ?? ''
        );


        if (!$result['success']) {

            $employee = $this->employees->find($id);


            $this->render(
                'employees/pin.twig',
                [
                    'title' => 'Change Employee PIN',
                    'activeMenu' => 'employees',
                    'employee' => $employee,
                    'errors' => $result['errors'] ?? []
                ]
            );

            return;
        }


        $this->audit->log(
            'employee.pin_updated',
            'Updated PIN for employee ID: ' . $id
        );


        Flash::success(
            'Employee PIN updated successfully.'
        );


        header('Location: /employees');
        exit;
    }
}
