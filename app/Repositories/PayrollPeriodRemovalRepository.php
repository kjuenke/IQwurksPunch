<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

class PayrollPeriodRemovalRepository
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
     * @return array<string,mixed>|null
     */
    public function find(
        int $payrollPeriodId
    ): ?array
    {
        $statement =
            $this->db->prepare(
                '
                SELECT *
                FROM payroll_periods

                WHERE id = :id

                LIMIT 1
                '
            );


        $statement->execute(
            [
                'id' =>
                    $payrollPeriodId
            ]
        );


        $period =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );


        return $period ?: null;
    }


    /**
     * @return array<string,int>
     */
    public function dependencySummary(
        int $payrollPeriodId
    ): array
    {
        $statement =
            $this->db->prepare(
                '
                SELECT
                    (
                        SELECT COUNT(*)
                        FROM payroll_period_history

                        WHERE payroll_period_id =
                            :history_period_id
                    ) AS history_count,

                    (
                        SELECT COUNT(*)
                        FROM payroll_period_history

                        WHERE payroll_period_id =
                            :created_period_id

                          AND action =
                            "created"
                    ) AS created_history_count,

                    (
                        SELECT COUNT(*)
                        FROM payroll_review_notes

                        WHERE payroll_period_id =
                            :note_period_id
                    ) AS review_note_count,

                    (
                        SELECT COUNT(*)
                        FROM payroll_exception_resolutions

                        WHERE payroll_period_id =
                            :exception_period_id
                    ) AS exception_count,

                    (
                        SELECT COUNT(*)
                        FROM payroll_exception_resolutions

                        WHERE payroll_period_id =
                            :resolution_period_id

                          AND resolution_status IN
                            (
                                "resolved",
                                "accepted"
                            )
                    ) AS resolution_count
                '
            );


        $statement->execute(
            [
                'history_period_id' =>
                    $payrollPeriodId,

                'created_period_id' =>
                    $payrollPeriodId,

                'note_period_id' =>
                    $payrollPeriodId,

                'exception_period_id' =>
                    $payrollPeriodId,

                'resolution_period_id' =>
                    $payrollPeriodId
            ]
        );


        $summary =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );


        return [
            'history_count' =>
                (int)(
                    $summary['history_count']
                    ??
                    0
                ),

            'created_history_count' =>
                (int)(
                    $summary['created_history_count']
                    ??
                    0
                ),

            'review_note_count' =>
                (int)(
                    $summary['review_note_count']
                    ??
                    0
                ),

            'exception_count' =>
                (int)(
                    $summary['exception_count']
                    ??
                    0
                ),

            'resolution_count' =>
                (int)(
                    $summary['resolution_count']
                    ??
                    0
                )
        ];
    }


    public function markArchived(
        int $payrollPeriodId,
        int $actingUserId,
        string $reason
    ): bool
    {
        $statement =
            $this->db->prepare(
                '
                UPDATE payroll_periods

                SET
                    archived_at =
                        CURRENT_TIMESTAMP,

                    archived_by_user_id =
                        :acting_user_id,

                    archive_reason =
                        :reason,

                    updated_at =
                        CURRENT_TIMESTAMP

                WHERE id =
                    :id

                  AND archived_at
                    IS NULL

                  AND voided_at
                    IS NULL
                '
            );


        $statement->execute(
            [
                'acting_user_id' =>
                    $actingUserId,

                'reason' =>
                    $reason,

                'id' =>
                    $payrollPeriodId
            ]
        );


        return $statement->rowCount() > 0;
    }


    public function markVoided(
        int $payrollPeriodId,
        int $actingUserId,
        string $reason
    ): bool
    {
        $statement =
            $this->db->prepare(
                '
                UPDATE payroll_periods

                SET
                    voided_at =
                        CURRENT_TIMESTAMP,

                    voided_by_user_id =
                        :acting_user_id,

                    void_reason =
                        :reason,

                    updated_at =
                        CURRENT_TIMESTAMP

                WHERE id =
                    :id

                  AND voided_at
                    IS NULL

                  AND archived_at
                    IS NULL
                '
            );


        $statement->execute(
            [
                'acting_user_id' =>
                    $actingUserId,

                'reason' =>
                    $reason,

                'id' =>
                    $payrollPeriodId
            ]
        );


        return $statement->rowCount() > 0;
    }


    public function deleteHistory(
        int $payrollPeriodId
    ): int
    {
        $statement =
            $this->db->prepare(
                '
                DELETE FROM payroll_period_history

                WHERE payroll_period_id =
                    :payroll_period_id
                '
            );


        $statement->execute(
            [
                'payroll_period_id' =>
                    $payrollPeriodId
            ]
        );


        return $statement->rowCount();
    }


    public function deletePeriod(
        int $payrollPeriodId
    ): bool
    {
        $statement =
            $this->db->prepare(
                '
                DELETE FROM payroll_periods

                WHERE id =
                    :id
                '
            );


        $statement->execute(
            [
                'id' =>
                    $payrollPeriodId
            ]
        );


        return $statement->rowCount() > 0;
    }
}
