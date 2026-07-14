<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Container;
use App\Payroll\PayrollCalculator;
use App\Repositories\CompanySettingsRepository;
use App\Repositories\PunchRepository;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

class PunchReportService
{
    private PunchRepository $punches;

    private CompanySettingsRepository $settings;

    private PayrollCalculator $payroll;


    public function __construct(
        PunchRepository $punches,
        ?CompanySettingsRepository $settings = null,
        ?PayrollCalculator $payroll = null
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

                /*
                 * Compatibility alias for the existing report and
                 * email templates while they are being upgraded.
                 */
                'hours' =>
                    $calculation['total_hours']
            ];
        }

        unset($employee);


        return array_values(
            $employees
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


        $utcTimezone =
            new DateTimeZone(
                'UTC'
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


        $timezone =
            $settings['timezone']
            ??
            'America/Los_Angeles';


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
            ...$settings,

            'timezone' =>
                $timezone,

            'daily_overtime_hours' =>
                (float)(
                    $settings['daily_overtime_hours']
                    ??
                    8
                ),

            'weekly_overtime_hours' =>
                (float)(
                    $settings['weekly_overtime_hours']
                    ??
                    40
                ),

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
