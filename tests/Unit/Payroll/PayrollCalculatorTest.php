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
                    $this->punch(
                        'clock_in',
                        '2026-07-14 15:00:00'
                    ),

                    $this->punch(
                        'clock_out',
                        '2026-07-14 23:00:00'
                    )
                ],
                $this->policy()
            );


        self::assertTrue(
            $result['complete']
        );


        self::assertSame(
            8.0,
            $result['gross_hours']
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


        self::assertCount(
            1,
            $result['work_periods']
        );
    }


    public function testCalculatesMultipleWorkPeriods(): void
    {
        $result =
            $this->calculator->calculateDay(
                [
                    $this->punch(
                        'clock_in',
                        '2026-07-14 15:00:00'
                    ),

                    $this->punch(
                        'clock_out',
                        '2026-07-14 19:00:00'
                    ),

                    $this->punch(
                        'clock_in',
                        '2026-07-14 20:00:00'
                    ),

                    $this->punch(
                        'clock_out',
                        '2026-07-15 00:00:00'
                    )
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


        self::assertCount(
            2,
            $result['work_periods']
        );
    }


    public function testCalculatesDailyOvertimeAfterDeductions(): void
    {
        $result =
            $this->calculator->calculateDay(
                [
                    $this->punch(
                        'clock_in',
                        '2026-07-14 15:00:00'
                    ),

                    $this->punch(
                        'clock_out',
                        '2026-07-15 01:00:00'
                    )
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


    public function testAppliesAutomaticMealDeductionWhenNoMealIsRecorded(): void
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
                    $this->punch(
                        'clock_in',
                        '2026-07-14 15:00:00'
                    ),

                    $this->punch(
                        'clock_out',
                        '2026-07-14 23:00:00'
                    )
                ],
                $policy
            );


        self::assertSame(
            8.0,
            $result['gross_hours']
        );


        self::assertSame(
            0,
            $result['recorded_meal_minutes']
        );


        self::assertSame(
            30,
            $result['automatic_meal_deduction_minutes']
        );


        self::assertSame(
            7.5,
            $result['total_hours']
        );
    }


    public function testSubtractsARecordedMealPeriod(): void
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
                    $this->punch(
                        'clock_in',
                        '2026-07-14 15:00:00'
                    ),

                    $this->punch(
                        'meal_out',
                        '2026-07-14 19:00:00'
                    ),

                    $this->punch(
                        'meal_in',
                        '2026-07-14 19:30:00'
                    ),

                    $this->punch(
                        'clock_out',
                        '2026-07-14 23:00:00'
                    )
                ],
                $policy
            );


        self::assertTrue(
            $result['complete']
        );


        self::assertSame(
            8.0,
            $result['gross_hours']
        );


        self::assertSame(
            30,
            $result['recorded_meal_minutes']
        );


        self::assertSame(
            0,
            $result['automatic_meal_deduction_minutes']
        );


        self::assertSame(
            7.5,
            $result['total_hours']
        );


        self::assertCount(
            1,
            $result['meal_periods']
        );
    }


    public function testPaidBreakWithinAllowanceDoesNotReduceHours(): void
    {
        $policy =
            $this->policy();


        $policy['paid_break_minutes'] =
            20;


        $result =
            $this->calculator->calculateDay(
                [
                    $this->punch(
                        'clock_in',
                        '2026-07-14 15:00:00'
                    ),

                    $this->punch(
                        'break_out',
                        '2026-07-14 18:00:00'
                    ),

                    $this->punch(
                        'break_in',
                        '2026-07-14 18:15:00'
                    ),

                    $this->punch(
                        'clock_out',
                        '2026-07-14 23:00:00'
                    )
                ],
                $policy
            );


        self::assertSame(
            15,
            $result['recorded_break_minutes']
        );


        self::assertSame(
            15,
            $result['paid_break_minutes']
        );


        self::assertSame(
            0,
            $result['unpaid_break_minutes']
        );


        self::assertSame(
            8.0,
            $result['total_hours']
        );
    }


    public function testBreakBeyondAllowanceReducesPayableHours(): void
    {
        $policy =
            $this->policy();


        $policy['paid_break_minutes'] =
            20;


        $result =
            $this->calculator->calculateDay(
                [
                    $this->punch(
                        'clock_in',
                        '2026-07-14 15:00:00'
                    ),

                    $this->punch(
                        'break_out',
                        '2026-07-14 18:00:00'
                    ),

                    $this->punch(
                        'break_in',
                        '2026-07-14 18:30:00'
                    ),

                    $this->punch(
                        'clock_out',
                        '2026-07-14 23:00:00'
                    )
                ],
                $policy
            );


        self::assertSame(
            30,
            $result['recorded_break_minutes']
        );


        self::assertSame(
            20,
            $result['paid_break_minutes']
        );


        self::assertSame(
            10,
            $result['unpaid_break_minutes']
        );


        self::assertSame(
            7.83,
            $result['total_hours']
        );
    }


    public function testCombinesMealAndExcessBreakDeductions(): void
    {
        $policy =
            $this->policy();


        $policy['paid_break_minutes'] =
            20;


        $result =
            $this->calculator->calculateDay(
                [
                    $this->punch(
                        'clock_in',
                        '2026-07-14 15:00:00'
                    ),

                    $this->punch(
                        'break_out',
                        '2026-07-14 17:00:00'
                    ),

                    $this->punch(
                        'break_in',
                        '2026-07-14 17:30:00'
                    ),

                    $this->punch(
                        'meal_out',
                        '2026-07-14 19:00:00'
                    ),

                    $this->punch(
                        'meal_in',
                        '2026-07-14 19:30:00'
                    ),

                    $this->punch(
                        'clock_out',
                        '2026-07-14 23:00:00'
                    )
                ],
                $policy
            );


        self::assertSame(
            8.0,
            $result['gross_hours']
        );


        self::assertSame(
            30,
            $result['recorded_meal_minutes']
        );


        self::assertSame(
            10,
            $result['unpaid_break_minutes']
        );


        self::assertSame(
            7.33,
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
                    $this->punch(
                        'clock_in',
                        '2026-07-14 15:07:00'
                    ),

                    $this->punch(
                        'clock_out',
                        '2026-07-14 23:08:00'
                    )
                ],
                $policy
            );


        self::assertSame(
            8.25,
            $result['total_hours']
        );
    }


    public function testAlwaysRoundsPunchesUp(): void
    {
        $policy =
            $this->policy();


        $policy['rounding_minutes'] =
            15;


        $policy['rounding_mode'] =
            'up';


        $result =
            $this->calculator->calculateDay(
                [
                    $this->punch(
                        'clock_in',
                        '2026-07-14 15:01:00'
                    ),

                    $this->punch(
                        'clock_out',
                        '2026-07-14 23:01:00'
                    )
                ],
                $policy
            );


        self::assertSame(
            8.0,
            $result['total_hours']
        );
    }


    public function testAlwaysRoundsPunchesDown(): void
    {
        $policy =
            $this->policy();


        $policy['rounding_minutes'] =
            15;


        $policy['rounding_mode'] =
            'down';


        $result =
            $this->calculator->calculateDay(
                [
                    $this->punch(
                        'clock_in',
                        '2026-07-14 15:14:00'
                    ),

                    $this->punch(
                        'clock_out',
                        '2026-07-14 23:14:00'
                    )
                ],
                $policy
            );


        self::assertSame(
            8.0,
            $result['total_hours']
        );
    }


    public function testDetectsMissingClockOut(): void
    {
        $result =
            $this->calculator->calculateDay(
                [
                    $this->punch(
                        'clock_in',
                        '2026-07-14 15:00:00'
                    )
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


    public function testDetectsClockOutWithoutClockIn(): void
    {
        $result =
            $this->calculator->calculateDay(
                [
                    $this->punch(
                        'clock_out',
                        '2026-07-14 23:00:00'
                    )
                ],
                $this->policy()
            );


        self::assertFalse(
            $result['complete']
        );


        self::assertNotEmpty(
            $result['errors']
        );
    }


    public function testDetectsIncompleteMealPeriod(): void
    {
        $result =
            $this->calculator->calculateDay(
                [
                    $this->punch(
                        'clock_in',
                        '2026-07-14 15:00:00'
                    ),

                    $this->punch(
                        'meal_out',
                        '2026-07-14 19:00:00'
                    ),

                    $this->punch(
                        'clock_out',
                        '2026-07-14 23:00:00'
                    )
                ],
                $this->policy()
            );


        self::assertFalse(
            $result['complete']
        );


        self::assertNotEmpty(
            $result['errors']
        );
    }


    public function testDetectsIncompleteBreakPeriod(): void
    {
        $result =
            $this->calculator->calculateDay(
                [
                    $this->punch(
                        'clock_in',
                        '2026-07-14 15:00:00'
                    ),

                    $this->punch(
                        'break_out',
                        '2026-07-14 18:00:00'
                    ),

                    $this->punch(
                        'clock_out',
                        '2026-07-14 23:00:00'
                    )
                ],
                $this->policy()
            );


        self::assertFalse(
            $result['complete']
        );


        self::assertNotEmpty(
            $result['errors']
        );
    }


    /**
     * @return array<string,string>
     */
    private function punch(
        string $type,
        string $time
    ): array
    {
        return [
            'punch_type' =>
                $type,

            'punch_time' =>
                $time
        ];
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
                30,

            'paid_break_minutes' =>
                20
        ];
    }
}
