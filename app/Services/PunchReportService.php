<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Container;
use App\Payroll\PayrollCalculator;
use App\Repositories\CompanySettingsRepository;
use App\Repositories\PunchRepository;
use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

class PunchReportService
{
    private PunchRepository $punches;

    private CompanySettingsRepository $settings;

    private PayrollCalculator $payroll;

    private LaborRulesService $laborRules;


    public function __construct(
        PunchRepository $punches,
        ?CompanySettingsRepository $settings = null,
        ?PayrollCalculator $payroll = null,
        ?LaborRulesService $laborRules = null
    )
    {
        $this->punches =
            $punches;


        $this->settings =
            $settings
            ??
            Container::companySettingsRepository();


        $this->payroll =
            $payroll
            ??
            new PayrollCalculator();


        $this->laborRules =
            $laborRules
            ??
            Container::laborRulesService();
    }


    public function employeeHistory(
        int $employeeId
    ): array
    {
        return $this->punches->employeePunches(
            $employeeId
        );
    }


    public function today(): array
    {
        $company =
            $this->companySettings();


        $date =
            new DateTimeImmutable(
                'now',
                new DateTimeZone(
                    $company['timezone']
                )
            );


        return $this->punchesForDate(
            $date->format(
                'Y-m-d'
            ),
            $company['timezone']
        );
    }


    public function recent(
        int $limit = 100
    ): array
    {
        return $this->punches->recent(
            $limit
        );
    }


    /**
     * Compatibility method for callers that only need payable hours.
     *
     * @param array<int,array<string,mixed>> $punches
     */
    public function calculateHours(
        array $punches
    ): float
    {
        $company =
            $this->companySettings();


        $result =
            $this->payroll->calculateDay(
                $punches,
                $company
            );


        return (float)$result['total_hours'];
    }


    /**
     * @return array<int,array<string,mixed>>
     */
    public function dailySummary(
        ?string $date = null
    ): array
    {
        $company =
            $this->companySettings();


        $reportDate =
            $date
            ??
            (
                new DateTimeImmutable(
                    'now',
                    new DateTimeZone(
                        $company['timezone']
                    )
                )
            )->format(
                'Y-m-d'
            );


        $punches =
            $this->punchesForDate(
                $reportDate,
                $company['timezone']
            );


        return $this->dailyEmployeeSummaries(
            $punches,
            $reportDate,
            $company
        );
    }


    /**
     * Returns one weekly payroll result per employee.
     *
     * @return array<string,mixed>
     */
    public function weeklySummary(
        ?string $date = null
    ): array
    {
        $company =
            $this->companySettings();


        $timezone =
            new DateTimeZone(
                $company['timezone']
            );


        $referenceDate =
            new DateTimeImmutable(
                (
                    $date
                    ??
                    'now'
                ),
                $timezone
            );


        $weekStart =
            $this->weekStart(
                $referenceDate,
                $company['workweek_start_day']
                ??
                'monday'
            );


        $weekEnd =
            $weekStart->modify(
                '+7 days'
            );


        $punches =
            $this->punchesBetweenLocalDates(
                $weekStart,
                $weekEnd
            );


        $employees =
            $this->groupPunchesByEmployeeAndDate(
                $punches,
                $timezone
            );


        $weeklyEmployees = [];


        foreach ($employees as $employee) {

            $dailyResults = [];


            $period =
                new DatePeriod(
                    $weekStart,
                    new DateInterval(
                        'P1D'
                    ),
                    $weekEnd
                );


            foreach ($period as $day) {

                $dayDate =
                    $day->format(
                        'Y-m-d'
                    );


                $dayPunches =
                    $employee['punches_by_date'][$dayDate]
                    ??
                    [];


                if (empty($dayPunches)) {

                    continue;
                }


                $dailyResult =
                    $this->payroll->calculateDay(
                        $dayPunches,
                        $company
                    );


                $dailyResults[] = [
                    'date' =>
                        $dayDate,

                    ...$dailyResult
                ];
            }


            if (empty($dailyResults)) {

                continue;
            }


            $weekCalculation =
                $this->payroll->calculateWeek(
                    $dailyResults,
                    $company
                );


            $weeklyEmployees[] = [
                'employee_id' =>
                    $employee['employee_id'],

                'employee_number' =>
                    $employee['employee_number'],

                'name' =>
                    $employee['name'],

                'department' =>
                    $employee['department'],

                ...$weekCalculation
            ];
        }


        return [
            'week_start' =>
                $weekStart->format(
                    'Y-m-d'
                ),

            'week_end' =>
                $weekEnd
                    ->modify(
                        '-1 day'
                    )
                    ->format(
                        'Y-m-d'
                    ),

            'timezone' =>
                $company['timezone'],

            'employees' =>
                $weeklyEmployees
        ];
    }


    /**
     * @param array<int,array<string,mixed>> $punches
     * @param array<string,mixed> $company
     *
     * @return array<int,array<string,mixed>>
     */
    private function dailyEmployeeSummaries(
        array $punches,
        string $reportDate,
        array $company
    ): array
    {
        $employees = [];


        foreach ($punches as $punch) {

            $employeeId =
                (int)$punch['employee_id'];


            if (
                !isset(
                    $employees[$employeeId]
                )
            ) {
                $employees[$employeeId] = [
                    'employee_id' =>
                        $employeeId,

                    'employee_number' =>
                        (string)$punch['employee_number'],

                    'name' =>
                        trim(
                            (string)$punch['first_name']
                            .
                            ' '
                            .
                            (string)$punch['last_name']
                        ),

                    'department' =>
                        (string)(
                            $punch['department']
                            ??
                            ''
                        ),

                    'date' =>
                        $reportDate,

                    'punches' =>
                        []
                ];
            }


            $employees[$employeeId]['punches'][] =
                $punch;
        }


        foreach ($employees as &$employee) {

            $calculation =
                $this->payroll->calculateDay(
                    $employee['punches'],
                    $company
                );


            $employee = [
                ...$employee,
                ...$calculation,

                'hours' =>
                    $calculation['total_hours']
            ];
        }

        unset($employee);


        return array_values(
            $employees
        );
    }


