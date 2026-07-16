<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Container;
use App\Services\CompanySettingsService;
use App\Services\EmployeeService;
use App\Services\PayrollWorkspaceService;
use App\Services\PunchReportService;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

class PayrollController extends Controller
{
    private PunchReportService $reports;

    private PayrollWorkspaceService $workspace;

    private EmployeeService $employees;

    private CompanySettingsService $settings;


    public function __construct()
    {
        $this->reports =
            Container::punchReportService();


        $this->workspace =
            Container::payrollWorkspaceService();


        $this->employees =
            Container::employeeService();


        $this->settings =
            Container::companySettingsService();
    }


    public function daily(): void
    {
        $summary =
            $this->reports->dailySummary();


        $this->render(
            'reports/payroll.twig',
            [
                'title' =>
                    'Payroll Summary',

                'activeMenu' =>
                    'reports',

                'summary' =>
                    $summary
            ]
        );
    }


    public function weekly(): void
    {
        $summary =
            $this->reports->weeklySummary();


        $this->render(
            'reports/weekly-payroll.twig',
            [
                'title' =>
                    'Weekly Payroll Summary',

                'activeMenu' =>
                    'reports',

                'summary' =>
                    $summary
            ]
        );
    }


    public function workspace(): void
    {
        $company =
            $this->settings->get()
            ??
            [];


        $timezoneName =
            (string)(
                $company['timezone']
                ??
                'America/Los_Angeles'
            );


        if (
            !in_array(
                $timezoneName,
                timezone_identifiers_list(),
                true
            )
        ) {
            $timezoneName =
                'America/Los_Angeles';
        }


        $timezone =
            new DateTimeZone(
                $timezoneName
            );


        $today =
            new DateTimeImmutable(
                'now',
                $timezone
            );


        $defaultStart =
            $today
                ->modify(
                    'first day of this month'
                )
                ->format(
                    'Y-m-d'
                );


        $defaultEnd =
            $today->format(
                'Y-m-d'
            );


        $startDate =
            trim(
                (string)(
                    $_GET['start_date']
                    ??
                    $defaultStart
                )
            );


        $endDate =
            trim(
                (string)(
                    $_GET['end_date']
                    ??
                    $defaultEnd
                )
            );


        $employeeId =
            $this->employeeFilter(
                $_GET['employee_id']
                ??
                null
            );


        $department =
            $this->departmentFilter(
                $_GET['department']
                ??
                null
            );


        $errors = [];

        $summary = null;


        try {

            $summary =
                $this->workspace->summary(
                    $startDate,
                    $endDate,
                    $employeeId,
                    $department
                );

        } catch (Throwable $exception) {

            $errors[] =
                $exception->getMessage();
        }


        $employees =
            $this->employees->all();


        $departments =
            $this->departments(
                $employees
            );


        $this->render(
            'reports/payroll-workspace.twig',
            [
                'title' =>
                    'Payroll Workspace',

                'activeMenu' =>
                    'reports',

                'summary' =>
                    $summary,

                'errors' =>
                    $errors,

                'employees' =>
                    $employees,

                'departments' =>
                    $departments,

                'filters' => [
                    'start_date' =>
                        $startDate,

                    'end_date' =>
                        $endDate,

                    'employee_id' =>
                        $employeeId,

                    'department' =>
                        $department
                ],

                'timezone' =>
                    $timezoneName
            ]
        );
    }


    private function employeeFilter(
        mixed $value
    ): ?int
    {
        if (
            $value === null
            ||
            $value === ''
            ||
            $value === 'all'
        ) {
            return null;
        }


        $employeeId =
            filter_var(
                $value,
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' =>
                            1
                    ]
                ]
            );


        return $employeeId === false
            ? null
            : (int)$employeeId;
    }


    private function departmentFilter(
        mixed $value
    ): ?string
    {
        if (
            !is_string(
                $value
            )
        ) {
            return null;
        }


        $department =
            trim(
                $value
            );


        if (
            $department === ''
            ||
            $department === 'all'
        ) {
            return null;
        }


        return $department;
    }


    /**
     * @param array<int,array<string,mixed>> $employees
     *
     * @return array<int,string>
     */
    private function departments(
        array $employees
    ): array
    {
        $departments = [];


        foreach ($employees as $employee) {

            $department =
                trim(
                    (string)(
                        $employee['department']
                        ??
                        ''
                    )
                );


            if ($department === '') {

                continue;
            }


            $departments[
                strtolower(
                    $department
                )
            ] =
                $department;
        }


        natcasesort(
            $departments
        );


        return array_values(
            $departments
        );
    }
}
