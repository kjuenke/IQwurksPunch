<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\CompanySettingsRepository;

class CompanySettingsService
{
    private CompanySettingsRepository $settings;


    public function __construct(
        CompanySettingsRepository $settings
    )
    {
        $this->settings =
            $settings;
    }


    public function get(): ?array
    {
        return $this->settings->get();
    }


    public function update(
        array $data
    ): array
    {
        $errors = [];


        $companyName =
            trim(
                $data['company_name']
                ??
                ''
            );


        $email =
            trim(
                $data['email']
                ??
                ''
            );


        $timezone =
            $data['timezone']
            ??
            'America/Los_Angeles';


        $payPeriodStart =
            $data['pay_period_start']
            ??
            'monday';


        $dailyOvertimeHours =
            (float)(
                $data['daily_overtime_hours']
                ??
                8
            );


        $weeklyOvertimeHours =
            (float)(
                $data['weekly_overtime_hours']
                ??
                40
            );


        $roundingMinutes =
            (int)(
                $data['rounding_minutes']
                ??
                15
            );


        $roundingMode =
            $data['rounding_mode']
            ??
            'nearest';


        $mealDeductionEnabled =
            isset(
                $data['meal_deduction_enabled']
            )
                ? 1
                : 0;


        $mealDeductionMinutes =
            (int)(
                $data['meal_deduction_minutes']
                ??
                30
            );


        $paidBreakMinutes =
            (int)(
                $data['paid_break_minutes']
                ??
                20
            );


        if ($companyName === '') {

            $errors['company_name'] =
                'Company name is required.';
        }


        if (
            $email !== ''
            &&
            !filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {

            $errors['email'] =
                'Invalid email address.';
        }


        if (
            !in_array(
                $timezone,
                timezone_identifiers_list(),
                true
            )
        ) {

            $errors['timezone'] =
                'Invalid timezone.';
        }


        if (
            !in_array(
                $payPeriodStart,
                [
                    'monday',
                    'sunday'
                ],
                true
            )
        ) {

            $errors['pay_period_start'] =
                'Invalid pay period start day.';
        }


        if (
            $dailyOvertimeHours < 0
            ||
            $dailyOvertimeHours > 24
        ) {

            $errors['daily_overtime_hours'] =
                'Daily overtime hours must be between 0 and 24.';
        }


        if (
            $weeklyOvertimeHours < 0
            ||
            $weeklyOvertimeHours > 168
        ) {

            $errors['weekly_overtime_hours'] =
                'Weekly overtime hours must be between 0 and 168.';
        }


        if (
            !in_array(
                $roundingMinutes,
                [
                    0,
                    5,
                    6,
                    10,
                    15,
                    30
                ],
                true
            )
        ) {

            $errors['rounding_minutes'] =
                'Invalid rounding interval.';
        }


        if (
            !in_array(
                $roundingMode,
                [
                    'nearest',
                    'up',
                    'down'
                ],
                true
            )
        ) {

            $errors['rounding_mode'] =
                'Invalid rounding mode.';
        }


        if (
            $mealDeductionMinutes < 0
            ||
            $mealDeductionMinutes > 240
        ) {

            $errors['meal_deduction_minutes'] =
                'Meal deduction must be between 0 and 240 minutes.';
        }


        if (
            $paidBreakMinutes < 0
            ||
            $paidBreakMinutes > 240
        ) {

            $errors['paid_break_minutes'] =
                'Paid break time must be between 0 and 240 minutes.';
        }


        if (!empty($errors)) {

            return [
                'success' =>
                    false,

                'errors' =>
                    $errors
            ];
        }


        $updated =
            $this->settings->update(
                [
                    'company_name' =>
                        $companyName,

                    'address' =>
                        trim(
                            $data['address']
                            ??
                            ''
                        ),

                    'city' =>
                        trim(
                            $data['city']
                            ??
                            ''
                        ),

                    'state' =>
                        trim(
                            $data['state']
                            ??
                            ''
                        ),

                    'zip' =>
                        trim(
                            $data['zip']
                            ??
                            ''
                        ),

                    'phone' =>
                        trim(
                            $data['phone']
                            ??
                            ''
                        ),

                    'email' =>
                        $email,

                    'timezone' =>
                        $timezone,

                    'pay_period_start' =>
                        $payPeriodStart,

                    'daily_overtime_hours' =>
                        $dailyOvertimeHours,

                    'weekly_overtime_hours' =>
                        $weeklyOvertimeHours,

                    'rounding_minutes' =>
                        $roundingMinutes,

                    'rounding_mode' =>
                        $roundingMode,

                    'meal_deduction_enabled' =>
                        $mealDeductionEnabled,

                    'meal_deduction_minutes' =>
                        $mealDeductionMinutes,

                    'paid_break_minutes' =>
                        $paidBreakMinutes
                ]
            );


        return [
            'success' =>
                $updated,

            'errors' =>
                []
        ];
    }
}
