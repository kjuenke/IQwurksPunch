<?php
declare(strict_types=1);

namespace Tests\Unit\Payroll;

use App\Payroll\PayrollCalculator;
use App\Payroll\PayrollPolicy;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class LaborRulesPayrollTest extends TestCase
{
    private PayrollCalculator $calculator;


    protected function setUp(): void
    {
        $this->calculator =
            new PayrollCalculator();
    }


    public function testCustomDailyAndDoubleTimeThresholdsClassifyHours(): void
    {
        $policy =
            $this->policy(
                [
                    'daily_overtime_hours' =>
                        7.5,

                    'double_time_hours' =>
                        11.5
                ]
            );


        $result =
            $this->calculateDay(
                '2026-07-13',
                14.0,
                $policy
            );


        self::assertTrue(
            $result['complete']
        );


        self::assertSame(
            14.0,
            $result['gross_hours']
        );


        self::assertSame(
            7.5,
            $result['regular_hours']
        );


        self::assertSame(
            4.0,
            $result['daily_overtime_hours']
        );


        self::assertSame(
            2.5,
            $result['double_time_hours']
        );


        self::assertSame(
            4.0,
            $result['overtime_hours']
        );


        self::assertSame(
            6.5,
            $result['premium_hours']
        );


        self::assertSame(
            14.0,
            $result['total_hours']
        );


        $this->assertHourRelationships(
            $result
        );
    }


    public function testWeeklyOvertimeUsesOnlyRemainingRegularHours(): void
    {
        $policy =
            $this->policy(
                [
                    'weekly_overtime_hours' =>
                        36.0
                ]
            );


        $result =
            $this->calculator->calculateWeek(
                [
                    $this->calculateDay(
                        '2026-07-13',
                        8.0,
                        $policy
                    ),

                    $this->calculateDay(
                        '2026-07-14',
                        8.0,
                        $policy
                    ),

                    $this->calculateDay(
                        '2026-07-15',
                        8.0,
                        $policy
                    ),

                    $this->calculateDay(
                        '2026-07-16',
                        8.0,
                        $policy
                    ),

                    $this->calculateDay(
                        '2026-07-17',
                        8.0,
                        $policy
                    )
                ],
                $policy
            );


        self::assertSame(
            40.0,
            $result['total_hours']
        );


        self::assertSame(
            36.0,
            $result['regular_hours']
        );


        self::assertSame(
            0.0,
            $result['daily_overtime_hours']
        );


        self::assertSame(
            4.0,
            $result['weekly_overtime_hours']
        );


        self::assertSame(
            0.0,
            $result['double_time_hours']
        );


        self::assertSame(
            4.0,
            $result['overtime_hours']
        );


        self::assertSame(
            4.0,
            $result['premium_hours']
        );


        $this->assertHourRelationships(
            $result
        );
    }


    public function testDailyAndWeeklyOvertimeAreCombinedWithoutDoubleCounting(): void
    {
        $policy =
            $this->policy(
                [
                    'daily_overtime_hours' =>
                        8.0,

                    'weekly_overtime_hours' =>
                        36.0,

                    'double_time_hours' =>
                        12.0
                ]
            );


        $result =
            $this->calculator->calculateWeek(
                [
                    $this->calculateDay(
                        '2026-07-13',
                        10.0,
                        $policy
                    ),

                    $this->calculateDay(
                        '2026-07-14',
                        10.0,
                        $policy
                    ),

                    $this->calculateDay(
                        '2026-07-15',
                        10.0,
                        $policy
                    ),

                    $this->calculateDay(
                        '2026-07-16',
                        10.0,
                        $policy
                    ),

                    $this->calculateDay(
                        '2026-07-17',
                        10.0,
                        $policy
                    )
                ],
                $policy
            );


        self::assertSame(
            50.0,
            $result['total_hours']
        );


        self::assertSame(
            36.0,
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
            0.0,
            $result['double_time_hours']
        );


        self::assertSame(
            14.0,
            $result['overtime_hours']
        );


        self::assertSame(
            14.0,
            $result['premium_hours']
        );


        $this->assertHourRelationships(
            $result
        );
    }


    public function testDoubleTimeIsPremiumButIsNotOrdinaryOvertime(): void
    {
        $policy =
            $this->policy();


        $result =
            $this->calculator->calculateWeek(
                [
                    $this->calculateDay(
                        '2026-07-13',
                        13.0,
                        $policy
                    ),

                    $this->calculateDay(
                        '2026-07-14',
                        8.0,
                        $policy
                    ),

                    $this->calculateDay(
                        '2026-07-15',
                        8.0,
                        $policy
                    ),

                    $this->calculateDay(
                        '2026-07-16',
                        8.0,
                        $policy
                    ),

                    $this->calculateDay(
                        '2026-07-17',
                        8.0,
                        $policy
                    )
                ],
                $policy
            );


        self::assertSame(
            45.0,
            $result['total_hours']
        );


        self::assertSame(
            40.0,
            $result['regular_hours']
        );


        self::assertSame(
            4.0,
            $result['daily_overtime_hours']
        );


        self::assertSame(
            0.0,
            $result['weekly_overtime_hours']
        );


        self::assertSame(
            1.0,
            $result['double_time_hours']
        );


        self::assertSame(
            4.0,
            $result['overtime_hours']
        );


        self::assertSame(
            5.0,
            $result['premium_hours']
        );


        $this->assertHourRelationships(
            $result
        );
    }


    public function testPolicyAcceptsSundayAndMondayWorkweekStarts(): void
    {
        $mondayPolicy =
            new PayrollPolicy(
                $this->policy(
                    [
                        'workweek_start_day' =>
                            'monday'
                    ]
                )
            );


        $sundayPolicy =
            new PayrollPolicy(
                $this->policy(
                    [
                        'workweek_start_day' =>
                            'sunday'
                    ]
                )
            );


        self::assertSame(
            'monday',
            $mondayPolicy->workweekStartDay()
        );


        self::assertSame(
            'sunday',
            $sundayPolicy->workweekStartDay()
        );
    }


    public function testPolicyRejectsDoubleTimeThresholdAtDailyThreshold(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );


        $this->expectExceptionMessage(
            'double_time_hours must be greater than daily_overtime_hours.'
        );


        new PayrollPolicy(
            $this->policy(
                [
                    'daily_overtime_hours' =>
                        8.0,

                    'double_time_hours' =>
                        8.0
                ]
            )
        );
    }


    /**
     * @param array<string,mixed> $overrides
     *
     * @return array<string,mixed>
     */
    private function policy(
        array $overrides = []
    ): array
    {
        return [
            'timezone' =>
                'America/Los_Angeles',

            'rounding_minutes' =>
                0,

            'rounding_mode' =>
                'nearest',

            'daily_overtime_hours' =>
                8.0,

            'weekly_overtime_hours' =>
                40.0,

            'double_time_hours' =>
                12.0,

            'workweek_start_day' =>
                'monday',

            'meal_deduction_enabled' =>
                false,

            'meal_deduction_minutes' =>
                0,

            'paid_break_minutes' =>
                0,

            ...$overrides
        ];
    }


    /**
     * @param array<string,mixed> $policy
     *
     * @return array<string,mixed>
     */
    private function calculateDay(
        string $date,
        float $hours,
        array $policy
    ): array
    {
        $minutes =
            (int)round(
                $hours
                *
                60
            );


        $clockIn =
            new \DateTimeImmutable(
                $date
                .
                ' 08:00:00'
            );


        $clockOut =
            $clockIn->modify(
                '+'
                .
                $minutes
                .
                ' minutes'
            );


        $result =
            $this->calculator->calculateDay(
                [
                    $this->punch(
                        'clock_in',
                        $clockIn->format(
                            'Y-m-d H:i:s'
                        )
                    ),

                    $this->punch(
                        'clock_out',
                        $clockOut->format(
                            'Y-m-d H:i:s'
                        )
                    )
                ],
                $policy
            );


        return [
            'date' =>
                $date,

            ...$result
        ];
    }


    /**
     * @return array<string,mixed>
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
     * @param array<string,mixed> $result
     */
    private function assertHourRelationships(
        array $result
    ): void
    {
        $dailyOvertime =
            (float)(
                $result['daily_overtime_hours']
                ??
                0
            );


        $weeklyOvertime =
            (float)(
                $result['weekly_overtime_hours']
                ??
                0
            );


        $doubleTime =
            (float)(
                $result['double_time_hours']
                ??
                0
            );


        $overtime =
            (float)(
                $result['overtime_hours']
                ??
                0
            );


        $premium =
            (float)(
                $result['premium_hours']
                ??
                0
            );


        $regular =
            (float)(
                $result['regular_hours']
                ??
                0
            );


        $total =
            (float)(
                $result['total_hours']
                ??
                0
            );


        self::assertSame(
            $dailyOvertime
            +
            $weeklyOvertime,
            $overtime,
            'Total overtime must equal daily overtime plus weekly overtime.'
        );


        self::assertSame(
            $overtime
            +
            $doubleTime,
            $premium,
            'Premium hours must equal total overtime plus double-time.'
        );


        self::assertSame(
            $regular
            +
            $overtime
            +
            $doubleTime,
            $total,
            'Payable hours must equal regular, overtime, and double-time hours.'
        );
    }
}
