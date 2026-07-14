<?php
declare(strict_types=1);

namespace App\Payroll;

final class PayrollCalculator
{
    private DailyPayrollCalculator $dailyCalculator;


    public function __construct(
        ?DailyPayrollCalculator $dailyCalculator = null
    )
    {
        $this->dailyCalculator =
            $dailyCalculator
            ??
            new DailyPayrollCalculator();
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
}
