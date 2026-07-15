<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

class PunchRepository
{
    private PDO $db;


    public function __construct(
        PDO $db
    )
    {
        $this->db =
            $db;
    }


    public function create(
        int $employeeId,
        string $type
    ): bool
    {
        $stmt =
            $this->db->prepare(
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
                'employee_id' =>
                    $employeeId,

                'punch_type' =>
                    $type
            ]
        );
    }


    public function latest(
        int $employeeId
    ): ?array
    {
        $stmt =
            $this->db->prepare(
                "
                SELECT *
                FROM punches

                WHERE employee_id = :employee_id

                ORDER BY punch_time DESC

                LIMIT 1
                "
            );


        $stmt->execute(
            [
                'employee_id' =>
                    $employeeId
            ]
        );


        $result =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        return $result ?: null;
    }


    public function employeePunches(
        int $employeeId
    ): array
    {
        $stmt =
            $this->db->prepare(
                "
                SELECT *
                FROM punches

                WHERE employee_id = :employee_id

                ORDER BY punch_time ASC
                "
            );


        $stmt->execute(
            [
                'employee_id' =>
                    $employeeId
            ]
        );


        return $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );
    }


    /**
     * Returns punches whose UTC timestamps fall within:
     *
     *     start <= punch_time < end
     */
    public function punchesBetween(
        string $startUtc,
        string $endUtc
    ): array
    {
        $stmt =
            $this->db->prepare(
                "
                SELECT
                    punches.*,
                    employees.employee_number,
                    employees.first_name,
                    employees.last_name,
                    employees.department

                FROM punches

                JOIN employees
                    ON employees.id = punches.employee_id

                WHERE punches.punch_time >= :start_utc
                  AND punches.punch_time < :end_utc

                ORDER BY
                    employees.last_name ASC,
                    employees.first_name ASC,
                    punches.punch_time ASC
                "
            );


        $stmt->execute(
            [
                'start_utc' =>
                    $startUtc,

                'end_utc' =>
                    $endUtc
            ]
        );


        return $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );
    }


    /**
     * Legacy UTC-calendar-date query.
     *
     * New payroll reporting should use punchesBetween() with UTC boundaries
     * derived from the configured company timezone.
     */
    public function dailyPunches(
        string $date
    ): array
    {
        $startUtc =
            $date
            .
            ' 00:00:00';


        $endUtc =
            date(
                'Y-m-d H:i:s',
                strtotime(
                    $startUtc
                    .
                    ' +1 day'
                )
            );


        return $this->punchesBetween(
            $startUtc,
            $endUtc
        );
    }


    public function recent(
        int $limit = 100
    ): array
    {
        $stmt =
            $this->db->prepare(
                "
                SELECT
                    punches.*,
                    employees.employee_number,
                    employees.first_name,
                    employees.last_name,
                    employees.department

                FROM punches

                JOIN employees
                    ON employees.id = punches.employee_id

                ORDER BY punch_time DESC

                LIMIT :limit
                "
            );


        $stmt->bindValue(
            ':limit',
            $limit,
            PDO::PARAM_INT
        );


        $stmt->execute();


        return $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );
    }
}
