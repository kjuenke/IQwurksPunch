<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\PayrollExceptionResolutionRepository;
use App\Repositories\PayrollPeriodRepository;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

final class PayrollExceptionService
{
    private PDO $db;

    private PayrollPeriodRepository $payrollPeriods;

    private PayrollExceptionResolutionRepository $exceptions;

    private PayrollWorkspaceService $workspace;


    public function __construct(
        PDO $db,
        PayrollPeriodRepository $payrollPeriods,
        PayrollExceptionResolutionRepository $exceptions,
        PayrollWorkspaceService $workspace
    )
    {
        $this->db =
            $db;


        $this->payrollPeriods =
            $payrollPeriods;


        $this->exceptions =
            $exceptions;


        $this->workspace =
            $workspace;
    }


    /**
     * Recalculate a payroll period and synchronize its persisted exceptions.
     *
     * @return array<string,mixed>
     */
    public function refresh(
        int $payrollPeriodId
    ): array
    {
        $period =
            $this->requireRefreshablePeriod(
                $payrollPeriodId
            );


        $summary =
            $this->workspace
                ->summary(
                    (string)$period['start_date'],
                    (string)$period['end_date']
                );


        return
            $this->refreshFromSummary(
                $payrollPeriodId,
                $summary
            );
    }


    /**
     * Synchronize persisted exceptions from an existing workspace summary.
     *
     * This public method allows the synchronization rules to be tested
     * independently from payroll calculation and database punch fixtures.
     *
     * @param array<string,mixed> $summary
     *
     * @return array<string,mixed>
     */
    public function refreshFromSummary(
        int $payrollPeriodId,
        array $summary
    ): array
    {
        $period =
            $this->requireRefreshablePeriod(
                $payrollPeriodId
            );


        $this->validateSummaryRange(
            $period,
            $summary
        );


        $detectedExceptions =
            $this->detectedExceptions(
                $payrollPeriodId,
                $summary
            );


        return
            $this->transactional(
                function () use (
                    $payrollPeriodId,
                    $detectedExceptions
                ): array {

                    $createdCount = 0;

                    $refreshedCount = 0;

                    $reopenedCount = 0;

                    $acceptedPreservedCount = 0;

                    $exceptionKeys = [];


                    foreach (
                        $detectedExceptions
                        as $detectedException
                    ) {
                        $exceptionKey =
                            (string)$detectedException[
                                'exception_key'
                            ];


                        $exceptionKeys[] =
                            $exceptionKey;


                        $existing =
                            $this->exceptions
                                ->findByPeriodAndKey(
                                    $payrollPeriodId,
                                    $exceptionKey
                                );


                        if ($existing === null) {

                            $createdCount++;

                        } else {

                            $refreshedCount++;


                            $existingStatus =
                                (string)(
                                    $existing[
                                        'resolution_status'
                                    ]
                                    ??
                                    ''
                                );


                            if ($existingStatus === 'resolved') {

                                $reopened =
                                    $this->exceptions
                                        ->reopen(
                                            (int)$existing['id']
                                        );


                                if (!$reopened) {

                                    throw new RuntimeException(
                                        'A resolved payroll exception changed before it could be reopened.'
                                    );
                                }


                                $reopenedCount++;

                            } elseif (
                                $existingStatus
                                ===
                                'accepted'
                            ) {

                                $acceptedPreservedCount++;
                            }
                        }


                        $exceptionId =
                            $this->exceptions
                                ->createOrRefresh(
                                    $detectedException
                                );


                        if ($exceptionId < 1) {

                            throw new RuntimeException(
                                'A payroll exception could not be created or refreshed.'
                            );
                        }
                    }


                    $deletedStaleOpenCount =
                        $this->exceptions
                            ->deleteOpenForPeriodExceptKeys(
                                $payrollPeriodId,
                                $exceptionKeys
                            );


                    return [
                        'payroll_period_id' =>
                            $payrollPeriodId,

                        'detected_count' =>
                            count(
                                $detectedExceptions
                            ),

                        'created_count' =>
                            $createdCount,

                        'refreshed_count' =>
                            $refreshedCount,

                        'reopened_count' =>
                            $reopenedCount,

                        'accepted_preserved_count' =>
                            $acceptedPreservedCount,

                        'deleted_stale_open_count' =>
                            $deletedStaleOpenCount,

                        'open_count' =>
                            $this->exceptions
                                ->countOpenForPeriod(
                                    $payrollPeriodId
                                ),

                        'total_count' =>
                            $this->exceptions
                                ->countForPeriod(
                                    $payrollPeriodId
                                ),

                        'exceptions' =>
                            $this->exceptions
                                ->allForPeriod(
                                    $payrollPeriodId
                                )
                    ];
                }
            );
    }


