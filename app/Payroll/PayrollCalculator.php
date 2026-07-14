<?php
declare(strict_types=1);

namespace App\Payroll;

final class PayrollCalculator
{
    private DailyPayrollCalculator $dailyCalculator;

    private WeeklyPayrollCalculator $weeklyCalculator;


    public function __construct(
        ?DailyPayrollCalculator $dailyCalculator = null,
        ?WeeklyPayrollCalculator $weeklyCalculator = null
    )
    {
        $this->dailyCalculator =
            $dailyCalculator
            ??
            new DailyPayrollCalculator();


        $this->weeklyCalculator =
            $weeklyCalculator
            ??
            new WeeklyPayrollCalculator();
    }


    /**
     * @param array<int,array<string,mixed>> $punches
     * @param array<string,mixed> $policy
     *
     * @return array<string,mixed>
     */
    public function calculateDay(
        array $punches,
        array $policy
    ): array
    {
        return $this->dailyCalculator->calculate(
            $punches,
            new PayrollPolicy(
                $policy
            )
        );
    }


    /**
     * @param array<int,array<string,mixed>> $dailyResults
     * @param array<string,mixed> $policy
     *
     * @return array<string,mixed>
     */
    public function calculateWeek(
        array $dailyResults,
        array $policy
    ): array
    {
        return $this->weeklyCalculator->calculate(
            $dailyResults,
            new PayrollPolicy(
                $policy
            )
        );
    }
}
