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
        $this->settings = $settings;
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


        if (
            empty(
                trim(
                    $data['company_name'] ?? ''
                )
            )
        ) {

            $errors['company_name'] =
                'Company name is required.';
        }


        if (
            !empty($data['email'])
            &&
            !filter_var(
                $data['email'],
                FILTER_VALIDATE_EMAIL
            )
        ) {

            $errors['email'] =
                'Invalid email address.';
        }


        if (!empty($errors)) {

            return [
                'success' => false,
                'errors' => $errors
            ];
        }



        $updated =
            $this->settings->update(
                [
                    'company_name' =>
                        trim(
                            $data['company_name']
                        ),

                    'address' =>
                        trim(
                            $data['address'] ?? ''
                        ),

                    'city' =>
                        trim(
                            $data['city'] ?? ''
                        ),

                    'state' =>
                        trim(
                            $data['state'] ?? ''
                        ),

                    'zip' =>
                        trim(
                            $data['zip'] ?? ''
                        ),

                    'phone' =>
                        trim(
                            $data['phone'] ?? ''
                        ),

                    'email' =>
                        trim(
                            $data['email'] ?? ''
                        ),

                    'timezone' =>
                        $data['timezone']
                        ??
                        'America/Los_Angeles',

                    'pay_period_start' =>
                        $data['pay_period_start']
                        ??
                        'monday'
                ]
            );


        return [
            'success' => $updated
        ];
    }
}
