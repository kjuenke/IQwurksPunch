<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\PayrollPeriodRepository;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class PayrollPeriodProtectionService
{
    public function __construct(
        private readonly PayrollPeriodRepository $payrollPeriodRepository
    )
    {
    }


    public function localDateForUtcTimestamp(
        string $utcTimestamp,
        string $companyTimezone
    ): string
    {
        $normalizedTimestamp =
            trim(
                $utcTimestamp
            );


        if ($normalizedTimestamp === '') {

            throw new InvalidArgumentException(
                'A punch timestamp is required.'
            );
        }


        $timezone =
            $this->createTimezone(
                $companyTimezone
            );


        try {

            $utcDateTime =
                new DateTimeImmutable(
                    $normalizedTimestamp,
                    new DateTimeZone(
                        'UTC'
                    )
                );

        } catch (Throwable $exception) {

            throw new InvalidArgumentException(
                'The punch timestamp is invalid.',
                0,
                $exception
            );
        }


        return
            $utcDateTime
                ->setTimezone(
                    $timezone
                )
                ->format(
                    'Y-m-d'
                );
    }


    public function findProtectedForUtcTimestamp(
        string $utcTimestamp,
        string $companyTimezone
    ): ?array
    {
        $localDate =
            $this->localDateForUtcTimestamp(
                $utcTimestamp,
                $companyTimezone
            );


        return
            $this->findProtectedForLocalDate(
                $localDate
            );
    }


    public function findProtectedForLocalDate(
        string $localDate
    ): ?array
    {
        $normalizedDate =
            $this->validateLocalDate(
                $localDate
            );


        return
            $this->payrollPeriodRepository
                ->findProtectedContainingDate(
                    $normalizedDate
                );
    }


    public function isUtcTimestampProtected(
        string $utcTimestamp,
        string $companyTimezone
    ): bool
    {
        return
            $this->findProtectedForUtcTimestamp(
                $utcTimestamp,
                $companyTimezone
            )
            !==
            null;
    }


    public function isLocalDateProtected(
        string $localDate
    ): bool
    {
        return
            $this->findProtectedForLocalDate(
                $localDate
            )
            !==
            null;
    }


    public function assertUtcTimestampIsEditable(
        string $utcTimestamp,
        string $companyTimezone
    ): void
    {
        $protectedPeriod =
            $this->findProtectedForUtcTimestamp(
                $utcTimestamp,
                $companyTimezone
            );


        if ($protectedPeriod === null) {

            return;
        }


        throw $this->protectionException(
            $protectedPeriod
        );
    }


    public function assertLocalDateIsEditable(
        string $localDate
    ): void
    {
        $protectedPeriod =
            $this->findProtectedForLocalDate(
                $localDate
            );


        if ($protectedPeriod === null) {

            return;
        }


        throw $this->protectionException(
            $protectedPeriod
        );
    }


    private function validateLocalDate(
        string $localDate
    ): string
    {
        $normalizedDate =
            trim(
                $localDate
            );


        if ($normalizedDate === '') {

            throw new InvalidArgumentException(
                'A company-local date is required.'
            );
        }


        $date =
            DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $normalizedDate
            );


        $errors =
            DateTimeImmutable::getLastErrors();


        $hasDateErrors =
            $errors !== false
            &&
            (
                $errors['warning_count'] > 0
                ||
                $errors['error_count'] > 0
            );


        if (
            $date === false
            ||
            $hasDateErrors
            ||
            $date->format(
                'Y-m-d'
            )
            !==
            $normalizedDate
        ) {

            throw new InvalidArgumentException(
                'The company-local date must use a valid YYYY-MM-DD date.'
            );
        }


        return $normalizedDate;
    }


    private function createTimezone(
        string $companyTimezone
    ): DateTimeZone
    {
        $normalizedTimezone =
            trim(
                $companyTimezone
            );


        if ($normalizedTimezone === '') {

            throw new InvalidArgumentException(
                'A company timezone is required.'
            );
        }


        try {

            return
                new DateTimeZone(
                    $normalizedTimezone
                );

        } catch (Throwable $exception) {

            throw new InvalidArgumentException(
                sprintf(
                    'The company timezone "%s" is invalid.',
                    $normalizedTimezone
                ),
                0,
                $exception
            );
        }
    }


    private function protectionException(
        array $protectedPeriod
    ): RuntimeException
    {
        $periodName =
            trim(
                (string)(
                    $protectedPeriod['period_name']
                    ??
                    'Payroll period'
                )
            );


        $status =
            trim(
                (string)(
                    $protectedPeriod['status']
                    ??
                    'protected'
                )
            );


        $startDate =
            trim(
                (string)(
                    $protectedPeriod['start_date']
                    ??
                    ''
                )
            );


        $endDate =
            trim(
                (string)(
                    $protectedPeriod['end_date']
                    ??
                    ''
                )
            );


        return
            new RuntimeException(
                sprintf(
                    'This punch falls within the %s payroll period "%s" (%s through %s). Reopen the payroll period before making corrections.',
                    $status,
                    $periodName,
                    $startDate,
                    $endDate
                )
            );
    }
}
