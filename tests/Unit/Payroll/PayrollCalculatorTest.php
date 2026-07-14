<?php
declare(strict_types=1);

namespace Tests\Unit\Payroll;

use App\Payroll\PayrollCalculator;
use PHPUnit\Framework\TestCase;

final class PayrollCalculatorTest extends TestCase
{
    private PayrollCalculator $calculator;


    protected function setUp(): void
    {
        $this->calculator =
            new PayrollCalculator();
    }


    public function testCalculatesOneCompleteWorkPeriod(): void
    {
        $result =
            $this->calculator->calculateDay(
                [
                    [
                        'punch_type' =>
                            'clock_in',

                        'punch_time' =>
                            '2026-07-14 15:00:00'
                    ],
                    [
                        'punch_type' =>
                            'clock_out',

                        'punch_time' =>
                            '2026-07-14 23:00:00'
                    ]
                ],
                $this->policy()
            );


        self::assertTrue(
            $result['complete']
        );


        self::assertSame(
            8.0,
            $result['total_hours']
        );


        self::assertSame(
            8.0,
            $result['regular_hours']
        );


        self::assertSame(
            0.0,
            $result['overtime_hours']
        );
    }


    public function testCalculatesMultipleWorkPeriods(): void
    {
        $result =
            $this->calculator->calculateDay(
                [
                    [
                        'punch_type' =>
                            'clock_in',

                        'punch_time' =>
                            '2026-07-14 15:00:00'
                    ],
                    [
                        'punch_type' =>
                            'clock_out',

                        'punch_time' =>
                            '2026-07-14 19:00:00'
                    ],
                    [
                        'punch_type' =>
                            'clock_in',

                        'punch_time' =>
                            '2026-07-14 20:00:00'
                    ],
                    [
                        'punch_type' =>
                            'clock_out',

                        'punch_time' =>
                            '2026-07-15 00:00:00'
                    ]
                ],
                $this->policy()
            );


        self::assertSame(
            8.0,
            $result['total_hours']
        );


        self::assertCount(
            2,
            $result['work_periods']
        );
    }


    public function testCalculatesDailyOvertime(): void
    {
        $result =
            $this->calculator->calculateDay(
                [
                    [
                        'punch_type' =>
                            'clock_in',

                        'punch_time' =>
                            '2026-07-14 15:00:00'
                    ],
                    [
                        'punch_type' =>
                            'clock_out',

                        'punch_time' =>
                            '2026-07-15 01:00:00'
                    ]
                ],
                $this->policy()
            );


        self::assertSame(
            10.0,
            $result['total_hours']
        );


        self::assertSame(
            8.0,
            $result['regular_hours']
        );


        self::assertSame(
            2.0,
            $result['overtime_hours']
        );
    }


    public function testAppliesAutomaticMealDeduction(): void
    {
        $policy =
            $this->policy();


        $policy['meal_deduction_enabled'] =
            true;


        $policy['meal_deduction_minutes'] =
            30;


        $result =
            $this->calculator->calculateDay(
                [
                    [
                        'punch_type' =>
                            'clock_in',

                        'punch_time' =>
                            '2026-07-14 15:00:00'
                    ],
                    [
                        'punch_type' =>
                            'clock_out',

                        'punch_time' =>
                            '2026-07-14 23:00:00'
                    ]
                ],
                $policy
            );


        self::assertSame(
            8.0,
            $result['gross_hours']
        );


        self::assertSame(
            30,
            $result['meal_deduction_minutes']
        );


        self::assertSame(
            7.5,
            $result['total_hours']
        );
    }


    public function testDoesNotApplyAutomaticMealDeductionWhenMealPunchExists(): void
    {
        $policy =
            $this->policy();


        $policy['meal_deduction_enabled'] =
            true;


        $policy['meal_deduction_minutes'] =
            30;


        $result =
            $this->calculator->calculateDay(
                [
                    [
                        'punch_type' =>
                            'clock_in',

                        'punch_time' =>
                            '2026-07-14 15:00:00'
                    ],
                    [
                        'punch_type' =>
                            'meal_out',

                        'punch_time' =>
                            '2026-07-14 19:00:00'
                    ],
                    [
                        'punch_type' =>
                            'meal_in',

                        'punch_time' =>
                            '2026-07-14 19:30:00'
                    ],
                    [
                        'punch_type' =>
                            'clock_out',

                        'punch_time' =>
                            '2026-07-14 23:00:00'
                    ]
                ],
                $policy
            );


        self::assertSame(
            0,
            $result['meal_deduction_minutes']
        );


        self::assertSame(
            8.0,
            $result['total_hours']
        );
    }


    public function testRoundsPunchesToNearestInterval(): void
    {
        $policy =
            $this->policy();


        $policy['rounding_minutes'] =
            15;


        $policy['rounding_mode'] =
            'nearest';


        $result =
            $this->calculator->calculateDay(
                [
                    [
                        'punch_type' =>
                            'clock_in',

                        'punch_time' =>
                            '2026-07-14 15:07:00'
                    ],
                    [
                        'punch_type' =>
                            'clock_out',

                        'punch_time' =>
                            '2026-07-14 23:08:00'
                    ]
                ],
                $policy
            );


        self::assertSame(
            8.25,
            $result['total_hours']
        );
    }


    public function testDetectsMissingClockOut(): void
    {
        $result =
            $this->calculator->calculateDay(
                [
                    [
                        'punch_type' =>
                            'clock_in',

                        'punch_time' =>
                            '2026-07-14 15:00:00'
                    ]
                ],
                $this->policy()
            );


        self::assertFalse(
            $result['complete']
        );


        self::assertNotEmpty(
            $result['errors']
        );


        self::assertSame(
            0.0,
            $result['total_hours']
        );
    }


    /**
     * @return array<string,mixed>
     */
    private function policy(): array
    {
        return [
            'timezone' =>
                'America/Los_Angeles',

            'daily_overtime_hours' =>
                8,

            'rounding_minutes' =>
                0,

            'rounding_mode' =>
                'nearest',

            'meal_deduction_enabled' =>
                false,

            'meal_deduction_minutes' =>
                30
        ];
    }
}
