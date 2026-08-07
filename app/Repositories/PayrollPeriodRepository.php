<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class PayrollPeriodRepository
{
    private PDO $db;

    private ?bool $removalLifecycleColumnsAvailable = null;


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
        $statement =
            $this->db->query(
                "
                SELECT
                    payroll_periods.*,

                    created_user.username
                        AS created_by_username,

                    reviewed_user.username
                        AS reviewed_by_username,

                    approved_user.username
                        AS approved_by_username,

                    locked_user.username
                        AS locked_by_username

                FROM payroll_periods

                INNER JOIN users AS created_user
                    ON created_user.id
                    =
                    payroll_periods.created_by_user_id

                LEFT JOIN users AS reviewed_user
                    ON reviewed_user.id
                    =
                    payroll_periods.reviewed_by_user_id

                LEFT JOIN users AS approved_user
                    ON approved_user.id
                    =
                    payroll_periods.approved_by_user_id

                LEFT JOIN users AS locked_user
                    ON locked_user.id
                    =
                    payroll_periods.locked_by_user_id

                ORDER BY
                    payroll_periods.start_date DESC,
                    payroll_periods.end_date DESC,
                    payroll_periods.id DESC
                "
            );


        if ($statement === false) {

            return [];
        }


        return $statement->fetchAll(
            PDO::FETCH_ASSOC
        );
    }


    /**
     * @return array<int,array<string,mixed>>
     */
    public function active(): array
    {
        if (
            !$this->removalLifecycleColumnsAvailable()
        ) {
            return $this->all();
        }


        return
            array_values(
                array_filter(
                    $this->all(),
                    static function (
                        array $period
                    ): bool {
                        return
                            (
                                $period['archived_at']
                                ??
                                null
                            )
                            ===
                            null
                            &&
                            (
                                $period['voided_at']
                                ??
                                null
                            )
                            ===
                            null;
                    }
                )
            );
    }


    /**
     * @return array<int,array<string,mixed>>
     */
    public function removed(): array
    {
        if (
            !$this->removalLifecycleColumnsAvailable()
        ) {
            return [];
        }


        return
            array_values(
                array_filter(
                    $this->all(),
                    static function (
                        array $period
                    ): bool {
                        return
                            (
                                $period['archived_at']
                                ??
                                null
                            )
                            !==
                            null
                            ||
                            (
                                $period['voided_at']
                                ??
                                null
                            )
                            !==
                            null;
                    }
                )
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
                    payroll_periods.*,

                    created_user.username
                        AS created_by_username,

                    reviewed_user.username
                        AS reviewed_by_username,

                    approved_user.username
                        AS approved_by_username,

                    locked_user.username
                        AS locked_by_username

                FROM payroll_periods

                INNER JOIN users AS created_user
                    ON created_user.id
                    =
                    payroll_periods.created_by_user_id

                LEFT JOIN users AS reviewed_user
                    ON reviewed_user.id
                    =
                    payroll_periods.reviewed_by_user_id

                LEFT JOIN users AS approved_user
                    ON approved_user.id
                    =
                    payroll_periods.approved_by_user_id

                LEFT JOIN users AS locked_user
                    ON locked_user.id
                    =
                    payroll_periods.locked_by_user_id

                WHERE payroll_periods.id = :id

                LIMIT 1
                "
            );


        $statement->execute(
            [
                'id' =>
                    $id
            ]
        );


        $period =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );


        return
            is_array(
                $period
            )
                ? $period
                : null;
    }


    public function exists(
        int $id
    ): bool
    {
        $statement =
            $this->db->prepare(
                "
                SELECT 1

                FROM payroll_periods

                WHERE id = :id

                LIMIT 1
                "
            );


        $statement->execute(
            [
                'id' =>
                    $id
            ]
        );


        return
            $statement->fetchColumn()
            !==
            false;
    }


    public function create(
        array $data
    ): int
    {
        $statement =
            $this->db->prepare(
                "
                INSERT INTO payroll_periods
                (
                    period_name,
                    start_date,
                    end_date,
                    status,
                    created_by_user_id
                )

                VALUES
                (
                    :period_name,
                    :start_date,
                    :end_date,
                    'open',
                    :created_by_user_id
                )
                "
            );


        $statement->execute(
            [
                'period_name' =>
                    $data['period_name'],

                'start_date' =>
                    $data['start_date'],

                'end_date' =>
                    $data['end_date'],

                'created_by_user_id' =>
                    $data['created_by_user_id']
            ]
        );


        return
            (int)$this->db
                ->lastInsertId();
    }


    public function updateDraft(
        int $id,
        array $data
    ): bool
    {
        $activeLifecyclePredicate =
            $this->activeLifecyclePredicate();

        $statement =
            $this->db->prepare(
                "
                UPDATE payroll_periods

                SET
                    period_name =
                        :period_name,

                    start_date =
                        :start_date,

                    end_date =
                        :end_date,

                    updated_at =
                        CURRENT_TIMESTAMP

                WHERE id =
                    :id

                  AND status =
                    'open'

                  AND
                    {$activeLifecyclePredicate}
                "
            );


        $statement->execute(
            [
                'id' =>
                    $id,

                'period_name' =>
                    $data['period_name'],

                'start_date' =>
                    $data['start_date'],

                'end_date' =>
                    $data['end_date']
            ]
        );


        return
            $statement->rowCount()
            ===
            1;
    }


    public function findOverlapping(
        string $startDate,
        string $endDate,
        ?int $excludePeriodId = null
    ): ?array
    {


        $activeLifecyclePredicate =
            $this->activeLifecyclePredicate();

        $sql =
            "
            SELECT
                id,
                period_name,
                start_date,
                end_date,
                status

            FROM payroll_periods

            WHERE
                {$activeLifecyclePredicate}
                AND start_date <= :end_date
              AND end_date >= :start_date
            ";


        $parameters = [
            'start_date' =>
                $startDate,

            'end_date' =>
                $endDate
        ];


        if ($excludePeriodId !== null) {

            $sql .=
                "
                AND id != :exclude_period_id
                ";


            $parameters['exclude_period_id'] =
                $excludePeriodId;
        }


        $sql .=
            "
            ORDER BY
                start_date ASC,
                end_date ASC,
                id ASC

            LIMIT 1
            ";


        $statement =
            $this->db->prepare(
                $sql
            );


        $statement->execute(
            $parameters
        );


        $period =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );


        return
            is_array(
                $period
            )
                ? $period
                : null;
    }


    public function findContainingDate(
        string $localDate
    ): ?array
    {


        $activeLifecyclePredicate =
            $this->activeLifecyclePredicate();

        $statement =
            $this->db->prepare(
                "
                SELECT
                    id,
                    period_name,
                    start_date,
                    end_date,
                    status,
                    created_by_user_id,
                    reviewed_by_user_id,
                    approved_by_user_id,
                    locked_by_user_id,
                    created_at,
                    review_started_at,
                    approved_at,
                    locked_at,
                    updated_at

                FROM payroll_periods

                WHERE
                    {$activeLifecyclePredicate}
                    AND start_date <= :local_date
                  AND end_date >= :local_date

                ORDER BY
                    start_date ASC,
                    id ASC

                LIMIT 1
                "
            );


        $statement->execute(
            [
                'local_date' =>
                    $localDate
            ]
        );


        $period =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );


        return
            is_array(
                $period
            )
                ? $period
                : null;
    }


    public function findProtectedContainingDate(
        string $localDate
    ): ?array
    {


        $activeLifecyclePredicate =
            $this->activeLifecyclePredicate();

        $statement =
            $this->db->prepare(
                "
                SELECT
                    id,
                    period_name,
                    start_date,
                    end_date,
                    status,
                    approved_by_user_id,
                    approved_at,
                    locked_by_user_id,
                    locked_at

                FROM payroll_periods

                WHERE
                    {$activeLifecyclePredicate}
                    AND start_date <= :local_date
                  AND end_date >= :local_date

                  AND status IN
                  (
                      'approved',
                      'locked'
                  )

                ORDER BY
                    CASE status

                        WHEN 'locked'
                        THEN 1

                        WHEN 'approved'
                        THEN 2

                        ELSE 3

                    END,

                    start_date ASC,
                    id ASC

                LIMIT 1
                "
            );


        $statement->execute(
            [
                'local_date' =>
                    $localDate
            ]
        );


        $period =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );


        return
            is_array(
                $period
            )
                ? $period
                : null;
    }


    public function beginReview(
        int $id,
        int $reviewedByUserId
    ): bool
    {
        $statement =
            $this->db->prepare(
                "
                UPDATE payroll_periods

                SET
                    status =
                        'under_review',

                    reviewed_by_user_id =
                        :reviewed_by_user_id,

                    review_started_at =
                        CURRENT_TIMESTAMP,

                    approved_by_user_id =
                        NULL,

                    approved_at =
                        NULL,

                    locked_by_user_id =
                        NULL,

                    locked_at =
                        NULL,

                    updated_at =
                        CURRENT_TIMESTAMP

                WHERE id =
                    :id

                  AND status =
                    'open'
                "
            );


        $statement->execute(
            [
                'reviewed_by_user_id' =>
                    $reviewedByUserId,

                'id' =>
                    $id
            ]
        );


        return
            $statement->rowCount()
            ===
            1;
    }


    public function returnToOpen(
        int $id
    ): bool
    {
        $statement =
            $this->db->prepare(
                "
                UPDATE payroll_periods

                SET
                    status =
                        'open',

                    reviewed_by_user_id =
                        NULL,

                    review_started_at =
                        NULL,

                    approved_by_user_id =
                        NULL,

                    approved_at =
                        NULL,

                    locked_by_user_id =
                        NULL,

                    locked_at =
                        NULL,

                    updated_at =
                        CURRENT_TIMESTAMP

                WHERE id =
                    :id

                  AND status =
                    'under_review'
                "
            );


        $statement->execute(
            [
                'id' =>
                    $id
            ]
        );


        return
            $statement->rowCount()
            ===
            1;
    }


    public function approve(
        int $id,
        int $approvedByUserId
    ): bool
    {
        $statement =
            $this->db->prepare(
                "
                UPDATE payroll_periods

                SET
                    status =
                        'approved',

                    approved_by_user_id =
                        :approved_by_user_id,

                    approved_at =
                        CURRENT_TIMESTAMP,

                    locked_by_user_id =
                        NULL,

                    locked_at =
                        NULL,

                    updated_at =
                        CURRENT_TIMESTAMP

                WHERE id =
                    :id

                  AND status =
                    'under_review'
                "
            );


        $statement->execute(
            [
                'approved_by_user_id' =>
                    $approvedByUserId,

                'id' =>
                    $id
            ]
        );


        return
            $statement->rowCount()
            ===
            1;
    }


    public function lock(
        int $id,
        int $lockedByUserId
    ): bool
    {
        $statement =
            $this->db->prepare(
                "
                UPDATE payroll_periods

                SET
                    status =
                        'locked',

                    locked_by_user_id =
                        :locked_by_user_id,

                    locked_at =
                        CURRENT_TIMESTAMP,

                    updated_at =
                        CURRENT_TIMESTAMP

                WHERE id =
                    :id

                  AND status =
                    'approved'

                  AND approved_by_user_id
                    IS NOT NULL

                  AND approved_at
                    IS NOT NULL
                "
            );


        $statement->execute(
            [
                'locked_by_user_id' =>
                    $lockedByUserId,

                'id' =>
                    $id
            ]
        );


        return
            $statement->rowCount()
            ===
            1;
    }


    public function reopenToReview(
        int $id,
        int $reviewedByUserId
    ): bool
    {
        $statement =
            $this->db->prepare(
                "
                UPDATE payroll_periods

                SET
                    status =
                        'under_review',

                    reviewed_by_user_id =
                        :reviewed_by_user_id,

                    review_started_at =
                        CURRENT_TIMESTAMP,

                    approved_by_user_id =
                        NULL,

                    approved_at =
                        NULL,

                    locked_by_user_id =
                        NULL,

                    locked_at =
                        NULL,

                    updated_at =
                        CURRENT_TIMESTAMP

                WHERE id =
                    :id

                  AND status IN
                  (
                      'approved',
                      'locked'
                  )
                "
            );


        $statement->execute(
            [
                'reviewed_by_user_id' =>
                    $reviewedByUserId,

                'id' =>
                    $id
            ]
        );


        return
            $statement->rowCount()
            ===
            1;
    }


    public function count(): int
    {
        $statement =
            $this->db->query(
                "
                SELECT COUNT(*)

                FROM payroll_periods
                "
            );


        if ($statement === false) {

            return 0;
        }


        return
            (int)$statement
                ->fetchColumn();
    }


    private function activeLifecyclePredicate(): string
    {
        if (
            !$this->removalLifecycleColumnsAvailable()
        ) {
            return '1 = 1';
        }


        return
            'archived_at IS NULL'
            .
            ' AND '
            .
            'voided_at IS NULL';
    }


    private function removalLifecycleColumnsAvailable(): bool
    {
        if (
            $this->removalLifecycleColumnsAvailable
            !==
            null
        ) {
            return
                $this->removalLifecycleColumnsAvailable;
        }


        $statement =
            $this->db->query(
                '
                PRAGMA table_info(
                    payroll_periods
                )
                '
            );


        if ($statement === false) {
            $this->removalLifecycleColumnsAvailable =
                false;


            return false;
        }


        $columns =
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            );


        $names = [];


        foreach ($columns as $column) {
            $name =
                trim(
                    (string)(
                        $column['name']
                        ??
                        ''
                    )
                );


            if ($name !== '') {
                $names[] =
                    $name;
            }
        }


        $this->removalLifecycleColumnsAvailable =
            in_array(
                'archived_at',
                $names,
                true
            )
            &&
            in_array(
                'voided_at',
                $names,
                true
            );


        return
            $this->removalLifecycleColumnsAvailable;
    }

}
