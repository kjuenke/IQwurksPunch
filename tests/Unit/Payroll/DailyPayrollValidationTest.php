<?php
declare(strict_types=1);

use App\Payroll\DailyPayrollCalculator;
use App\Payroll\PayrollPolicy;
use PHPUnit\Framework\TestCase;

final class DailyPayrollValidationTest extends TestCase
{
    public function testUnsupportedPunchTypeMarksPayrollIncomplete(): void
    {
        $calculator =
            new DailyPayrollCalculator();


        $policy =
            new PayrollPolicy(
                [
                    'timezone' =>
                        'America/Los_Angeles',

                    'rounding_minutes' =>
                        0,

                    'rounding_mode' =>
                        'nearest',

                    'daily_overtime_hours' =>
                        8,

                    'weekly_overtime_hours' =>
                        40,

                    'double_time_hours' =>
                        12,

                    'meal_deduction_enabled' =>
                        false,

                    'meal_deduction_minutes' =>
                        0,

                    'paid_break_minutes' =>
                        0
                ]
            );


        $result =
            $calculator->calculate(
                [
                    [
                        'punch_time' =>
                            '2026-08-10 16:00:00',

                        'punch_type' =>
                            'clock_in'
                    ],

                    [
                        'punch_time' =>
                            '2026-08-10 18:00:00',

                        'punch_type' =>
                            'unsupported_type'
                    ],

                    [
                        'punch_time' =>
                            '2026-08-11 00:00:00',

                        'punch_type' =>
                            'clock_out'
                    ]
                ],
                $policy
            );


        self::assertFalse(
            $result['complete']
        );


        self::assertContains(
            'An unsupported punch type was encountered.',
            $result['errors']
        );


        self::assertSame(
            8.0,
            $result['total_hours']
        );
    }
}
