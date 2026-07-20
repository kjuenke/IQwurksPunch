<?php
declare(strict_types=1);

namespace App\Payroll;

use DateTimeZone;
use InvalidArgumentException;

final class PayrollPolicy
{
    private DateTimeZone $timezone;

    private int $roundingMinutes;

    private string $roundingMode;

    private float $dailyOvertimeHours;

    private float $weeklyOvertimeHours;

    private float $doubleTimeHours;

    private string $workweekStartDay;

    private bool $mealDeductionEnabled;

    private int $mealDeductionMinutes;

    private int $paidBreakMinutes;


    /**
     * @param array<string,mixed> $settings
     */
    public function __construct(
        array $settings
    )
    {
        $this->timezone =
            $this->createTimezone(
                $settings['timezone']
                ??
                'America/Los_Angeles'
            );


        $this->roundingMinutes =
            $this->nonNegativeInteger(
                $settings['rounding_minutes']
                ??
                0,
                'rounding_minutes'
            );


        $this->roundingMode =
            $this->validateRoundingMode(
                $settings['rounding_mode']
                ??
                'nearest'
            );


        $this->dailyOvertimeHours =
            $this->positiveFloat(
                $settings['daily_overtime_hours']
                ??
                8,
                'daily_overtime_hours'
            );


        $this->weeklyOvertimeHours =
            $this->positiveFloat(
                $settings['weekly_overtime_hours']
                ??
                40,
                'weekly_overtime_hours'
            );


        $this->doubleTimeHours =
            $this->positiveFloat(
                $settings['double_time_hours']
                ??
                12,
                'double_time_hours'
            );


        if (
            $this->doubleTimeHours
            <=
            $this->dailyOvertimeHours
        ) {
            throw new InvalidArgumentException(
                'double_time_hours must be greater than daily_overtime_hours.'
            );
        }


        $this->workweekStartDay =
            $this->validateWorkweekStartDay(
                $settings['workweek_start_day']
                ??
                $settings['pay_period_start']
                ??
                'monday'
            );


        $this->mealDeductionEnabled =
            (bool)(
                $settings['meal_deduction_enabled']
                ??
                false
            );


        $this->mealDeductionMinutes =
            $this->nonNegativeInteger(
                $settings['meal_deduction_minutes']
                ??
                0,
                'meal_deduction_minutes'
            );


        $this->paidBreakMinutes =
            $this->nonNegativeInteger(
                $settings['paid_break_minutes']
                ??
                0,
                'paid_break_minutes'
            );
    }


    public function timezone(): DateTimeZone
    {
        return $this->timezone;
    }


    public function roundingMinutes(): int
    {
        return $this->roundingMinutes;
    }


    public function roundingMode(): string
    {
        return $this->roundingMode;
    }


    public function dailyOvertimeHours(): float
    {
        return $this->dailyOvertimeHours;
    }


    public function weeklyOvertimeHours(): float
    {
        return $this->weeklyOvertimeHours;
    }


    public function doubleTimeHours(): float
    {
        return $this->doubleTimeHours;
    }


    public function workweekStartDay(): string
    {
        return $this->workweekStartDay;
    }


    public function mealDeductionEnabled(): bool
    {
        return $this->mealDeductionEnabled;
    }


    public function mealDeductionMinutes(): int
    {
        return $this->mealDeductionMinutes;
    }


    public function paidBreakMinutes(): int
    {
        return $this->paidBreakMinutes;
    }


    private function createTimezone(
        mixed $value
    ): DateTimeZone
    {
        if (
            !is_string(
                $value
            )
            ||
            !in_array(
                $value,
                timezone_identifiers_list(),
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Invalid payroll timezone.'
            );
        }


        return new DateTimeZone(
            $value
        );
    }


    private function validateRoundingMode(
        mixed $value
    ): string
    {
        if (
            !is_string(
                $value
            )
            ||
            !in_array(
                $value,
                [
                    'nearest',
                    'up',
                    'down'
                ],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Invalid payroll rounding mode.'
            );
        }


        return $value;
    }


    private function validateWorkweekStartDay(
        mixed $value
    ): string
    {
        if (!is_string($value)) {

            throw new InvalidArgumentException(
                'Invalid workweek start day.'
            );
        }


        $value =
            strtolower(
                trim(
                    $value
                )
            );


        if (
            !in_array(
                $value,
                [
                    'sunday',
                    'monday'
                ],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Workweek start day must be Sunday or Monday.'
            );
        }


        return $value;
    }


    private function nonNegativeInteger(
        mixed $value,
        string $name
    ): int
    {
        if (!is_numeric($value)) {

            throw new InvalidArgumentException(
                "{$name} must be numeric."
            );
        }


        $number =
            (int)$value;


        if ($number < 0) {

            throw new InvalidArgumentException(
                "{$name} cannot be negative."
            );
        }


        return $number;
    }


    private function positiveFloat(
        mixed $value,
        string $name
    ): float
    {
        if (!is_numeric($value)) {

            throw new InvalidArgumentException(
                "{$name} must be numeric."
            );
        }


        $number =
            (float)$value;


        if ($number <= 0) {

            throw new InvalidArgumentException(
                "{$name} must be greater than zero."
            );
        }


        return $number;
    }
}
