<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class PayrollExceptionResolutionRepository
{
    private PDO $db;


    public function __construct(
        PDO $db
    )
    {
        $this->db =
            $db;
    }


    public function createOrRefresh(
        array $data
    ): int
    {
        $statement =
            $this->db->prepare(
                "
                INSERT INTO payroll_exception_resolutions
                (
                    payroll_period_id,
                    employee_id,
                    exception_key,
                    exception_type,
                    exception_date,
                    description,
                    resolution_status
                )

                VALUES
                (
                    :payroll_period_id,
                    :employee_id,
                    :exception_key,
                    :exception_type,
                    :exception_date,
                    :description,
                    'open'
                )

                ON CONFLICT
                (
                    payroll_period_id,
                    exception_key
                )

                DO UPDATE SET
                    employee_id =
                        excluded.employee_id,

                    exception_type =
                        excluded.exception_type,

                    exception_date =
                        excluded.exception_date,

                    description =
                        excluded.description,

                    updated_at =
                        CURRENT_TIMESTAMP
                "
            );


        $statement->execute(
            [
                'payroll_period_id' =>
                    $data['payroll_period_id'],

                'employee_id' =>
                    $data['employee_id']
                    ??
                    null,

                'exception_key' =>
                    $data['exception_key'],

                'exception_type' =>
                    $data['exception_type'],

                'exception_date' =>
                    $data['exception_date']
                    ??
                    null,

                'description' =>
                    $data['description']
            ]
        );


        $exception =
            $this->findByPeriodAndKey(
                (int)$data['payroll_period_id'],
                (string)$data['exception_key']
            );


        if ($exception === null) {

            return 0;
        }


        return
            (int)$exception['id'];
    }


    public function find(
        int $id
    ): ?array
    {
        $statement =
            $this->db->prepare(
                "
                SELECT
                    payroll_exception_resolutions.id,
                    payroll_exception_resolutions.payroll_period_id,
                    payroll_exception_resolutions.employee_id,
                    payroll_exception_resolutions.exception_key,
                    payroll_exception_resolutions.exception_type,
                    payroll_exception_resolutions.exception_date,
                    payroll_exception_resolutions.description,
                    payroll_exception_resolutions.resolution_status,
                    payroll_exception_resolutions.resolution_note,
                    payroll_exception_resolutions.resolved_by_user_id,
                    payroll_exception_resolutions.resolved_at,
                    payroll_exception_resolutions.created_at,
                    payroll_exception_resolutions.updated_at,

                    employees.employee_number,
                    employees.first_name,
                    employees.last_name,

                    users.username
                        AS resolved_by_username,

                    users.email
                        AS resolved_by_email

                FROM payroll_exception_resolutions

                LEFT JOIN employees
                    ON employees.id
                    =
                    payroll_exception_resolutions.employee_id

                LEFT JOIN users
                    ON users.id
                    =
                    payroll_exception_resolutions.resolved_by_user_id

                WHERE
                    payroll_exception_resolutions.id
                    =
                    :id

                LIMIT 1
                "
            );


        $statement->execute(
            [
                'id' =>
                    $id
            ]
        );


        $exception =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );


        return
            is_array(
                $exception
            )
                ? $exception
                : null;
    }


    public function findByPeriodAndKey(
        int $payrollPeriodId,
        string $exceptionKey
    ): ?array
    {
        $statement =
            $this->db->prepare(
                "
                SELECT
                    id,
                    payroll_period_id,
                    employee_id,
                    exception_key,
                    exception_type,
                    exception_date,
                    description,
                    resolution_status,
                    resolution_note,
                    resolved_by_user_id,
                    resolved_at,
                    created_at,
                    updated_at

                FROM payroll_exception_resolutions

                WHERE payroll_period_id
                    =
                    :payroll_period_id

                  AND exception_key
                    =
                    :exception_key

                LIMIT 1
                "
            );


        $statement->execute(
            [
                'payroll_period_id' =>
                    $payrollPeriodId,

                'exception_key' =>
                    $exceptionKey
            ]
        );


        $exception =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );


        return
            is_array(
                $exception
            )
                ? $exception
                : null;
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
                    payroll_exception_resolutions.id,
                    payroll_exception_resolutions.payroll_period_id,
                    payroll_exception_resolutions.employee_id,
                    payroll_exception_resolutions.exception_key,
                    payroll_exception_resolutions.exception_type,
                    payroll_exception_resolutions.exception_date,
                    payroll_exception_resolutions.description,
                    payroll_exception_resolutions.resolution_status,
                    payroll_exception_resolutions.resolution_note,
                    payroll_exception_resolutions.resolved_by_user_id,
                    payroll_exception_resolutions.resolved_at,
                    payroll_exception_resolutions.created_at,
                    payroll_exception_resolutions.updated_at,

                    employees.employee_number,
                    employees.first_name,
                    employees.last_name,

                    users.username
                        AS resolved_by_username,

                    users.email
                        AS resolved_by_email

                FROM payroll_exception_resolutions

                LEFT JOIN employees
                    ON employees.id
                    =
                    payroll_exception_resolutions.employee_id

                LEFT JOIN users
                    ON users.id
                    =
                    payroll_exception_resolutions.resolved_by_user_id

                WHERE
                    payroll_exception_resolutions.payroll_period_id
                    =
                    :payroll_period_id

                ORDER BY
                    CASE
                        payroll_exception_resolutions.resolution_status

                        WHEN 'open'
                        THEN 1

                        WHEN 'resolved'
                        THEN 2

                        WHEN 'accepted'
                        THEN 3

                        ELSE 4
                    END,

                    payroll_exception_resolutions.exception_date ASC,
                    payroll_exception_resolutions.exception_type ASC,
                    payroll_exception_resolutions.id ASC
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


    /**
     * @return array<int,array<string,mixed>>
     */
    public function openForPeriod(
        int $payrollPeriodId
    ): array
    {
        $statement =
            $this->db->prepare(
                "
                SELECT
                    payroll_exception_resolutions.id,
                    payroll_exception_resolutions.payroll_period_id,
                    payroll_exception_resolutions.employee_id,
                    payroll_exception_resolutions.exception_key,
                    payroll_exception_resolutions.exception_type,
                    payroll_exception_resolutions.exception_date,
                    payroll_exception_resolutions.description,
                    payroll_exception_resolutions.resolution_status,
                    payroll_exception_resolutions.created_at,
                    payroll_exception_resolutions.updated_at,

                    employees.employee_number,
                    employees.first_name,
                    employees.last_name

                FROM payroll_exception_resolutions

                LEFT JOIN employees
                    ON employees.id
                    =
                    payroll_exception_resolutions.employee_id

                WHERE
                    payroll_exception_resolutions.payroll_period_id
                    =
                    :payroll_period_id

                  AND payroll_exception_resolutions.resolution_status
                    =
                    'open'

                ORDER BY
                    payroll_exception_resolutions.exception_date ASC,
                    payroll_exception_resolutions.exception_type ASC,
                    payroll_exception_resolutions.id ASC
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


    public function countForPeriod(
        int $payrollPeriodId
    ): int
    {
        $statement =
            $this->db->prepare(
                "
                SELECT COUNT(*)

                FROM payroll_exception_resolutions

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


    public function countOpenForPeriod(
        int $payrollPeriodId
    ): int
    {
        $statement =
            $this->db->prepare(
                "
                SELECT COUNT(*)

                FROM payroll_exception_resolutions

                WHERE payroll_period_id
                    =
                    :payroll_period_id

                  AND resolution_status
                    =
                    'open'
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


    public function markResolved(
        int $id,
        int $userId,
        ?string $resolutionNote = null
    ): bool
    {
        $statement =
            $this->db->prepare(
                "
                UPDATE payroll_exception_resolutions

                SET
                    resolution_status =
                        'resolved',

                    resolution_note =
                        :resolution_note,

                    resolved_by_user_id =
                        :resolved_by_user_id,

                    resolved_at =
                        CURRENT_TIMESTAMP,

                    updated_at =
                        CURRENT_TIMESTAMP

                WHERE id =
                    :id

                  AND resolution_status =
                    'open'
                "
            );


        $statement->execute(
            [
                'resolution_note' =>
                    $resolutionNote,

                'resolved_by_user_id' =>
                    $userId,

                'id' =>
                    $id
            ]
        );


        return
            $statement->rowCount()
            ===
            1;
    }


    public function markAccepted(
        int $id,
        int $userId,
        string $resolutionNote
    ): bool
    {
        $statement =
            $this->db->prepare(
                "
                UPDATE payroll_exception_resolutions

                SET
                    resolution_status =
                        'accepted',

                    resolution_note =
                        :resolution_note,

                    resolved_by_user_id =
                        :resolved_by_user_id,

                    resolved_at =
                        CURRENT_TIMESTAMP,

                    updated_at =
                        CURRENT_TIMESTAMP

                WHERE id =
                    :id

                  AND resolution_status =
                    'open'
                "
            );


        $statement->execute(
            [
                'resolution_note' =>
                    $resolutionNote,

                'resolved_by_user_id' =>
                    $userId,

                'id' =>
                    $id
            ]
        );


        return
            $statement->rowCount()
            ===
            1;
    }


    public function reopen(
        int $id
    ): bool
    {
        $statement =
            $this->db->prepare(
                "
                UPDATE payroll_exception_resolutions

                SET
                    resolution_status =
                        'open',

                    resolution_note =
                        NULL,

                    resolved_by_user_id =
                        NULL,

                    resolved_at =
                        NULL,

                    updated_at =
                        CURRENT_TIMESTAMP

                WHERE id =
                    :id

                  AND resolution_status IN
                  (
                      'resolved',
                      'accepted'
                  )
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


    public function deleteOpenForPeriodExceptKeys(
        int $payrollPeriodId,
        array $exceptionKeys
    ): int
    {
        if ($exceptionKeys === []) {

            $statement =
                $this->db->prepare(
                    "
                    DELETE FROM payroll_exception_resolutions

                    WHERE payroll_period_id
                        =
                        :payroll_period_id

                      AND resolution_status
                        =
                        'open'
                    "
                );


            $statement->execute(
                [
                    'payroll_period_id' =>
                        $payrollPeriodId
                ]
            );


            return
                $statement->rowCount();
        }


        $placeholders = [];

        $parameters = [
            'payroll_period_id' =>
                $payrollPeriodId
        ];


        foreach (
            array_values(
                $exceptionKeys
            )
            as $index => $exceptionKey
        ) {

            $parameterName =
                'exception_key_' . $index;


            $placeholders[] =
                ':' . $parameterName;


            $parameters[$parameterName] =
                $exceptionKey;
        }


        $statement =
            $this->db->prepare(
                "
                DELETE FROM payroll_exception_resolutions

                WHERE payroll_period_id
                    =
                    :payroll_period_id

                  AND resolution_status
                    =
                    'open'

                  AND exception_key NOT IN
                  (
                      " .
                      implode(
                          ', ',
                          $placeholders
                      ) .
                      "
                  )
                "
            );


        $statement->execute(
            $parameters
        );


        return
            $statement->rowCount();
    }
}
