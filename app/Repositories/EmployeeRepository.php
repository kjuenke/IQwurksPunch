<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

class EmployeeRepository
{
    private PDO $db;


    public function __construct(PDO $db)
    {
        $this->db = $db;
    }


    public function all(): array
    {
        $stmt = $this->db->query(
            "
            SELECT *
            FROM employees
            ORDER BY last_name, first_name
            "
        );

        return $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );
    }


    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "
            SELECT *
            FROM employees
            WHERE id = ?
            "
        );

        $stmt->execute([$id]);

        $employee = $stmt->fetch(
            PDO::FETCH_ASSOC
        );

        return $employee ?: null;
    }


    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
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


        $stmt->execute($data);

        return (int)$this->db->lastInsertId();
    }


    public function update(
        int $id,
        array $data
    ): bool
    {
        $stmt = $this->db->prepare(
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
                ...$data,
                'id' => $id
            ]
        );
    }


    public function deactivate(
        int $id
    ): bool
    {
        $stmt = $this->db->prepare(
            "
            UPDATE employees
            SET active = 0,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
            "
        );


        return $stmt->execute([$id]);
    }

    public function employeeNumberExists(string $employeeNumber): bool
    {
        $stmt = $this->db->prepare(
            "
            SELECT COUNT(*)
            FROM employees
            WHERE employee_number = ?
            "
        );

        $stmt->execute([$employeeNumber]);

        return (int)$stmt->fetchColumn() > 0;
    }

    public function updatePin(
        int $id,
        string $pinHash
    ): bool
    {
        $stmt = $this->db->prepare(
            "
            UPDATE employees
            SET
                pin_hash = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
            "
        );

        return $stmt->execute([
            $pinHash,
            $id
        ]);
    }
    public function updateDetails(
        int $id,
        array $data
    ): bool
    {
        $stmt = $this->db->prepare(
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
                'employee_number' => $data['employee_number'],
                'first_name'      => $data['first_name'],
                'last_name'       => $data['last_name'],
                'department'      => $data['department'],
                'active'          => $data['active'],
                'notes'           => $data['notes'],
                'id'              => $id
            ]
        );
    }

    public function findByEmployeeNumber(
        string $employeeNumber
    ): ?array
    {
        $stmt = $this->db->prepare(
            "
            SELECT *
            FROM employees
            WHERE employee_number = ?
            LIMIT 1
            "
        );


        $stmt->execute(
            [
                $employeeNumber
            ]
        );


        $employee = $stmt->fetch();


        return $employee ?: null;
    }
}
