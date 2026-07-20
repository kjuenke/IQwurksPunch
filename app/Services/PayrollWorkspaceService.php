<?php
declare(strict_types=1);

namespace App\Services;

use App\Payroll\PayrollCalculator;
use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class PayrollWorkspaceService
{
    private PunchReportService $reports;

    private CompanySettingsService $settings;

    private PayrollCalculator $payroll;

    private LaborRulesService $laborRules;


    public function __construct(
        PunchReportService $reports,
        CompanySettingsService $settings,
        ?PayrollCalculator $payroll = null,
        ?LaborRulesService $laborRules = null
    )
    {
        $this->reports =
            $reports;


        $this->settings =
            $settings;


        $this->payroll =
            $payroll
            ??
            new PayrollCalculator();


        $this->laborRules =
            $laborRules
            ??
            \App\Core\Container::laborRulesService();
    }


    /**
     * @return array<string,mixed>
     */
    public function summary(
        string $startDate,
        string $endDate,
        ?int $employeeId = null,
        ?string $department = null
    ): array
    {
        $company =
            $this->companySettings();


        $timezone =
            new DateTimeZone(
                $company['timezone']
            );


        $start =
            $this->parseDate(
                $startDate,
                $timezone,
                'start date'
            );


        $end =
            $this->parseDate(
                $endDate,
                $timezone,
                'end date'
            );


        if ($end < $start) {

            throw new InvalidArgumentException(
                'The end date cannot be earlier than the start date.'
            );
        }


        $dayCount =
            (int)$start
                ->diff(
                    $end
                )
                ->format(
                    '%a'
                )
            +
            1;


        if ($dayCount > 366) {

            throw new InvalidArgumentException(
                'Payroll Workspace reports are limited to 366 days.'
            );
        }


        $normalizedDepartment =
            $this->normalizeDepartment(
                $department
            );


        $employees = [];


        $period =
            new DatePeriod(
                $start,
                new DateInterval(
                    'P1D'
                ),
                $end->modify(
                    '+1 day'
                )
            );


        foreach ($period as $day) {

            $date =
                $day->format(
                    'Y-m-d'
                );


            $dailySummary =
                $this->reports->dailySummary(
                    $date
                );


            foreach ($dailySummary as $employee) {

                if (
                    $employeeId !== null
                    &&
                    (int)$employee['employee_id']
                    !==
                    $employeeId
                ) {
                    continue;
                }


                if (
                    $normalizedDepartment !== null
                    &&
                    strcasecmp(
                        trim(
                            (string)(
                                $employee['department']
                                ??
                                ''
                            )
                        ),
                        $normalizedDepartment
                    )
                    !==
                    0
                ) {
                    continue;
                }


                $id =
                    (int)$employee['employee_id'];


                if (!isset($employees[$id])) {

                    $employees[$id] = [
                        'employee_id' =>
                            $id,

                        'employee_number' =>
                            (string)(
                                $employee['employee_number']
                                ??
                                ''
                            ),

                        'name' =>
                            (string)(
                                $employee['name']
                                ??
                                ''
                            ),

                        'department' =>
                            (string)(
                                $employee['department']
                                ??
                                ''
                            ),

                        'days' =>
                            []
                    ];
                }


                $employees[$id]['days'][] =
                    $this->dailyResult(
                        $employee
                    );
            }
        }


        $employeeResults = [];


        foreach ($employees as $employee) {

            $employeeResults[] =
                $this->employeeResult(
                    $employee,
                    $company
                );
        }


        usort(
            $employeeResults,
            static function (
                array $left,
                array $right
            ): int {
                return strcasecmp(
                    (string)$left['name'],
                    (string)$right['name']
                );
            }
        );


        return [
            'start_date' =>
                $start->format(
                    'Y-m-d'
                ),

            'end_date' =>
                $end->format(
                    'Y-m-d'
                ),

            'day_count' =>
                $dayCount,

            'timezone' =>
                $company['timezone'],

            'filters' => [
                'employee_id' =>
                    $employeeId,

                'department' =>
                    $normalizedDepartment
            ],

            'employees' =>
                $employeeResults,

            'totals' =>
                $this->reportTotals(
                    $employeeResults
                ),

            'issue_count' =>
                array_sum(
                    array_map(
                        static fn (
                            array $employee
                        ): int =>
                            count(
                                $employee['errors']
                                ??
                                []
                            ),
                        $employeeResults
                    )
                )
        ];
    }


    /**
     * @param array<string,mixed> $employee
     *
     * @return array<string,mixed>
     */
    private function dailyResult(
        array $employee
    ): array
    {
        return [
            'date' =>
                (string)(
                    $employee['date']
                    ??
                    ''
                ),

            'complete' =>
                (bool)(
                    $employee['complete']
                    ??
                    false
                ),

            'errors' =>
                is_array(
                    $employee['errors']
                    ??
                    null
                )
                    ? $employee['errors']
                    : [],

            'work_periods' =>
                $employee['work_periods']
                ??
                [],

            'meal_periods' =>
                $employee['meal_periods']
                ??
                [],

            'break_periods' =>
                $employee['break_periods']
                ??
                [],

            'gross_hours' =>
                (float)(
                    $employee['gross_hours']
                    ??
                    0
                ),

            'recorded_meal_minutes' =>
                (int)(
                    $employee['recorded_meal_minutes']
                    ??
                    0
                ),

            'automatic_meal_deduction_minutes' =>
                (int)(
                    $employee['automatic_meal_deduction_minutes']
                    ??
                    0
                ),

            'recorded_break_minutes' =>
                (int)(
                    $employee['recorded_break_minutes']
                    ??
                    0
                ),

            'paid_break_minutes' =>
                (int)(
                    $employee['paid_break_minutes']
                    ??
                    0
                ),

            'unpaid_break_minutes' =>
                (int)(
                    $employee['unpaid_break_minutes']
                    ??
                    0
                ),

            'total_hours' =>
                (float)(
                    $employee['total_hours']
                    ??
                    0
                ),

            'regular_hours' =>
                (float)(
                    $employee['regular_hours']
                    ??
                    0
                ),

            'daily_overtime_hours' =>
                (float)(
                    $employee['daily_overtime_hours']
                    ??
                    0
                ),

            'double_time_hours' =>
                (float)(
                    $employee['double_time_hours']
                    ??
                    0
                ),

            'overtime_hours' =>
                (float)(
                    $employee['overtime_hours']
                    ??
                    0
                )
        ];
    }


    /**
     * @param array<string,mixed> $employee
     * @param array<string,mixed> $company
     *
     * @return array<string,mixed>
     */
    private function employeeResult(
        array $employee,
        array $company
    ): array
    {
        $timezone =
            new DateTimeZone(
                $company['timezone']
            );


        $weeks = [];


        foreach ($employee['days'] as $day) {

            $date =
                new DateTimeImmutable(
                    $day['date']
                    .
                    ' 00:00:00',
                    $timezone
                );


            $weekStart =
                $this->weekStart(
                    $date,
                    (string)(
                        $company['workweek_start_day']
                        ??
                        'monday'
                    )
                );


            $weekKey =
                $weekStart->format(
                    'Y-m-d'
                );


            $weeks[$weekKey][] =
                $day;
        }


        $weekResults = [];


        foreach ($weeks as $weekStart => $days) {

            $weekCalculation =
                $this->payroll->calculateWeek(
                    $days,
                    $company
                );


            $weekResults[] = [
                'week_start' =>
                    $weekStart,

                'week_end' =>
                    (
                        new DateTimeImmutable(
                            $weekStart,
                            $timezone
                        )
                    )
                        ->modify(
                            '+6 days'
                        )
                        ->format(
                            'Y-m-d'
                        ),

                ...$weekCalculation
            ];
        }


        $errors = [];


        foreach ($employee['days'] as $day) {

            foreach ($day['errors'] as $error) {

                $errors[] =
                    $day['date']
                    .
                    ': '
                    .
                    (string)$error;
            }
        }


        $totals =
            $this->weekTotals(
                $weekResults
            );


        return [
            'employee_id' =>
                $employee['employee_id'],

            'employee_number' =>
                $employee['employee_number'],

            'name' =>
                $employee['name'],

            'department' =>
                $employee['department'],

            'complete' =>
                empty(
                    $errors
                ),

            'errors' =>
                $errors,

            'days' =>
                $employee['days'],

            'weeks' =>
                $weekResults,

            ...$totals
        ];
    }


    /**
     * @param array<int,array<string,mixed>> $weeks
     *
     * @return array<string,float>
     */
    private function weekTotals(
        array $weeks
    ): array
    {
        $totals = [
            'gross_hours' =>
                0.0,

            'regular_hours' =>
                0.0,

            'daily_overtime_hours' =>
                0.0,

            'weekly_overtime_hours' =>
                0.0,

            'double_time_hours' =>
                0.0,

            'overtime_hours' =>
                0.0,

            'premium_hours' =>
                0.0,

            'total_hours' =>
                0.0
        ];


        foreach ($weeks as $week) {

            foreach (
                array_keys(
                    $totals
                )
                as $field
            ) {
                $totals[$field] +=
                    (float)(
                        $week[$field]
                        ??
                        0
                    );
            }
        }


        foreach ($totals as $field => $value) {

            $totals[$field] =
                round(
                    $value,
                    2
                );
        }


        return $totals;
    }


    /**
     * @param array<int,array<string,mixed>> $employees
     *
     * @return array<string,float|int>
     */
    private function reportTotals(
        array $employees
    ): array
    {
        $totals = [
            'employee_count' =>
                count(
                    $employees
                ),

            'gross_hours' =>
                0.0,

            'regular_hours' =>
                0.0,

            'daily_overtime_hours' =>
                0.0,

            'weekly_overtime_hours' =>
                0.0,

            'double_time_hours' =>
                0.0,

            'overtime_hours' =>
                0.0,

            'premium_hours' =>
                0.0,

            'total_hours' =>
                0.0
        ];


        foreach ($employees as $employee) {

            foreach (
                [
                    'gross_hours',
                    'regular_hours',
                    'daily_overtime_hours',
                    'weekly_overtime_hours',
                    'double_time_hours',
                    'overtime_hours',
                    'premium_hours',
                    'total_hours'
                ]
                as $field
            ) {
                $totals[$field] +=
                    (float)(
                        $employee[$field]
                        ??
                        0
                    );
            }
        }


        foreach (
            [
                'gross_hours',
                'regular_hours',
                'daily_overtime_hours',
                'weekly_overtime_hours',
                'double_time_hours',
                'overtime_hours',
                'premium_hours',
                'total_hours'
            ]
            as $field
        ) {
            $totals[$field] =
                round(
                    (float)$totals[$field],
                    2
                );
        }


        return $totals;
    }


    private function weekStart(
        DateTimeImmutable $date,
        string $startDay
    ): DateTimeImmutable
    {
        $normalized =
            strtolower(
                trim(
                    $startDay
                )
            );


        if (
            !in_array(
                $normalized,
                [
                    'sunday',
                    'monday'
                ],
                true
            )
        ) {
            $normalized =
                'monday';
        }


        if (
            strtolower(
                $date->format(
                    'l'
                )
            )
            ===
            $normalized
        ) {
            return $date->setTime(
                0,
                0
            );
        }


        return $date
            ->modify(
                'last '
                .
                $normalized
            )
            ->setTime(
                0,
                0
            );
    }


    private function parseDate(
        string $date,
        DateTimeZone $timezone,
        string $label
    ): DateTimeImmutable
    {
        $parsed =
            DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $date,
                $timezone
            );


        $errors =
            DateTimeImmutable::getLastErrors();


        if (
            !$parsed
            ||
            (
                is_array(
                    $errors
                )
                &&
                (
                    $errors['warning_count'] > 0
                    ||
                    $errors['error_count'] > 0
                )
            )
            ||
            $parsed->format(
                'Y-m-d'
            )
            !==
            $date
        ) {
            throw new InvalidArgumentException(
                'The '
                .
                $label
                .
                ' must use YYYY-MM-DD format.'
            );
        }


        return $parsed;
    }


    private function normalizeDepartment(
        ?string $department
    ): ?string
    {
        if ($department === null) {

            return null;
        }


        $department =
            trim(
                $department
            );


        return $department === ''
            ? null
            : $department;
    }


    /**
     * @return array<string,mixed>
     */
    private function companySettings(): array
    {
        $company =
            $this->settings->get()
            ??
            [];


        $laborRules =
            $this->laborRules->get();


        $timezone =
            (string)(
                $company['timezone']
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
            $timezone =
                'America/Los_Angeles';
        }


        return [
            ...$company,
            ...$laborRules,

            'timezone' =>
                $timezone,

            'workweek_start_day' =>
                (string)(
                    $laborRules['workweek_start_day']
                    ??
                    $company['pay_period_start']
                    ??
                    'monday'
                ),

            'daily_overtime_hours' =>
                (float)(
                    $company['daily_overtime_hours']
                    ??
                    8
                ),

            'weekly_overtime_hours' =>
                (float)(
                    $company['weekly_overtime_hours']
                    ??
                    40
                ),

            'double_time_hours' =>
                (float)(
                    $laborRules['double_time_hours']
                    ??
                    12
                ),

            'rounding_minutes' =>
                (int)(
                    $company['rounding_minutes']
                    ??
                    0
                ),

            'rounding_mode' =>
                (string)(
                    $company['rounding_mode']
                    ??
                    'nearest'
                ),

            'meal_deduction_enabled' =>
                (bool)(
                    $company['meal_deduction_enabled']
                    ??
                    false
                ),

            'meal_deduction_minutes' =>
                (int)(
                    $company['meal_deduction_minutes']
                    ??
                    0
                ),

            'paid_break_minutes' =>
                (int)(
                    $company['paid_break_minutes']
                    ??
                    0
                )
        ];
    }
}
