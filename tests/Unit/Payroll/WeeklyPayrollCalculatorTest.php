<?php
declare(strict_types=1);

namespace Tests\Unit\Payroll;

use App\Payroll\PayrollCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class WeeklyPayrollCalculatorTest extends TestCase
{
    private PayrollCalculator $calculator;


    protected function setUp(): void
    {
        $this->calculator =
            new PayrollCalculator();
    }


    public function testCalculatesWeekBelowOvertimeThreshold(): void
    {
        $result =
            $this->calculator->calculateWeek(
                [
                    $this->day(
                        '2026-07-13',
                        8,
                        8,
                        0
                    ),

                    $this->day(
                        '2026-07-14',
                        8,
                        8,
                        0
                    ),

                    $this->day(
                        '2026-07-15',
                        8,
                        8,
                        0
                    ),

                    $this->day(
                        '2026-07-16',
                        8,
                        8,
                        0
                    )
                ],
                $this->policy()
            );


        self::assertTrue(
            $result['complete']
        );


        self::assertSame(
            32.0,
            $result['total_hours']
        );


        self::assertSame(
            32.0,
            $result['regular_hours']
        );


        self::assertSame(
            0.0,
            $result['weekly_overtime_hours']
        );


        self::assertSame(
            0.0,
            $result['overtime_hours']
        );
    }


    public function testConvertsRegularHoursAboveFortyToWeeklyOvertime(): void
    {
        $result =
            $this->calculator->calculateWeek(
                [
                    $this->day(
                        '2026-07-13',
                        8,
                        8,
                        0
                    ),

                    $this->day(
                        '2026-07-14',
                        8,
                        8,
                        0
                    ),

                    $this->day(
                        '2026-07-15',
                        8,
                        8,
                        0
                    ),

                    $this->day(
                        '2026-07-16',
                        8,
                        8,
                        0
                    ),

                    $this->day(
                        '2026-07-17',
                        8,
                        8,
                        0
                    ),

                    $this->day(
                        '2026-07-18',
                        4,
                        4,
                        0
                    )
                ],
                $this->policy()
            );


        self::assertSame(
            44.0,
            $result['total_hours']
        );


        self::assertSame(
            40.0,
            $result['regular_hours']
        );


        self::assertSame(
            4.0,
            $result['weekly_overtime_hours']
        );


        self::assertSame(
            4.0,
            $result['overtime_hours']
        );
    }


    public function testDoesNotDoubleCountDailyOvertime(): void
    {
        $result =
            $this->calculator->calculateWeek(
                [
                    $this->day(
                        '2026-07-13',
                        10,
                        8,
                        2
                    ),

                    $this->day(
                        '2026-07-14',
                        10,
                        8,
                        2
                    ),

                    $this->day(
                        '2026-07-15',
                        10,
                        8,
                        2
                    ),

                    $this->day(
                        '2026-07-16',
                        10,
                        8,
                        2
                    ),

                    $this->day(
                        '2026-07-17',
                        10,
                        8,
                        2
                    )
                ],
                $this->policy()
            );


        self::assertSame(
            50.0,
            $result['total_hours']
        );


        self::assertSame(
            40.0,
            $result['regular_hours']
        );


        self::assertSame(
            10.0,
            $result['daily_overtime_hours']
        );


        self::assertSame(
            0.0,
            $result['weekly_overtime_hours']
        );


        self::assertSame(
            10.0,
            $result['overtime_hours']
        );
    }


    public function testCombinesDailyAndWeeklyOvertimeWithoutDoubleCounting(): void
    {
        $result =
            $this->calculator->calculateWeek(
                [
                    $this->day(
                        '2026-07-13',
                        10,
                        8,
                        2
                    ),

                    $this->day(
                        '2026-07-14',
                        10,
                        8,
                        2
                    ),

                    $this->day(
                        '2026-07-15',
                        10,
                        8,
                        2
                    ),

                    $this->day(
                        '2026-07-16',
                        10,
                        8,
                        2
                    ),

                    $this->day(
                        '2026-07-17',
                        10,
                        8,
                        2
                    ),

                    $this->day(
                        '2026-07-18',
                        4,
                        4,
                        0
                    )
                ],
                $this->policy()
            );


        self::assertSame(
            54.0,
            $result['total_hours']
        );


        self::assertSame(
            40.0,
            $result['regular_hours']
        );


        self::assertSame(
            10.0,
            $result['daily_overtime_hours']
        );


        self::assertSame(
            4.0,
            $result['weekly_overtime_hours']
        );


        self::assertSame(
            14.0,
            $result['overtime_hours']
        );
    }


    public function testUsesConfiguredWeeklyThreshold(): void
    {
        $policy =
            $this->policy();


        $policy['weekly_overtime_hours'] =
            35;


        $result =
            $this->calculator->calculateWeek(
                [
                    $this->day(
                        '2026-07-13',
                        8,
                        8,
                        0
                    ),

                    $this->day(
                        '2026-07-14',
                        8,
                        8,
                        0
                    ),

                    $this->day(
                        '2026-07-15',
                        8,
                        8,
                        0
                    ),

                    $this->day(
                        '2026-07-16',
                        8,
                        8,
                        0
                    ),

                    $this->day(
                        '2026-07-17',
                        8,
                        8,
                        0
                    )
                ],
                $policy
            );


        self::assertSame(
            35.0,
            $result['regular_hours']
        );


        self::assertSame(
            5.0,
            $result['weekly_overtime_hours']
        );
    }


    public function testCarriesIncompleteDayErrorsIntoWeeklyResult(): void
    {
        $incompleteDay =
            $this->day(
                '2026-07-15',
                0,
                0,
                0
            );


        $incompleteDay['complete'] =
            false;


        $incompleteDay['errors'] = [
            'A clock-in punch does not have a matching clock-out punch.'
        ];


        $result =
            $this->calculator->calculateWeek(
                [
                    $this->day(
                        '2026-07-13',
                        8,
                        8,
                        0
                    ),

                    $incompleteDay
                ],
                $this->policy()
            );


        self::assertFalse(
            $result['complete']
        );


        self::assertCount(
            1,
            $result['errors']
        );


        self::assertSame(
            '2026-07-15: A clock-in punch does not have a matching clock-out punch.',
            $result['errors'][0]
        );
    }


    public function testRejectsInvalidDailyResult(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );


        $this->calculator->calculateWeek(
            [
                [
                    'date' =>
                        '2026-07-13',

                    'complete' =>
                        true,

                    'errors' =>
                        []
                ]
            ],
            $this->policy()
        );
    }


    /**
     * @return array<string,mixed>
     */
    private function day(
        string $date,
        float|int $totalHours,
        float|int $regularHours,
        float|int $overtimeHours
    ): array
    {
        return [
            'date' =>
                $date,

            'complete' =>
                true,

            'errors' =>
                [],

            'gross_hours' =>
                (float)$totalHours,

            'total_hours' =>
                (float)$totalHours,

            'regular_hours' =>
                (float)$regularHours,

            'overtime_hours' =>
                (float)$overtimeHours
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

            'weekly_overtime_hours' =>
                40,

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
