<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

class EmailRepository
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
        string $reportType,
        string $recipients,
        string $status
    ): bool
    {
        return
            $this->createAndReturnId(
                $reportType,
                $recipients,
                $status
            )
            !==
            null;
    }


    public function createAndReturnId(
        string $reportType,
        string $recipients,
        string $status
    ): ?int
    {
        $statement =
            $this->db->prepare(
                "
                INSERT INTO email_log
                (
                    report_type,
                    recipients,
                    status
                )

                VALUES
                (
                    :report_type,
                    :recipients,
                    :status
                )
                "
            );


        $created =
            $statement->execute(
                [
                    'report_type' =>
                        $reportType,

                    'recipients' =>
                        $recipients,

                    'status' =>
                        $status
                ]
            );


        if (!$created) {
            return null;
        }


        $id =
            (int)$this->db
                ->lastInsertId();


        if ($id < 1) {
            throw new RuntimeException(
                'The email history record ID could not be determined.'
            );
        }


        return $id;
    }


    /**
     * @return array<int,array<string,mixed>>
     */
    public function all(): array
    {
        $statement =
            $this->db->query(
                "
                SELECT *
                FROM email_log

                ORDER BY id DESC
                "
            );


        if ($statement === false) {
            return [];
        }


        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            );
    }
}