    private function weekStart(
        DateTimeImmutable $date,
        string $startDay
    ): DateTimeImmutable
    {
        $normalizedStartDay =
            strtolower(
                trim(
                    $startDay
                )
            );


        if (
            !in_array(
                $normalizedStartDay,
                [
                    'sunday',
                    'monday'
                ],
                true
            )
        ) {
            $normalizedStartDay =
                'monday';
        }


        if (
            strtolower(
                $date->format(
                    'l'
                )
            )
            ===
            $normalizedStartDay
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
                $normalizedStartDay
            )
            ->setTime(
                0,
                0
            );
    }


    /**
     * @return array<int,array<string,mixed>>
     */
    private function punchesForDate(
        string $date,
        string $timezone
    ): array
    {
        $companyTimezone =
            new DateTimeZone(
                $timezone
            );


        $localStart =
            new DateTimeImmutable(
                $date
                .
                ' 00:00:00',
                $companyTimezone
            );


        $localEnd =
            $localStart->modify(
                '+1 day'
            );


        return $this->punchesBetweenLocalDates(
            $localStart,
            $localEnd
        );
    }


    /**
     * @return array<int,array<string,mixed>>
     */
    private function punchesBetweenLocalDates(
        DateTimeImmutable $localStart,
        DateTimeImmutable $localEnd
    ): array
    {
        $utcTimezone =
            new DateTimeZone(
                'UTC'
            );


        $startUtc =
            $localStart
                ->setTimezone(
                    $utcTimezone
                )
                ->format(
                    'Y-m-d H:i:s'
                );


        $endUtc =
            $localEnd
                ->setTimezone(
                    $utcTimezone
                )
                ->format(
                    'Y-m-d H:i:s'
                );


        return $this->punches->punchesBetween(
            $startUtc,
            $endUtc
        );
    }


    /**
     * @param array<int,array<string,mixed>> $punches
     *
     * @return array<int,array<string,mixed>>
     */
    private function groupPunchesByEmployeeAndDate(
        array $punches,
        DateTimeZone $timezone
    ): array
    {
        $employees = [];


        foreach ($punches as $punch) {

            $employeeId =
                (int)$punch['employee_id'];


            if (
                !isset(
                    $employees[$employeeId]
                )
            ) {
                $employees[$employeeId] = [
                    'employee_id' =>
                        $employeeId,

                    'employee_number' =>
                        (string)$punch['employee_number'],

                    'name' =>
                        trim(
                            (string)$punch['first_name']
                            .
                            ' '
                            .
                            (string)$punch['last_name']
                        ),

                    'department' =>
                        (string)(
                            $punch['department']
                            ??
                            ''
                        ),

                    'punches_by_date' =>
                        []
                ];
            }


            $localDate =
                (
                    new DateTimeImmutable(
                        (string)$punch['punch_time'],
                        new DateTimeZone(
                            'UTC'
                        )
                    )
                )
                    ->setTimezone(
                        $timezone
                    )
                    ->format(
                        'Y-m-d'
                    );


            $employees[$employeeId]
                ['punches_by_date']
                [$localDate][] =
                    $punch;
        }


        return array_values(
            $employees
        );
    }


    /**
     * @return array<string,mixed>
     */
    private function companySettings(): array
    {
        $settings =
            $this->settings->get();


        if (!$settings) {

            throw new RuntimeException(
                'Company settings were not found.'
            );
        }


        $laborRules =
            $this->laborRules->get();


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
            $timezone =
                'America/Los_Angeles';
        }


        $workweekStartDay =
            strtolower(
                trim(
                    (string)(
                        $laborRules['workweek_start_day']
                        ??
                        $settings['pay_period_start']
                        ??
                        'monday'
                    )
                )
            );


        if (
            !in_array(
                $workweekStartDay,
                [
                    'sunday',
                    'monday'
                ],
                true
            )
        ) {
            $workweekStartDay =
                'monday';
        }


        return [
            ...$settings,

            'timezone' =>
                $timezone,

            'daily_overtime_hours' =>
                (float)(
                    $laborRules['daily_overtime_hours']
                    ??
                    $settings['daily_overtime_hours']
                    ??
                    8
                ),

            'weekly_overtime_hours' =>
                (float)(
                    $laborRules['weekly_overtime_hours']
                    ??
                    $settings['weekly_overtime_hours']
                    ??
                    40
                ),

            'double_time_hours' =>
                (float)(
                    $laborRules['double_time_hours']
                    ??
                    12
                ),

            'workweek_start_day' =>
                $workweekStartDay,

            'rounding_minutes' =>
                (int)(
                    $settings['rounding_minutes']
                    ??
                    0
                ),

            'rounding_mode' =>
                (string)(
                    $settings['rounding_mode']
                    ??
                    'nearest'
                ),

            'meal_deduction_enabled' =>
                (bool)(
                    $settings['meal_deduction_enabled']
                    ??
                    false
                ),

            'meal_deduction_minutes' =>
                (int)(
                    $settings['meal_deduction_minutes']
                    ??
                    0
                ),

            'paid_break_minutes' =>
                (int)(
                    $settings['paid_break_minutes']
                    ??
                    0
                )
        ];
    }
}
