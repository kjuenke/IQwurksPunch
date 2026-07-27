<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\PayrollExceptionResolutionRepository;
use App\Repositories\PayrollPeriodRepository;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;

final class PayrollReportPeriodMetadataService
{
    private PayrollPeriodRepository $payrollPeriods;

    private PayrollExceptionResolutionRepository $exceptions;


    public function __construct(
        PayrollPeriodRepository $payrollPeriods,
        PayrollExceptionResolutionRepository $exceptions
    )
    {
        $this->payrollPeriods =
            $payrollPeriods;


        $this->exceptions =
            $exceptions;
    }


    /**
     * Add payroll-period metadata to an existing report summary.
     *
     * @param array<string,mixed> $summary
     *
     * @return array<string,mixed>
     */
    public function enrich(
        array $summary,
        string $startDate,
        string $endDate
    ): array
    {
        $metadata =
            $this->forRange(
                $startDate,
                $endDate
            );


        return [
            ...$summary,

            'payroll_period_association' =>
                $metadata['association_status'],

            'payroll_period_association_message' =>
                $metadata['association_message'],

            'payroll_period' =>
                $metadata['payroll_period']
        ];
    }


    /**
     * Find payroll-period metadata for an exact report date range.
     *
     * @return array{
     *     association_status:string,
     *     association_message:string,
     *     payroll_period:?array<string,mixed>
     * }
     */
    public function forRange(
        string $startDate,
        string $endDate
    ): array
    {
        $startDate =
            trim(
                $startDate
            );


        $endDate =
            trim(
                $endDate
            );


        $this->validateDate(
            $startDate,
            'start date'
        );


        $this->validateDate(
            $endDate,
            'end date'
        );


        if ($endDate < $startDate) {

            throw new InvalidArgumentException(
                'The report end date cannot be earlier than the report start date.'
            );
        }


        $overlappingPeriod =
            $this->payrollPeriods
                ->findOverlapping(
                    $startDate,
                    $endDate
                );


        if ($overlappingPeriod === null) {

            return [
                'association_status' =>
                    'none',

                'association_message' =>
                    'This report is not associated with a payroll period.',

                'payroll_period' =>
                    null
            ];
        }


        $periodStartDate =
            (string)(
                $overlappingPeriod['start_date']
                ??
                ''
            );


        $periodEndDate =
            (string)(
                $overlappingPeriod['end_date']
                ??
                ''
            );


        if (
            $periodStartDate !== $startDate
            ||
            $periodEndDate !== $endDate
        ) {

            return [
                'association_status' =>
                    'partial_overlap',

                'association_message' =>
                    sprintf(
                        'This report overlaps payroll period "%s" (%s through %s), but its date range is not an exact match.',
                        (string)(
                            $overlappingPeriod['period_name']
                            ??
                            'Unnamed Payroll Period'
                        ),
                        $periodStartDate,
                        $periodEndDate
                    ),

                'payroll_period' =>
                    null
            ];
        }


        $payrollPeriodId =
            (int)(
                $overlappingPeriod['id']
                ??
                0
            );


        if ($payrollPeriodId < 1) {

            throw new RuntimeException(
                'The matching payroll period contains an invalid ID.'
            );
        }


        $period =
            $this->payrollPeriods
                ->find(
                    $payrollPeriodId
                );


        if ($period === null) {

            throw new RuntimeException(
                'The matching payroll period could not be loaded.'
            );
        }


        $openExceptionCount =
            $this->exceptions
                ->countOpenForPeriod(
                    $payrollPeriodId
                );


        $totalExceptionCount =
            $this->exceptions
                ->countForPeriod(
                    $payrollPeriodId
                );


        return [
            'association_status' =>
                'exact',

            'association_message' =>
                'This report exactly matches a payroll review period.',

            'payroll_period' => [
                'id' =>
                    $payrollPeriodId,

                'period_name' =>
                    (string)(
                        $period['period_name']
                        ??
                        ''
                    ),

                'start_date' =>
                    (string)(
                        $period['start_date']
                        ??
                        ''
                    ),

                'end_date' =>
                    (string)(
                        $period['end_date']
                        ??
                        ''
                    ),

                'status' =>
                    (string)(
                        $period['status']
                        ??
                        ''
                    ),

                'created_at' =>
                    $period['created_at']
                    ??
                    null,

                'created_by_user_id' =>
                    $this->nullableInteger(
                        $period['created_by_user_id']
                        ??
                        null
                    ),

                'created_by_username' =>
                    $period['created_by_username']
                    ??
                    null,

                'review_started_at' =>
                    $period['review_started_at']
                    ??
                    null,

                'reviewed_by_user_id' =>
                    $this->nullableInteger(
                        $period['reviewed_by_user_id']
                        ??
                        null
                    ),

                'reviewed_by_username' =>
                    $period['reviewed_by_username']
                    ??
                    null,

                'approved_at' =>
                    $period['approved_at']
                    ??
                    null,

                'approved_by_user_id' =>
                    $this->nullableInteger(
                        $period['approved_by_user_id']
                        ??
                        null
                    ),

                'approved_by_username' =>
                    $period['approved_by_username']
                    ??
                    null,

                'locked_at' =>
                    $period['locked_at']
                    ??
                    null,

                'locked_by_user_id' =>
                    $this->nullableInteger(
                        $period['locked_by_user_id']
                        ??
                        null
                    ),

                'locked_by_username' =>
                    $period['locked_by_username']
                    ??
                    null,

                'updated_at' =>
                    $period['updated_at']
                    ??
                    null,

                'open_exception_count' =>
                    $openExceptionCount,

                'total_exception_count' =>
                    $totalExceptionCount,

                'approval_blocked' =>
                    $openExceptionCount > 0,

                'detail_url' =>
                    '/payroll-periods/'
                    .
                    $payrollPeriodId
            ]
        ];
    }


    private function validateDate(
        string $date,
        string $label
    ): void
    {
        $parsed =
            DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $date
            );


        $errors =
            DateTimeImmutable::getLastErrors();


        if (
            !$parsed
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
            $parsed->format(
                'Y-m-d'
            )
            !==
            $date
        ) {

            throw new InvalidArgumentException(
                'The report '
                .
                $label
                .
                ' must use YYYY-MM-DD format.'
            );
        }
    }


    private function nullableInteger(
        mixed $value
    ): ?int
    {
        if (
            $value === null
            ||
            $value === ''
        ) {

            return null;
        }


        $integer =
            (int)$value;


        return $integer > 0
            ? $integer
            : null;
    }
}
