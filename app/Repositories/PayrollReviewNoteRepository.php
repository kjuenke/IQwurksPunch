<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class PayrollReviewNoteRepository
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
                INSERT INTO payroll_review_notes
                (
                    payroll_period_id,
                    note,
                    created_by_user_id
                )

                VALUES
                (
                    :payroll_period_id,
                    :note,
                    :created_by_user_id
                )
                "
            );


        $statement->execute(
            [
                'payroll_period_id' =>
                    $data['payroll_period_id'],

                'note' =>
                    $data['note'],

                'created_by_user_id' =>
                    $data['created_by_user_id']
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
                    payroll_review_notes.id,
                    payroll_review_notes.payroll_period_id,
                    payroll_review_notes.note,
                    payroll_review_notes.created_by_user_id,
                    payroll_review_notes.created_at,

                    users.username
                        AS created_by_username,

                    users.email
                        AS created_by_email

                FROM payroll_review_notes

                INNER JOIN users
                    ON users.id
                    =
                    payroll_review_notes.created_by_user_id

                WHERE
                    payroll_review_notes.payroll_period_id
                    =
                    :payroll_period_id

                ORDER BY
                    payroll_review_notes.created_at ASC,
                    payroll_review_notes.id ASC
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
                    payroll_review_notes.id,
                    payroll_review_notes.payroll_period_id,
                    payroll_review_notes.note,
                    payroll_review_notes.created_by_user_id,
                    payroll_review_notes.created_at,

                    users.username
                        AS created_by_username,

                    users.email
                        AS created_by_email

                FROM payroll_review_notes

                INNER JOIN users
                    ON users.id
                    =
                    payroll_review_notes.created_by_user_id

                WHERE payroll_review_notes.id = :id

                LIMIT 1
                "
            );


        $statement->execute(
            [
                'id' =>
                    $id
            ]
        );


        $note =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );


        return
            is_array(
                $note
            )
                ? $note
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

                FROM payroll_review_notes

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
                    payroll_review_notes.id,
                    payroll_review_notes.payroll_period_id,
                    payroll_review_notes.note,
                    payroll_review_notes.created_by_user_id,
                    payroll_review_notes.created_at,

                    users.username
                        AS created_by_username,

                    users.email
                        AS created_by_email

                FROM payroll_review_notes

                INNER JOIN users
                    ON users.id
                    =
                    payroll_review_notes.created_by_user_id

                WHERE
                    payroll_review_notes.payroll_period_id
                    =
                    :payroll_period_id

                ORDER BY
                    payroll_review_notes.created_at DESC,
                    payroll_review_notes.id DESC

                LIMIT 1
                "
            );


        $statement->execute(
            [
                'payroll_period_id' =>
                    $payrollPeriodId
            ]
        );


        $note =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );


        return
            is_array(
                $note
            )
                ? $note
                : null;
    }
}
