<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class PayrollPeriodLifecycle
{
    /**
     * @param array<string,mixed> $period
     */
    public static function assertActive(
        array $period
    ): void
    {
        if (
            self::hasTimestamp(
                $period['voided_at']
                ??
                null
            )
        ) {
            throw new RuntimeException(
                'Voided payroll periods are read-only and cannot participate in payroll workflow.'
            );
        }


        if (
            self::hasTimestamp(
                $period['archived_at']
                ??
                null
            )
        ) {
            throw new RuntimeException(
                'Archived payroll periods are read-only and cannot participate in payroll workflow.'
            );
        }
    }


    /**
     * @param array<string,mixed> $period
     */
    public static function isActive(
        array $period
    ): bool
    {
        return
            !self::hasTimestamp(
                $period['archived_at']
                ??
                null
            )
            &&
            !self::hasTimestamp(
                $period['voided_at']
                ??
                null
            );
    }


    private static function hasTimestamp(
        mixed $value
    ): bool
    {
        if ($value === null) {
            return false;
        }


        return
            trim(
                (string)$value
            )
            !==
            '';
    }
}
