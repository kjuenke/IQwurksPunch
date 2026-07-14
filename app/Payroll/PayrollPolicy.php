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
            $this->nonNegativeFloat(
                $settings['daily_overtime_hours']
                ??
                8,
                'daily_overtime_hours'
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


    private function nonNegativeFloat(
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


        if ($number < 0) {

            throw new InvalidArgumentException(
                "{$name} cannot be negative."
            );
        }


        return $number;
    }
}
