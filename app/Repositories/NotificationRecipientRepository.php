<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

class NotificationRecipientRepository
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
                FROM notification_recipients

                ORDER BY
                    active DESC,
                    name COLLATE NOCASE ASC,
                    email COLLATE NOCASE ASC
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
                FROM notification_recipients

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


        $recipient =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        return $recipient ?: null;
    }


    public function findByEmail(
        string $email
    ): ?array
    {
        $stmt =
            $this->db->prepare(
                "
                SELECT *
                FROM notification_recipients

                WHERE LOWER(email) = LOWER(:email)

                LIMIT 1
                "
            );


        $stmt->execute(
            [
                'email' =>
                    trim(
                        $email
                    )
            ]
        );


        $recipient =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        return $recipient ?: null;
    }


    public function create(
        array $data
    ): int
    {
        $stmt =
            $this->db->prepare(
                "
                INSERT INTO notification_recipients
                (
                    name,
                    email,
                    daily_payroll,
                    weekly_payroll,
                    exception_reports,
                    active
                )

                VALUES
                (
                    :name,
                    :email,
                    :daily_payroll,
                    :weekly_payroll,
                    :exception_reports,
                    :active
                )
                "
            );


        $stmt->execute(
            [
                'name' =>
                    $data['name'],

                'email' =>
                    $data['email'],

                'daily_payroll' =>
                    $data['daily_payroll'],

                'weekly_payroll' =>
                    $data['weekly_payroll'],

                'exception_reports' =>
                    $data['exception_reports'],

                'active' =>
                    $data['active']
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
                UPDATE notification_recipients

                SET
                    name = :name,
                    email = :email,
                    daily_payroll = :daily_payroll,
                    weekly_payroll = :weekly_payroll,
                    exception_reports = :exception_reports,
                    active = :active,
                    updated_at = CURRENT_TIMESTAMP

                WHERE id = :id
                "
            );


        return $stmt->execute(
            [
                'name' =>
                    $data['name'],

                'email' =>
                    $data['email'],

                'daily_payroll' =>
                    $data['daily_payroll'],

                'weekly_payroll' =>
                    $data['weekly_payroll'],

                'exception_reports' =>
                    $data['exception_reports'],

                'active' =>
                    $data['active'],

                'id' =>
                    $id
            ]
        );
    }


    public function setActive(
        int $id,
        bool $active
    ): bool
    {
        $stmt =
            $this->db->prepare(
                "
                UPDATE notification_recipients

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


    public function delete(
        int $id
    ): bool
    {
        $stmt =
            $this->db->prepare(
                "
                DELETE FROM notification_recipients

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


    /**
     * @return array<int,string>
     */
    public function activeEmailsFor(
        string $notificationType
    ): array
    {
        $allowedColumns = [
            'daily_payroll',
            'weekly_payroll',
            'exception_reports'
        ];


        if (
            !in_array(
                $notificationType,
                $allowedColumns,
                true
            )
        ) {
            throw new RuntimeException(
                'Unsupported notification type: '
                .
                $notificationType
            );
        }


        /*
         * The column name cannot be bound as a SQL parameter.
         * It is safe here because it must match the allowlist above.
         */
        $stmt =
            $this->db->query(
                "
                SELECT email
                FROM notification_recipients

                WHERE active = 1
                  AND {$notificationType} = 1

                ORDER BY
                    name COLLATE NOCASE ASC,
                    email COLLATE NOCASE ASC
                "
            );


        $emails =
            $stmt->fetchAll(
                PDO::FETCH_COLUMN
            );


        return array_values(
            array_map(
                static fn (
                    mixed $email
                ): string =>
                    (string)$email,
                $emails
            )
        );
    }


    public function countActiveFor(
        string $notificationType
    ): int
    {
        return count(
            $this->activeEmailsFor(
                $notificationType
            )
        );
    }
}
