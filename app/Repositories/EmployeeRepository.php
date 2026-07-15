<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

class EmployeeRepository
{
    private PDO $db;


    public function __construct(
        PDO $db
    )
    {
        $this->db =
            $db;
    }


    /**
     * @return array<int,array<string,mixed>>
     */
    public function all(): array
    {
        $stmt =
            $this->db->query(
                "
                SELECT *
                FROM employees

                ORDER BY
                    active DESC,
                    last_name COLLATE NOCASE ASC,
                    first_name COLLATE NOCASE ASC
                "
            );


        return $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );
    }


    public function find(
        int $id
    ): ?array
    {
        $stmt =
            $this->db->prepare(
                "
                SELECT *
                FROM employees

                WHERE id = :id

                LIMIT 1
                "
            );


        $stmt->execute(
            [
                'id' =>
                    $id
            ]
        );


        $employee =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        return $employee ?: null;
    }


    public function create(
        array $data
    ): int
    {
        $stmt =
            $this->db->prepare(
                "
                INSERT INTO employees
                (
                    employee_number,
                    first_name,
                    last_name,
                    pin_hash,
                    department,
                    active,
                    notes
                )

                VALUES
                (
                    :employee_number,
                    :first_name,
                    :last_name,
                    :pin_hash,
                    :department,
                    :active,
                    :notes
                )
                "
            );


        $stmt->execute(
            [
                'employee_number' =>
                    $data['employee_number'],

                'first_name' =>
                    $data['first_name'],

                'last_name' =>
                    $data['last_name'],

                'pin_hash' =>
                    $data['pin_hash'],

                'department' =>
                    $data['department'],

                'active' =>
                    $data['active'],

                'notes' =>
                    $data['notes']
            ]
        );


        return (int)$this->db
            ->lastInsertId();
    }


    public function update(
        int $id,
        array $data
    ): bool
    {
        $stmt =
            $this->db->prepare(
                "
                UPDATE employees

                SET
                    employee_number = :employee_number,
                    first_name = :first_name,
                    last_name = :last_name,
                    department = :department,
                    active = :active,
                    notes = :notes,
                    updated_at = CURRENT_TIMESTAMP

                WHERE id = :id
                "
            );


        return $stmt->execute(
            [
                'employee_number' =>
                    $data['employee_number'],

                'first_name' =>
                    $data['first_name'],

                'last_name' =>
                    $data['last_name'],

                'department' =>
                    $data['department'],

                'active' =>
                    $data['active'],

                'notes' =>
                    $data['notes'],

                'id' =>
                    $id
            ]
        );
    }


    /**
     * Kept for compatibility with existing service calls.
     */
    public function updateDetails(
        int $id,
        array $data
    ): bool
    {
        return $this->update(
            $id,
            $data
        );
    }


    public function activate(
        int $id
    ): bool
    {
        return $this->setActive(
            $id,
            true
        );
    }


    public function deactivate(
        int $id
    ): bool
    {
        return $this->setActive(
            $id,
            false
        );
    }


    public function hasPunchHistory(
        int $id
    ): bool
    {
        $stmt =
            $this->db->prepare(
                "
                SELECT COUNT(*)
                FROM punches

                WHERE employee_id = :employee_id
                "
            );


        $stmt->execute(
            [
                'employee_id' =>
                    $id
            ]
        );


        return (int)$stmt->fetchColumn() > 0;
    }


    public function punchCount(
        int $id
    ): int
    {
        $stmt =
            $this->db->prepare(
                "
                SELECT COUNT(*)
                FROM punches

                WHERE employee_id = :employee_id
                "
            );


        $stmt->execute(
            [
                'employee_id' =>
                    $id
            ]
        );


        return (int)$stmt->fetchColumn();
    }


    public function delete(
        int $id
    ): bool
    {
        $stmt =
            $this->db->prepare(
                "
                DELETE FROM employees

                WHERE id = :id
                "
            );


        return $stmt->execute(
            [
                'id' =>
                    $id
            ]
        );
    }


    public function employeeNumberExists(
        string $employeeNumber,
        ?int $excludeId = null
    ): bool
    {
        $sql =
            "
            SELECT COUNT(*)
            FROM employees

            WHERE employee_number = :employee_number
            ";


        $parameters = [
            'employee_number' =>
                $employeeNumber
        ];


        if ($excludeId !== null) {

            $sql .=
                "
                AND id <> :exclude_id
                ";


            $parameters['exclude_id'] =
                $excludeId;
        }


        $stmt =
            $this->db->prepare(
                $sql
            );


        $stmt->execute(
            $parameters
        );


        return (int)$stmt->fetchColumn() > 0;
    }


    public function updatePin(
        int $id,
        string $pinHash
    ): bool
    {
        $stmt =
            $this->db->prepare(
                "
                UPDATE employees

                SET
                    pin_hash = :pin_hash,
                    updated_at = CURRENT_TIMESTAMP

                WHERE id = :id
                "
            );


        return $stmt->execute(
            [
                'pin_hash' =>
                    $pinHash,

                'id' =>
                    $id
            ]
        );
    }


    public function findByEmployeeNumber(
        string $employeeNumber
    ): ?array
    {
        $stmt =
            $this->db->prepare(
                "
                SELECT *
                FROM employees

                WHERE employee_number = :employee_number

                LIMIT 1
                "
            );


        $stmt->execute(
            [
                'employee_number' =>
                    $employeeNumber
            ]
        );


        $employee =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        return $employee ?: null;
    }


    private function setActive(
        int $id,
        bool $active
    ): bool
    {
        $stmt =
            $this->db->prepare(
                "
                UPDATE employees

                SET
                    active = :active,
                    updated_at = CURRENT_TIMESTAMP

                WHERE id = :id
                "
            );


        return $stmt->execute(
            [
                'active' =>
                    $active
                        ? 1
                        : 0,

                'id' =>
                    $id
            ]
        );
    }
}
