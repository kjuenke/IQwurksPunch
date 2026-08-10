<?php
declare(strict_types=1);

use App\Repositories\CompanySettingsRepository;
use App\Services\CompanySettingsService;
use PHPUnit\Framework\TestCase;

final class CompanySettingsPayrollThresholdValidationTest extends TestCase
{
    public function testZeroDailyOvertimeThresholdIsRejected(): void
    {
        $result =
            $this->service()->update(
                [
                    ...$this->validSettings(),

                    'daily_overtime_hours' =>
                        0
                ]
            );


        self::assertFalse(
            $result['success']
        );


        self::assertSame(
            'Daily overtime hours must be greater than 0 and no more than 24.',
            $result['errors']['daily_overtime_hours']
        );
    }


    public function testZeroWeeklyOvertimeThresholdIsRejected(): void
    {
        $result =
            $this->service()->update(
                [
                    ...$this->validSettings(),

                    'weekly_overtime_hours' =>
                        0
                ]
            );


        self::assertFalse(
            $result['success']
        );


        self::assertSame(
            'Weekly overtime hours must be greater than 0 and no more than 168.',
            $result['errors']['weekly_overtime_hours']
        );
    }


    public function testNonNumericDailyOvertimeThresholdIsRejected(): void
    {
        $result =
            $this->service()->update(
                [
                    ...$this->validSettings(),

                    'daily_overtime_hours' =>
                        'not-a-number'
                ]
            );


        self::assertFalse(
            $result['success']
        );


        self::assertArrayHasKey(
            'daily_overtime_hours',
            $result['errors']
        );
    }


    private function service(): CompanySettingsService
    {
        $database =
            new PDO(
                'sqlite::memory:'
            );


        $database->setAttribute(
            PDO::ATTR_ERRMODE,
            PDO::ERRMODE_EXCEPTION
        );


        return
            new CompanySettingsService(
                new CompanySettingsRepository(
                    $database
                )
            );
    }


    /**
     * @return array<string,mixed>
     */
    private function validSettings(): array
    {
        return [
            'company_name' =>
                'IQwurks Test Company',

            'email' =>
                '',

            'timezone' =>
                'America/Los_Angeles',

            'pay_period_start' =>
                'monday',

            'daily_overtime_hours' =>
                8,

            'weekly_overtime_hours' =>
                40,

            'rounding_minutes' =>
                15,

            'rounding_mode' =>
                'nearest',

            'meal_deduction_minutes' =>
                30,

            'paid_break_minutes' =>
                20,

            'kiosk_inactivity_timeout_seconds' =>
                60
        ];
    }
}
