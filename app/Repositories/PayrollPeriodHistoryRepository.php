<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class PayrollPeriodHistoryRepository
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
        array $data
    ): int
    {
        $statement =
            $this->db->prepare(
                "
                INSERT INTO payroll_period_history
                (
                    payroll_period_id,
                    action,
                    previous_status,
                    new_status,
                    reason,
                    user_id
                )

                VALUES
                (
                    :payroll_period_id,
                    :action,
                    :previous_status,
                    :new_status,
                    :reason,
                    :user_id
                )
                "
            );


        $statement->execute(
            [
                'payroll_period_id' =>
                    $data['payroll_period_id'],

                'action' =>
                    $data['action'],

                'previous_status' =>
                    $data['previous_status']
                    ??
                    null,

                'new_status' =>
                    $data['new_status'],

                'reason' =>
                    $data['reason']
                    ??
                    null,

                'user_id' =>
                    $data['user_id']
            ]
        );


        return
            (int)$this->db
                ->lastInsertId();
    }


    /**
     * @return array<int,array<string,mixed>>
     */
    public function allForPeriod(
        int $payrollPeriodId
    ): array
    {
        $statement =
            $this->db->prepare(
                "
                SELECT
                    payroll_period_history.id,
                    payroll_period_history.payroll_period_id,
                    payroll_period_history.action,
                    payroll_period_history.previous_status,
                    payroll_period_history.new_status,
                    payroll_period_history.reason,
                    payroll_period_history.user_id,
                    payroll_period_history.created_at,

                    users.username
                        AS user_username,

                    users.email
                        AS user_email

                FROM payroll_period_history

                INNER JOIN users
                    ON users.id
                    =
                    payroll_period_history.user_id

                WHERE
                    payroll_period_history.payroll_period_id
                    =
                    :payroll_period_id

                ORDER BY
                    payroll_period_history.created_at ASC,
                    payroll_period_history.id ASC
                "
            );


        $statement->execute(
            [
                'payroll_period_id' =>
                    $payrollPeriodId
            ]
        );


        return $statement->fetchAll(
            PDO::FETCH_ASSOC
        );
    }


    public function find(
        int $id
    ): ?array
    {
        $statement =
            $this->db->prepare(
                "
                SELECT
                    payroll_period_history.id,
                    payroll_period_history.payroll_period_id,
                    payroll_period_history.action,
                    payroll_period_history.previous_status,
                    payroll_period_history.new_status,
                    payroll_period_history.reason,
                    payroll_period_history.user_id,
                    payroll_period_history.created_at,

                    users.username
                        AS user_username,

                    users.email
                        AS user_email

                FROM payroll_period_history

                INNER JOIN users
                    ON users.id
                    =
                    payroll_period_history.user_id

                WHERE payroll_period_history.id = :id

                LIMIT 1
                "
            );


        $statement->execute(
            [
                'id' =>
                    $id
            ]
        );


        $history =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );


        return
            is_array(
                $history
            )
                ? $history
                : null;
    }


    public function countForPeriod(
        int $payrollPeriodId
    ): int
    {
        $statement =
            $this->db->prepare(
                "
                SELECT COUNT(*)

                FROM payroll_period_history

                WHERE payroll_period_id
                    =
                    :payroll_period_id
                "
            );


        $statement->execute(
            [
                'payroll_period_id' =>
                    $payrollPeriodId
            ]
        );


        return
            (int)$statement
                ->fetchColumn();
    }


    public function latestForPeriod(
        int $payrollPeriodId
    ): ?array
    {
        $statement =
            $this->db->prepare(
                "
                SELECT
                    payroll_period_history.id,
                    payroll_period_history.payroll_period_id,
                    payroll_period_history.action,
                    payroll_period_history.previous_status,
                    payroll_period_history.new_status,
                    payroll_period_history.reason,
                    payroll_period_history.user_id,
                    payroll_period_history.created_at,

                    users.username
                        AS user_username,

                    users.email
                        AS user_email

                FROM payroll_period_history

                INNER JOIN users
                    ON users.id
                    =
                    payroll_period_history.user_id

                WHERE
                    payroll_period_history.payroll_period_id
                    =
                    :payroll_period_id

                ORDER BY
                    payroll_period_history.created_at DESC,
                    payroll_period_history.id DESC

                LIMIT 1
                "
            );


        $statement->execute(
            [
                'payroll_period_id' =>
                    $payrollPeriodId
            ]
        );


        $history =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );


        return
            is_array(
                $history
            )
                ? $history
                : null;
    }
}
