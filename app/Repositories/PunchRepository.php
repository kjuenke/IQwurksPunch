<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

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
        $statement =
            $this->db->prepare(
                "
                INSERT INTO punches
                (
                    employee_id,
                    punch_time,
                    punch_type,
                    source,
                    notes,
                    correction_reason
                )

                VALUES
                (
                    :employee_id,
                    CURRENT_TIMESTAMP,
                    :punch_type,
                    'kiosk',
                    '',
                    ''
                )
                "
            );


        return $statement->execute(
            [
                'employee_id' =>
                    $employeeId,

                'punch_type' =>
                    $type
            ]
        );
    }


    public function createManual(
        int $employeeId,
        string $punchTimeUtc,
        string $punchType,
        string $notes,
        int $userId,
        string $reason
    ): int
    {
        $statement =
            $this->db->prepare(
                "
                INSERT INTO punches
                (
                    employee_id,
                    punch_time,
                    punch_type,
                    source,
                    notes,
                    corrected_at,
                    corrected_by_user_id,
                    correction_reason
                )

                VALUES
                (
                    :employee_id,
                    :punch_time,
                    :punch_type,
                    'manual',
                    :notes,
                    CURRENT_TIMESTAMP,
                    :corrected_by_user_id,
                    :correction_reason
                )
                "
            );


        $statement->execute(
            [
                'employee_id' =>
                    $employeeId,

                'punch_time' =>
                    $punchTimeUtc,

                'punch_type' =>
                    $punchType,

                'notes' =>
                    $notes,

                'corrected_by_user_id' =>
                    $userId,

                'correction_reason' =>
                    $reason
            ]
        );


        return (int)$this->db
            ->lastInsertId();
    }


    public function find(
        int $id
    ): ?array
    {
        $statement =
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

                WHERE punches.id = :id

                LIMIT 1
                "
            );


        $statement->execute(
            [
                'id' =>
                    $id
            ]
        );


        $punch =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );


        return $punch ?: null;
    }


    public function updateManual(
        int $id,
        string $punchTimeUtc,
        string $punchType,
        string $notes,
        int $userId,
        string $reason
    ): bool
    {
        $statement =
            $this->db->prepare(
                "
                UPDATE punches

                SET
                    punch_time = :punch_time,
                    punch_type = :punch_type,
                    source = 'manual',
                    notes = :notes,
                    corrected_at = CURRENT_TIMESTAMP,
                    corrected_by_user_id = :corrected_by_user_id,
                    correction_reason = :correction_reason

                WHERE id = :id
                "
            );


        return $statement->execute(
            [
                'punch_time' =>
                    $punchTimeUtc,

                'punch_type' =>
                    $punchType,

                'notes' =>
                    $notes,

                'corrected_by_user_id' =>
                    $userId,

                'correction_reason' =>
                    $reason,

                'id' =>
                    $id
            ]
        );
    }


    public function delete(
        int $id
    ): bool
    {
        $statement =
            $this->db->prepare(
                "
                DELETE FROM punches

                WHERE id = :id
                "
            );


        return $statement->execute(
            [
                'id' =>
                    $id
            ]
        );
    }


    public function latest(
        int $employeeId
    ): ?array
    {
        $statement =
            $this->db->prepare(
                "
                SELECT *
                FROM punches

                WHERE employee_id = :employee_id

                ORDER BY
                    punch_time DESC,
                    id DESC

                LIMIT 1
                "
            );


        $statement->execute(
            [
                'employee_id' =>
                    $employeeId
            ]
        );


        $result =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );


        return $result ?: null;
    }


    /**
     * @return array<int,array<string,mixed>>
     */
    public function employeePunches(
        int $employeeId
    ): array
    {
        $statement =
            $this->db->prepare(
                "
                SELECT *
                FROM punches

                WHERE employee_id = :employee_id

                ORDER BY
                    punch_time ASC,
                    id ASC
                "
            );


        $statement->execute(
            [
                'employee_id' =>
                    $employeeId
            ]
        );


        return $statement->fetchAll(
            PDO::FETCH_ASSOC
        );
    }


    /**
     * @return array<int,array<string,mixed>>
     */
    public function employeePunchesExcept(
        int $employeeId,
        int $excludedPunchId
    ): array
    {
        $statement =
            $this->db->prepare(
                "
                SELECT *
                FROM punches

                WHERE employee_id = :employee_id
                  AND id <> :excluded_punch_id

                ORDER BY
                    punch_time ASC,
                    id ASC
                "
            );


        $statement->execute(
            [
                'employee_id' =>
                    $employeeId,

                'excluded_punch_id' =>
                    $excludedPunchId
            ]
        );


        return $statement->fetchAll(
            PDO::FETCH_ASSOC
        );
    }


    /**
     * Returns punches whose UTC timestamps fall within:
     *
     *     start <= punch_time < end
     *
     * @return array<int,array<string,mixed>>
     */
    public function punchesBetween(
        string $startUtc,
        string $endUtc
    ): array
    {
        $statement =
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
                    punches.punch_time ASC,
                    punches.id ASC
                "
            );


        $statement->execute(
            [
                'start_utc' =>
                    $startUtc,

                'end_utc' =>
                    $endUtc
            ]
        );


        return $statement->fetchAll(
            PDO::FETCH_ASSOC
        );
    }


    /**
     * Legacy UTC-calendar-date query.
     *
     * New payroll reporting should use punchesBetween() with UTC boundaries
     * derived from the configured company timezone.
     *
     * @return array<int,array<string,mixed>>
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


    /**
     * @return array<int,array<string,mixed>>
     */
    public function recent(
        int $limit = 100
    ): array
    {
        if ($limit < 1) {

            throw new RuntimeException(
                'Punch result limit must be greater than zero.'
            );
        }


        $statement =
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

                ORDER BY
                    punches.punch_time DESC,
                    punches.id DESC

                LIMIT :limit
                "
            );


        $statement->bindValue(
            ':limit',
            $limit,
            PDO::PARAM_INT
        );


        $statement->execute();


        return $statement->fetchAll(
            PDO::FETCH_ASSOC
        );
    }
}
