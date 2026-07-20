<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\EmailRepository;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

class DashboardService
{
    private EmployeeService $employees;

    private PunchService $punches;

    private PunchReportService $reports;

    private EmailRepository $emails;

    private ReportScheduleService $schedule;

    private CompanySettingsService $settings;


    public function __construct(
        EmployeeService $employees,
        PunchService $punches,
        PunchReportService $reports,
        EmailRepository $emails,
        ReportScheduleService $schedule,
        CompanySettingsService $settings
    )
    {
        $this->employees =
            $employees;


        $this->punches =
            $punches;


        $this->reports =
            $reports;


        $this->emails =
            $emails;


        $this->schedule =
            $schedule;


        $this->settings =
            $settings;
    }


    /**
     * @return array<string,mixed>
     */
    public function summary(): array
    {
        $company =
            $this->settings->get()
            ??
            [];


        $timezone =
            $this->timezone(
                $company['timezone']
                ??
                'America/Los_Angeles'
            );


        $employees =
            $this->employees->all();


        $activeEmployees =
            array_values(
                array_filter(
                    $employees,
                    static fn (
                        array $employee
                    ): bool =>
                        (bool)(
                            $employee['active']
                            ??
                            false
                        )
                )
            );


        $workingEmployees = [];


        foreach ($activeEmployees as $employee) {

            $status =
                $this->punches->status(
                    (int)$employee['id']
                );


            if (
                $status['state']
                !==
                'working'
            ) {
                continue;
            }


            $workingEmployees[] = [
                'id' =>
                    (int)$employee['id'],

                'employee_number' =>
                    (string)$employee['employee_number'],

                'name' =>
                    trim(
                        (string)$employee['first_name']
                        .
                        ' '
                        .
                        (string)$employee['last_name']
                    ),

                'department' =>
                    (string)(
                        $employee['department']
                        ??
                        ''
                    ),

                'clocked_in_at' =>
                    $status['punch']['punch_time']
                    ??
                    null
            ];
        }


        $todayPunches =
            $this->reports->today();


        $weekly =
            $this->safeWeeklySummary();


        $weekTotals =
            $this->weeklyTotals(
                $weekly
            );


        $recentPunches =
            array_slice(
                $this->reports->recent(
                    10
                ),
                0,
                10
            );


        $emailHistory =
            $this->emails->all();


        $lastEmail =
            $emailHistory[0]
            ??
            null;


        $schedule =
            $this->schedule->get();


        return [
            'timezone' =>
                $timezone->getName(),

            'generated_at' =>
                (
                    new DateTimeImmutable(
                        'now',
                        $timezone
                    )
                )->format(
                    'Y-m-d H:i:s'
                ),

            'employees' => [
                'active' =>
                    count(
                        $activeEmployees
                    ),

                'total' =>
                    count(
                        $employees
                    )
            ],

            'working' => [
                'count' =>
                    count(
                        $workingEmployees
                    ),

                'employees' =>
                    $workingEmployees
            ],

            'today' => [
                'punch_count' =>
                    count(
                        $todayPunches
                    ),

                'recent_punches' =>
                    $recentPunches
            ],

            'week' =>
                $weekTotals,

            'payroll_issues' =>
                $this->payrollIssues(
                    $weekly
                ),

            'schedule' =>
                $this->scheduleSummary(
                    $schedule
                ),

            'last_email' =>
                $lastEmail,

            'system' => [
                'database' =>
                    'healthy',

                'payroll_engine' =>
                    'healthy',

                'scheduler' =>
                    (
                        !empty(
                            $schedule['enabled']
                        )
                            ? 'enabled'
                            : 'disabled'
                    )
            ]
        ];
    }


    /**
     * @return array<string,mixed>
     */
    private function safeWeeklySummary(): array
    {
        try {

            return $this->reports->weeklySummary();

        } catch (Throwable $exception) {

            return [
                'week_start' =>
                    null,

                'week_end' =>
                    null,

                'employees' =>
                    [],

                'error' =>
                    $exception->getMessage()
            ];
        }
    }


