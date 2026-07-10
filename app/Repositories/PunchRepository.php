<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

class PunchRepository
{
    private PDO $db;


    public function __construct(PDO $db)
    {
        $this->db = $db;
    }


    public function create(
        int $employeeId,
        string $type
    ): bool
    {
        $stmt = $this->db->prepare(
            "
            INSERT INTO punches
            (
                employee_id,
                punch_time,
                punch_type
            )

            VALUES
            (
                :employee_id,
                CURRENT_TIMESTAMP,
                :punch_type
            )
            "
        );


        return $stmt->execute(
            [
                'employee_id' => $employeeId,
                'punch_type'  => $type
            ]
        );
    }


    public function latest(
        int $employeeId
    ): ?array
    {
        $stmt = $this->db->prepare(
            "
            SELECT *
            FROM punches
            WHERE employee_id = ?
            ORDER BY punch_time DESC
            LIMIT 1
            "
        );


        $stmt->execute(
            [$employeeId]
        );


        $result = $stmt->fetch();


        return $result ?: null;
    }

    public function employeePunches(
        int $employeeId
    ): array
    {
        $stmt = $this->db->prepare(
            "
            SELECT *
            FROM punches
            WHERE employee_id = ?
            ORDER BY punch_time ASC
            "
        );


        $stmt->execute(
            [
                $employeeId
            ]
        );


        return $stmt->fetchAll();
    }



    public function dailyPunches(
        string $date
    ): array
    {
        $stmt = $this->db->prepare(
            "
            SELECT
                punches.*,
                employees.employee_number,
                employees.first_name,
                employees.last_name
            FROM punches

            JOIN employees
            ON employees.id = punches.employee_id

            WHERE DATE(punch_time) = ?

            ORDER BY punch_time ASC
            "
        );


        $stmt->execute(
            [
                $date
            ]
        );


        return $stmt->fetchAll();
    }



    public function recent(
        int $limit = 100
    ): array
    {
        $stmt = $this->db->prepare(
            "
            SELECT
                punches.*,
                employees.employee_number,
                employees.first_name,
                employees.last_name

            FROM punches

            JOIN employees
            ON employees.id = punches.employee_id

            ORDER BY punch_time DESC

            LIMIT ?
            "
        );


        $stmt->bindValue(
            1,
            $limit,
            PDO::PARAM_INT
        );


        $stmt->execute();


        return $stmt->fetchAll();
    }

}