    /**
     * @return array<string,mixed>
     */
    private function requireRefreshablePeriod(
        int $payrollPeriodId
    ): array
    {
        if ($payrollPeriodId < 1) {

            throw new InvalidArgumentException(
                'A valid payroll period ID is required.'
            );
        }


        $period =
            $this->payrollPeriods
                ->find(
                    $payrollPeriodId
                );


        if ($period === null) {

            throw new RuntimeException(
                'The payroll period could not be found.'
            );
        }


        $status =
            (string)(
                $period['status']
                ??
                ''
            );


        if (
            !in_array(
                $status,
                [
                    'open',
                    'under_review'
                ],
                true
            )
        ) {

            throw new RuntimeException(
                'Payroll exceptions may be refreshed only while a payroll period is open or under review.'
            );
        }


        return $period;
    }


    /**
     * @param array<string,mixed> $period
     * @param array<string,mixed> $summary
     */
    private function validateSummaryRange(
        array $period,
        array $summary
    ): void
    {
        $summaryStart =
            trim(
                (string)(
                    $summary['start_date']
                    ??
                    ''
                )
            );


        $summaryEnd =
            trim(
                (string)(
                    $summary['end_date']
                    ??
                    ''
                )
            );


        if (
            $summaryStart
            !==
            (string)$period['start_date']
            ||
            $summaryEnd
            !==
            (string)$period['end_date']
        ) {

            throw new InvalidArgumentException(
                'The payroll workspace summary does not match the payroll period date range.'
            );
        }


        if (
            !isset(
                $summary['employees']
            )
            ||
            !is_array(
                $summary['employees']
            )
        ) {

            throw new InvalidArgumentException(
                'The payroll workspace summary does not contain a valid employee list.'
            );
        }
    }


    /**
     * @param array<string,mixed> $summary
     *
     * @return array<int,array<string,mixed>>
     */
    private function detectedExceptions(
        int $payrollPeriodId,
        array $summary
    ): array
    {
        $detected = [];


        foreach (
            $summary['employees']
            as $employee
        ) {
            if (!is_array($employee)) {

                continue;
            }


            $employeeId =
                (int)(
                    $employee['employee_id']
                    ??
                    0
                );


            if ($employeeId < 1) {

                continue;
            }


            $days =
                $employee['days']
                ??
                [];


            if (!is_array($days)) {

                continue;
            }


            foreach ($days as $day) {

                if (!is_array($day)) {

                    continue;
                }


                $exceptionDate =
                    trim(
                        (string)(
                            $day['date']
                            ??
                            ''
                        )
                    );


                $errors =
                    $day['errors']
                    ??
                    [];


                if (!is_array($errors)) {

                    continue;
                }


                foreach ($errors as $error) {

                    if (
                        !is_string(
                            $error
                        )
                    ) {

                        continue;
                    }


                    $description =
                        $this->normalizeDescription(
                            $error
                        );


                    if ($description === '') {

                        continue;
                    }


                    $exceptionType =
                        $this->exceptionType(
                            $description
                        );


                    $exceptionKey =
                        $this->exceptionKey(
                            $employeeId,
                            $exceptionDate,
                            $exceptionType,
                            $description
                        );


                    $detected[$exceptionKey] = [
                        'payroll_period_id' =>
                            $payrollPeriodId,

                        'employee_id' =>
                            $employeeId,

                        'exception_key' =>
                            $exceptionKey,

                        'exception_type' =>
                            $exceptionType,

                        'exception_date' =>
                            $exceptionDate === ''
                                ? null
                                : $exceptionDate,

                        'description' =>
                            $description
                    ];
                }
            }
        }


        ksort(
            $detected
        );


        return
            array_values(
                $detected
            );
    }