    /**
     * @param array<string,mixed> $weekly
     *
     * @return array<string,mixed>
     */
    private function weeklyTotals(
        array $weekly
    ): array
    {
        $regularHours = 0.0;

        $dailyOvertimeHours = 0.0;

        $weeklyOvertimeHours = 0.0;

        $doubleTimeHours = 0.0;

        $overtimeHours = 0.0;

        $premiumHours = 0.0;

        $totalHours = 0.0;


        foreach (
            $weekly['employees']
            ??
            []
            as $employee
        ) {
            $employeeDailyOvertime =
                (float)(
                    $employee['daily_overtime_hours']
                    ??
                    0
                );


            $employeeWeeklyOvertime =
                (float)(
                    $employee['weekly_overtime_hours']
                    ??
                    0
                );


            $employeeDoubleTime =
                (float)(
                    $employee['double_time_hours']
                    ??
                    0
                );


            $employeeOvertime =
                (float)(
                    $employee['overtime_hours']
                    ??
                    (
                        $employeeDailyOvertime
                        +
                        $employeeWeeklyOvertime
                    )
                );


            $employeePremium =
                (float)(
                    $employee['premium_hours']
                    ??
                    (
                        $employeeOvertime
                        +
                        $employeeDoubleTime
                    )
                );


            $regularHours +=
                (float)(
                    $employee['regular_hours']
                    ??
                    0
                );


            $dailyOvertimeHours +=
                $employeeDailyOvertime;


            $weeklyOvertimeHours +=
                $employeeWeeklyOvertime;


            $doubleTimeHours +=
                $employeeDoubleTime;


            $overtimeHours +=
                $employeeOvertime;


            $premiumHours +=
                $employeePremium;


            $totalHours +=
                (float)(
                    $employee['total_hours']
                    ??
                    0
                );
        }


        return [
            'start' =>
                $weekly['week_start']
                ??
                null,

            'end' =>
                $weekly['week_end']
                ??
                null,

            'regular_hours' =>
                round(
                    $regularHours,
                    2
                ),

            'daily_overtime_hours' =>
                round(
                    $dailyOvertimeHours,
                    2
                ),

            'weekly_overtime_hours' =>
                round(
                    $weeklyOvertimeHours,
                    2
                ),

            'double_time_hours' =>
                round(
                    $doubleTimeHours,
                    2
                ),

            'overtime_hours' =>
                round(
                    $overtimeHours,
                    2
                ),

            'premium_hours' =>
                round(
                    $premiumHours,
                    2
                ),

            'total_hours' =>
                round(
                    $totalHours,
                    2
                )
        ];
    }


    /**
     * @param array<string,mixed> $weekly
     *
     * @return array<string,mixed>
     */
    private function payrollIssues(
        array $weekly
    ): array
    {
        $issues = [];


        foreach (
            $weekly['employees']
            ??
            []
            as $employee
        ) {
            foreach (
                $employee['errors']
                ??
                []
                as $error
            ) {
                $issues[] = [
                    'employee_id' =>
                        (int)(
                            $employee['employee_id']
                            ??
                            0
                        ),

                    'employee_name' =>
                        (string)(
                            $employee['name']
                            ??
                            'Unknown Employee'
                        ),

                    'message' =>
                        (string)$error
                ];
            }
        }


        return [
            'count' =>
                count(
                    $issues
                ),

            'items' =>
                $issues
        ];
    }


    /**
     * @param array<string,mixed>|null $schedule
     *
     * @return array<string,mixed>
     */
    private function scheduleSummary(
        ?array $schedule
    ): array
    {
        if (!$schedule) {

            return [
                'configured' =>
                    false,

                'enabled' =>
                    false,

                'send_time' =>
                    null,

                'weekdays_only' =>
                    false,

                'last_sent_at' =>
                    null
            ];
        }


        return [
            'configured' =>
                true,

            'enabled' =>
                (bool)(
                    $schedule['enabled']
                    ??
                    false
                ),

            'send_time' =>
                $schedule['send_time']
                ??
                null,

            'weekdays_only' =>
                (bool)(
                    $schedule['weekdays_only']
                    ??
                    false
                ),

            'last_sent_at' =>
                $schedule['last_sent_at']
                ??
                null
        ];
    }


    private function timezone(
        mixed $timezone
    ): DateTimeZone
    {
        if (
            !is_string(
                $timezone
            )
            ||
            !in_array(
                $timezone,
                timezone_identifiers_list(),
                true
            )
        ) {
            return new DateTimeZone(
                'America/Los_Angeles'
            );
        }


        return new DateTimeZone(
            $timezone
        );
    }
}
