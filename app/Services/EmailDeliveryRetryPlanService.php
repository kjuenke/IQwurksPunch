<?php
declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use JsonException;

final class EmailDeliveryRetryPlanService
{
    public const DAILY_PAYROLL =
        'daily_payroll';

    public const WEEKLY_PAYROLL =
        'weekly_payroll';

    public const EXCEPTION_REPORTS =
        'exception_reports';


    /**
     * @param array<string,mixed> $attempt
     *
     * @return array{
     *     failed_attempt_id:int,
     *     notification_type:string,
     *     source:string,
     *     schedule_id:int|null,
     *     attempt_number:int,
     *     max_attempts:int,
     *     retry_of_id:int,
     *     report_date:string|null,
     *     reference_date:string|null
     * }
     */
    public function plan(
        array $attempt
    ): array
    {
        $attemptId =
            $this->positiveInteger(
                $attempt['id']
                ??
                null,
                'Failed delivery attempt ID'
            );


        $status =
            trim(
                (string)(
                    $attempt['status']
                    ??
                    ''
                )
            );


        if ($status !== 'failed') {
            throw new InvalidArgumentException(
                'Only failed email deliveries can be retried.'
            );
        }


        if (
            (int)(
                $attempt['permanent_failure']
                ??
                0
            )
            ===
            1
        ) {
            throw new InvalidArgumentException(
                'A permanent email-delivery failure cannot be retried.'
            );
        }


        $attemptNumber =
            $this->positiveInteger(
                $attempt['attempt_number']
                ??
                null,
                'Current attempt number'
            );


        $maxAttempts =
            $this->positiveInteger(
                $attempt['max_attempts']
                ??
                null,
                'Maximum attempt count'
            );


        if ($attemptNumber >= $maxAttempts) {
            throw new InvalidArgumentException(
                'The email-delivery retry limit has already been reached.'
            );
        }


        $notificationType =
            trim(
                (string)(
                    $attempt['notification_type']
                    ??
                    ''
                )
            );


        if (
            !in_array(
                $notificationType,
                [
                    self::DAILY_PAYROLL,
                    self::WEEKLY_PAYROLL,
                    self::EXCEPTION_REPORTS
                ],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'This email notification type cannot be regenerated safely.'
            );
        }


        $scheduleId =
            $this->nullablePositiveInteger(
                $attempt['schedule_id']
                ??
                null,
                'Delivery schedule ID'
            );


        $reportDate = null;

        $referenceDate = null;


        if (
            $notificationType
            ===
            self::DAILY_PAYROLL
        ) {
            $reportDate =
                $this->dailyReportDate(
                    $attempt
                );
        }


        if (
            $notificationType
            ===
            self::WEEKLY_PAYROLL
        ) {
            $referenceDate =
                $this->weeklyReferenceDate(
                    $attempt
                );
        }


        return [
            'failed_attempt_id' =>
                $attemptId,

            'notification_type' =>
                $notificationType,

            'source' =>
                'retry',

            'schedule_id' =>
                $scheduleId,

            'attempt_number' =>
                $attemptNumber
                +
                1,

            'max_attempts' =>
                $maxAttempts,

            'retry_of_id' =>
                $attemptId,

            'report_date' =>
                $reportDate,

            'reference_date' =>
                $referenceDate
        ];
    }


    /**
     * @param array<string,mixed> $attempt
     */
    private function dailyReportDate(
        array $attempt
    ): string
    {
        foreach (
            $this->attachmentNames(
                $attempt
            )
            as
            $filename
        ) {
            if (
                preg_match(
                    '/^iqwurkspunch-daily-payroll-(\d{4}-\d{2}-\d{2})\.(?:csv|pdf)$/',
                    $filename,
                    $matches
                )
                ===
                1
            ) {
                return
                    $this->validDate(
                        $matches[1],
                        'Daily payroll report date'
                    );
            }
        }


        throw new InvalidArgumentException(
            'The original daily payroll report date could not be determined.'
        );
    }


    /**
     * @param array<string,mixed> $attempt
     */
    private function weeklyReferenceDate(
        array $attempt
    ): string
    {
        foreach (
            $this->attachmentNames(
                $attempt
            )
            as
            $filename
        ) {
            if (
                preg_match(
                    '/^iqwurkspunch-weekly-payroll-(\d{4}-\d{2}-\d{2})-to-(\d{4}-\d{2}-\d{2})\.(?:csv|pdf)$/',
                    $filename,
                    $matches
                )
                ===
                1
            ) {
                $weekStart =
                    $this->validDate(
                        $matches[1],
                        'Weekly payroll start date'
                    );


                $weekEnd =
                    $this->validDate(
                        $matches[2],
                        'Weekly payroll end date'
                    );


                if ($weekEnd < $weekStart) {
                    throw new InvalidArgumentException(
                        'The weekly payroll attachment contains an invalid date range.'
                    );
                }


                /*
                 * Any date within the requested week can be used by the
                 * weekly report service. The original week-start date is the
                 * safest deterministic reference.
                 */
                return $weekStart;
            }
        }


        throw new InvalidArgumentException(
            'The original weekly payroll date range could not be determined.'
        );
    }


    /**
     * @param array<string,mixed> $attempt
     *
     * @return array<int,string>
     */
    private function attachmentNames(
        array $attempt
    ): array
    {
        $json =
            trim(
                (string)(
                    $attempt['attachment_names']
                    ??
                    '[]'
                )
            );


        if ($json === '') {
            $json =
                '[]';
        }


        try {

            $decoded =
                json_decode(
                    $json,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );

        } catch (JsonException $exception) {

            throw new InvalidArgumentException(
                'The email attachment metadata is not valid JSON.',
                0,
                $exception
            );
        }


        if (!is_array($decoded)) {
            throw new InvalidArgumentException(
                'The email attachment metadata must contain a list.'
            );
        }


        $names = [];


        foreach ($decoded as $filename) {

            if (!is_string($filename)) {
                throw new InvalidArgumentException(
                    'Every recorded email attachment name must be a string.'
                );
            }


            $filename =
                trim(
                    $filename
                );


            if ($filename === '') {
                continue;
            }


            $names[] =
                basename(
                    $filename
                );
        }


        return $names;
    }


    private function validDate(
        string $value,
        string $label
    ): string
    {
        $timezone =
            new DateTimeZone(
                'UTC'
            );


        $date =
            DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $value,
                $timezone
            );


        $errors =
            DateTimeImmutable::getLastErrors();


        if (
            !$date
            ||
            (
                is_array(
                    $errors
                )
                &&
                (
                    $errors['warning_count'] > 0
                    ||
                    $errors['error_count'] > 0
                )
            )
            ||
            $date->format(
                'Y-m-d'
            )
            !==
            $value
        ) {
            throw new InvalidArgumentException(
                $label
                .
                ' must be a valid date in YYYY-MM-DD format.'
            );
        }


        return $value;
    }


    private function positiveInteger(
        mixed $value,
        string $label
    ): int
    {
        if (
            filter_var(
                $value,
                FILTER_VALIDATE_INT
            )
            ===
            false
        ) {
            throw new InvalidArgumentException(
                $label
                .
                ' must be an integer.'
            );
        }


        $value =
            (int)$value;


        if ($value < 1) {
            throw new InvalidArgumentException(
                $label
                .
                ' must be greater than zero.'
            );
        }


        return $value;
    }


    private function nullablePositiveInteger(
        mixed $value,
        string $label
    ): ?int
    {
        if (
            $value === null
            ||
            $value === ''
        ) {
            return null;
        }


        return
            $this->positiveInteger(
                $value,
                $label
            );
    }
}