    private function exceptionKey(
        int $employeeId,
        string $exceptionDate,
        string $exceptionType,
        string $description
    ): string
    {
        $keySource =
            implode(
                '|',
                [
                    (string)$employeeId,
                    $exceptionDate,
                    $exceptionType,
                    strtolower(
                        $description
                    )
                ]
            );


        return
            'workspace:'
            .
            hash(
                'sha256',
                $keySource
            );
    }


    private function normalizeDescription(
        string $description
    ): string
    {
        $normalized =
            preg_replace(
                '/\s+/u',
                ' ',
                trim(
                    $description
                )
            );


        return
            is_string(
                $normalized
            )
                ? $normalized
                : trim(
                    $description
                );
    }


    private function exceptionType(
        string $description
    ): string
    {
        $normalized =
            strtolower(
                $description
            );


        if (
            $this->containsAny(
                $normalized,
                [
                    'missing clock-out',
                    'missing clock out',
                    'without a clock-out',
                    'without clock-out',
                    'without a clock out',
                    'without clock out'
                ]
            )
        ) {

            return 'missing_clock_out';
        }


        if (
            $this->containsAny(
                $normalized,
                [
                    'missing clock-in',
                    'missing clock in',
                    'without a clock-in',
                    'without clock-in',
                    'without a clock in',
                    'without clock in'
                ]
            )
        ) {

            return 'missing_clock_in';
        }


        if (
            str_contains(
                $normalized,
                'meal'
            )
            &&
            $this->containsAny(
                $normalized,
                [
                    'incomplete',
                    'missing',
                    'unmatched',
                    'without'
                ]
            )
        ) {

            return 'unmatched_meal';
        }


        if (
            str_contains(
                $normalized,
                'break'
            )
            &&
            $this->containsAny(
                $normalized,
                [
                    'incomplete',
                    'missing',
                    'unmatched',
                    'without'
                ]
            )
        ) {

            return 'unmatched_break';
        }


        if (
            $this->containsAny(
                $normalized,
                [
                    'overlap',
                    'overlapping'
                ]
            )
        ) {

            return 'overlapping_punch_activity';
        }


        if (
            $this->containsAny(
                $normalized,
                [
                    'zero duration',
                    'zero-duration'
                ]
            )
        ) {

            return 'zero_duration_shift';
        }


        if (
            $this->containsAny(
                $normalized,
                [
                    'sequence',
                    'unsupported punch type',
                    'unexpected punch'
                ]
            )
        ) {

            return 'invalid_punch_sequence';
        }


        return 'payroll_calculation_warning';
    }


    /**
     * @param array<int,string> $needles
     */
    private function containsAny(
        string $value,
        array $needles
    ): bool
    {
        foreach ($needles as $needle) {

            if (
                str_contains(
                    $value,
                    $needle
                )
            ) {

                return true;
            }
        }


        return false;
    }


    private function transactional(
        callable $operation
    ): mixed
    {
        $startedTransaction =
            !$this->db
                ->inTransaction();


        if ($startedTransaction) {

            $this->db
                ->beginTransaction();
        }


        try {

            $result =
                $operation();


            if (
                $startedTransaction
                &&
                $this->db->inTransaction()
            ) {

                $this->db
                    ->commit();
            }


            return $result;

        } catch (Throwable $exception) {

            if (
                $startedTransaction
                &&
                $this->db->inTransaction()
            ) {

                $this->db
                    ->rollBack();
            }


            throw $exception;
        }
    }
}
