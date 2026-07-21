<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class PunchCorrectionHistoryRepository
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
        ?int $punchId,
        int $employeeId,
        string $employeeNumber,
        string $employeeName,
        string $action,
        ?string $oldPunchTime,
        ?string $oldPunchType,
        ?string $newPunchTime,
        ?string $newPunchType,
        string $reason,
        int $userId
    ): int
    {
        $statement =
            $this->db->prepare(
                "
                INSERT INTO punch_correction_history
                (
                    punch_id,
                    employee_id,
                    employee_number,
                    employee_name,
                    action,
                    old_punch_time,
                    old_punch_type,
                    new_punch_time,
                    new_punch_type,
                    reason,
                    user_id
                )

                VALUES
                (
                    :punch_id,
                    :employee_id,
                    :employee_number,
                    :employee_name,
                    :action,
                    :old_punch_time,
                    :old_punch_type,
                    :new_punch_time,
                    :new_punch_type,
                    :reason,
                    :user_id
                )
                "
            );


        $statement->execute(
            [
                'punch_id' =>
                    $punchId,

                'employee_id' =>
                    $employeeId,

                'employee_number' =>
                    $employeeNumber,

                'employee_name' =>
                    $employeeName,

                'action' =>
                    $action,

                'old_punch_time' =>
                    $oldPunchTime,

                'old_punch_type' =>
                    $oldPunchType,

                'new_punch_time' =>
                    $newPunchTime,

                'new_punch_type' =>
                    $newPunchType,

                'reason' =>
                    $reason,

                'user_id' =>
                    $userId
            ]
        );


        return (int)$this->db
            ->lastInsertId();
    }


    /**
     * @return array<int,array<string,mixed>>
     */
    public function forEmployee(
        int $employeeId,
        int $limit = 100
    ): array
    {
        $statement =
            $this->db->prepare(
                "
                SELECT
                    punch_correction_history.*,
                    users.username

                FROM punch_correction_history

                LEFT JOIN users
                    ON users.id =
                        punch_correction_history.user_id

                WHERE punch_correction_history.employee_id =
                    :employee_id

                ORDER BY
                    punch_correction_history.created_at DESC,
                    punch_correction_history.id DESC

                LIMIT :limit
                "
            );


        $statement->bindValue(
            ':employee_id',
            $employeeId,
            PDO::PARAM_INT
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


    /**
     * @return array<int,array<string,mixed>>
     */
    public function forPunch(
        int $punchId
    ): array
    {
        $statement =
            $this->db->prepare(
                "
                SELECT
                    punch_correction_history.*,
                    users.username

                FROM punch_correction_history

                LEFT JOIN users
                    ON users.id =
                        punch_correction_history.user_id

                WHERE punch_correction_history.punch_id =
                    :punch_id

                ORDER BY
                    punch_correction_history.created_at DESC,
                    punch_correction_history.id DESC
                "
            );


        $statement->execute(
            [
                'punch_id' =>
                    $punchId
            ]
        );


        return $statement->fetchAll(
            PDO::FETCH_ASSOC
        );
    }
}
