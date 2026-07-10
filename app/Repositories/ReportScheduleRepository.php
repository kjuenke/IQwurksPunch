<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

class ReportScheduleRepository
{
    private PDO $db;


    public function __construct(PDO $db)
    {
        $this->db = $db;
    }


    public function get(): ?array
    {
        $stmt =
            $this->db->query(
                "
                SELECT *
                FROM report_schedule_settings
                ORDER BY id ASC
                LIMIT 1
                "
            );


        $result =
            $stmt->fetch(PDO::FETCH_ASSOC);


        return $result ?: null;
    }


    public function update(
        bool $enabled,
        string $sendTime,
        bool $weekdaysOnly
    ): bool
    {
        $stmt =
            $this->db->prepare(
                "
                UPDATE report_schedule_settings

                SET
                    enabled = :enabled,
                    send_time = :send_time,
                    weekdays_only = :weekdays_only,
                    updated_at = CURRENT_TIMESTAMP

                WHERE id = 1
                "
            );


        return $stmt->execute(
            [
                'enabled' =>
                    $enabled ? 1 : 0,

                'send_time' =>
                    $sendTime,

                'weekdays_only' =>
                    $weekdaysOnly ? 1 : 0
            ]
        );
    }


    public function markSent(
        string $sentAtUtc
    ): bool
    {
        $stmt =
            $this->db->prepare(
                "
                UPDATE report_schedule_settings

                SET
                    last_sent_at = :last_sent_at,
                    updated_at = CURRENT_TIMESTAMP

                WHERE id = 1
                "
            );


        return $stmt->execute(
            [
                'last_sent_at' =>
                    $sentAtUtc
            ]
        );
    }
}
