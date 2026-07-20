<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class LaborRulesRepository
{
    public function __construct(
        private PDO $db
    ) {
    }


    /**
     * @return array<string,mixed>
     */
    public function get(): array
    {
        $stmt =
            $this->db->query(
                "
                SELECT *

                FROM labor_rules_settings

                WHERE id = 1
                "
            );


        $row =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        return is_array($row)
            ? $row
            : [];
    }


    /**
     * @param array<string,mixed> $data
     */
    public function update(
        array $data
    ): void
    {
        $stmt =
            $this->db->prepare(
                "
                UPDATE labor_rules_settings

                SET

                    daily_overtime_hours =
                        :daily,

                    weekly_overtime_hours =
                        :weekly,

                    double_time_hours =
                        :double,

                    workweek_start_day =
                        :start,

                    updated_at =
                        CURRENT_TIMESTAMP

                WHERE id = 1
                "
            );


        $stmt->execute(
            [
                'daily' =>
                    (float)$data['daily_overtime_hours'],

                'weekly' =>
                    (float)$data['weekly_overtime_hours'],

                'double' =>
                    (float)$data['double_time_hours'],

                'start' =>
                    (string)$data['workweek_start_day']
            ]
        );
    }
}
