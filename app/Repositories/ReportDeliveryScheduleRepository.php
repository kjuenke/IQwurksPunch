<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;
use Throwable;

final class ReportDeliveryScheduleRepository
{
    private const REPORT_TYPES = [
        'daily_payroll',
        'weekly_payroll',
        'exception_report'
    ];


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
                FROM report_delivery_schedules

                ORDER BY
                    CASE report_type
                        WHEN 'daily_payroll' THEN 1
                        WHEN 'weekly_payroll' THEN 2
                        WHEN 'exception_report' THEN 3
                        ELSE 4
                    END,
                    id ASC
                "
            );


        return $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );
    }


    /**
     * @return array<string,mixed>|null
     */
    public function findByType(
        string $reportType
    ): ?array
    {
        $reportType =
            $this->validatedReportType(
                $reportType
            );


        $stmt =
            $this->db->prepare(
                "
                SELECT *
                FROM report_delivery_schedules

                WHERE report_type = :report_type

                ORDER BY id ASC

                LIMIT 1
                "
            );


        $stmt->execute(
            [
                'report_type' =>
                    $reportType
            ]
        );


        $schedule =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        return $schedule ?: null;
    }


    public function update(
        string $reportType,
        bool $enabled,
        string $sendTime,
        bool $weekdaysOnly,
        ?int $sendDayOfWeek
    ): bool
    {
        $reportType =
            $this->validatedReportType(
                $reportType
            );


        $stmt =
            $this->db->prepare(
                "
                UPDATE report_delivery_schedules

                SET
                    enabled = :enabled,
                    send_time = :send_time,
                    weekdays_only = :weekdays_only,
                    send_day_of_week = :send_day_of_week,
                    updated_at = CURRENT_TIMESTAMP

                WHERE report_type = :report_type
                "
            );


        $updated =
            $stmt->execute(
                [
                    'enabled' =>
                        $enabled
                            ? 1
                            : 0,

                    'send_time' =>
                        $sendTime,

                    'weekdays_only' =>
                        $weekdaysOnly
                            ? 1
                            : 0,

                    'send_day_of_week' =>
                        $sendDayOfWeek,

                    'report_type' =>
                        $reportType
                ]
            );


        if (
            $updated
            &&
            $reportType === 'daily_payroll'
        ) {
            $this->syncLegacyDailyConfiguration(
                $enabled,
                $sendTime,
                $weekdaysOnly
            );
        }


        return $updated;
    }


    public function markSent(
        string $reportType,
        string $sentAtUtc
    ): bool
    {
        $reportType =
            $this->validatedReportType(
                $reportType
            );


        $stmt =
            $this->db->prepare(
                "
                UPDATE report_delivery_schedules

                SET
                    last_sent_at = :last_sent_at,
                    last_result = 'sent',
                    last_error = NULL,
                    updated_at = CURRENT_TIMESTAMP

                WHERE report_type = :report_type
                "
            );


        $updated =
            $stmt->execute(
                [
                    'last_sent_at' =>
                        $sentAtUtc,

                    'report_type' =>
                        $reportType
                ]
            );


        if (
            $updated
            &&
            $reportType === 'daily_payroll'
        ) {
            $this->syncLegacyDailySentAt(
                $sentAtUtc
            );
        }


        return $updated;
    }


    public function markFailed(
        string $reportType,
        string $error
    ): bool
    {
        $reportType =
            $this->validatedReportType(
                $reportType
            );


        $error =
            trim(
                $error
            );


        if (
            strlen(
                $error
            ) > 2000
        ) {
            $error =
                substr(
                    $error,
                    0,
                    2000
                );
        }


        $stmt =
            $this->db->prepare(
                "
                UPDATE report_delivery_schedules

                SET
                    last_result = 'failed',
                    last_error = :last_error,
                    updated_at = CURRENT_TIMESTAMP

                WHERE report_type = :report_type
                "
            );


        return $stmt->execute(
            [
                'last_error' =>
                    $error,

                'report_type' =>
                    $reportType
            ]
        );
    }


    private function syncLegacyDailyConfiguration(
        bool $enabled,
        string $sendTime,
        bool $weekdaysOnly
    ): void
    {
        if (!$this->legacyScheduleTableExists()) {
            return;
        }


        try {

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


            $stmt->execute(
                [
                    'enabled' =>
                        $enabled
                            ? 1
                            : 0,

                    'send_time' =>
                        $sendTime,

                    'weekdays_only' =>
                        $weekdaysOnly
                            ? 1
                            : 0
                ]
            );

        } catch (Throwable) {

            /*
             * The legacy table exists only for compatibility with existing
             * diagnostics and rollback. A compatibility-sync failure must
             * not prevent the active Version 0.8 schedule from being saved.
             */
        }
    }


    private function syncLegacyDailySentAt(
        string $sentAtUtc
    ): void
    {
        if (!$this->legacyScheduleTableExists()) {
            return;
        }


        try {

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


            $stmt->execute(
                [
                    'last_sent_at' =>
                        $sentAtUtc
                ]
            );

        } catch (Throwable) {

            /*
             * The active delivery record has already been updated. The
             * legacy synchronization is intentionally best-effort.
             */
        }
    }


    private function legacyScheduleTableExists(): bool
    {
        try {

            $stmt =
                $this->db->prepare(
                    "
                    SELECT 1
                    FROM sqlite_master

                    WHERE type = 'table'
                      AND name = :table_name

                    LIMIT 1
                    "
                );


            $stmt->execute(
                [
                    'table_name' =>
                        'report_schedule_settings'
                ]
            );


            return
                $stmt->fetchColumn()
                !==
                false;

        } catch (Throwable) {

            return false;
        }
    }


    private function validatedReportType(
        string $reportType
    ): string
    {
        $reportType =
            trim(
                $reportType
            );


        if (
            !in_array(
                $reportType,
                self::REPORT_TYPES,
                true
            )
        ) {
            throw new RuntimeException(
                'Unsupported report schedule type: '
                .
                $reportType
            );
        }


        return $reportType;
    }
}
