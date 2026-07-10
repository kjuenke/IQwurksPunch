<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

class CompanySettingsRepository
{
    private PDO $db;


    public function __construct(PDO $db)
    {
        $this->db = $db;
    }


    public function get(): ?array
    {
        $stmt =
            $this->db->query(
                "
                SELECT *
                FROM company_settings
                LIMIT 1
                "
            );


        $result =
            $stmt->fetch(PDO::FETCH_ASSOC);


        return $result ?: null;
    }


    public function update(
        array $data
    ): bool
    {
        $stmt =
            $this->db->prepare(
                "
                UPDATE company_settings

                SET

                company_name = :company_name,

                address = :address,

                city = :city,

                state = :state,

                zip = :zip,

                phone = :phone,

                email = :email,

                timezone = :timezone,

                pay_period_start = :pay_period_start,

                updated_at = CURRENT_TIMESTAMP

                WHERE id = 1
                "
            );


        return $stmt->execute(
            [
                'company_name' =>
                    $data['company_name'],

                'address' =>
                    $data['address'],

                'city' =>
                    $data['city'],

                'state' =>
                    $data['state'],

                'zip' =>
                    $data['zip'],

                'phone' =>
                    $data['phone'],

                'email' =>
                    $data['email'],

                'timezone' =>
                    $data['timezone'],

                'pay_period_start' =>
                    $data['pay_period_start']
            ]
        );
    }
}
