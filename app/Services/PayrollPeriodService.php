<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\PayrollPeriodHistoryRepository;
use App\Repositories\PayrollPeriodRepository;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

final class PayrollPeriodService
{
    private const MAX_PERIOD_NAME_LENGTH = 150;

    private const MAX_PERIOD_DAYS = 31;


    public function __construct(
        private readonly PDO $db,
        private readonly PayrollPeriodRepository $payrollPeriodRepository,
        private readonly PayrollPeriodHistoryRepository $historyRepository
    )
    {
    }


    /**
     * @return array<int,array<string,mixed>>
     */
    public function all(): array
    {
        return
            $this->payrollPeriodRepository
                ->all();
    }


    /**
     * @return array<int,array<string,mixed>>
     */
    public function active(): array
    {
        return
            $this->payrollPeriodRepository
                ->active();
    }


    /**
     * @return array<int,array<string,mixed>>
     */
    public function removed(): array
    {
        return
            $this->payrollPeriodRepository
                ->removed();
    }


    public function find(
        int $payrollPeriodId
    ): ?array
    {
        if ($payrollPeriodId < 1) {

            return null;
        }


        return
            $this->payrollPeriodRepository
                ->find(
                    $payrollPeriodId
                );
    }


    public function requirePeriod(
        int $payrollPeriodId
    ): array
    {
        if ($payrollPeriodId < 1) {

            throw new InvalidArgumentException(
                'A valid payroll period ID is required.'
            );
        }


        $period =
            $this->payrollPeriodRepository
                ->find(
                    $payrollPeriodId
                );


        if ($period === null) {

            throw new RuntimeException(
                'The requested payroll period was not found.'
            );
        }


        return $period;
    }


    public function requireEditableDraft(
        int $payrollPeriodId
    ): array
    {
        $period =
            $this->requirePeriod(
                $payrollPeriodId
            );


        if (
            (
                $period['archived_at']
                ??
                null
            )
            !==
            null
        ) {
            throw new InvalidArgumentException(
                'Archived payroll periods cannot be edited.'
            );
        }


        if (
            (
                $period['voided_at']
                ??
                null
            )
            !==
            null
        ) {
            throw new InvalidArgumentException(
                'Voided payroll periods cannot be edited.'
            );
        }


        if (
            (
                $period['status']
                ??
                ''
            )
            !==
            'open'
        ) {
            throw new InvalidArgumentException(
                'Only active open payroll periods can be edited.'
            );
        }


        return $period;
    }


    public function create(
        array $data,
        int $createdByUserId
    ): int
    {
        if ($createdByUserId < 1) {

            throw new InvalidArgumentException(
                'A valid creating user is required.'
            );
        }


        $validatedData =
            $this->validateCreateData(
                $data
            );


        $startedTransaction =
            !$this->db->inTransaction();


        try {

            if ($startedTransaction) {

                $this->db
                    ->beginTransaction();
            }


            $overlappingPeriod =
                $this->payrollPeriodRepository
                    ->findOverlapping(
                        $validatedData['start_date'],
                        $validatedData['end_date']
                    );


            if ($overlappingPeriod !== null) {

                throw new InvalidArgumentException(
                    sprintf(
                        'The payroll period overlaps "%s" (%s through %s).',
                        (string)$overlappingPeriod['period_name'],
                        (string)$overlappingPeriod['start_date'],
                        (string)$overlappingPeriod['end_date']
                    )
                );
            }


            $payrollPeriodId =
                $this->payrollPeriodRepository
                    ->create(
                        [
                            'period_name' =>
                                $validatedData['period_name'],

                            'start_date' =>
                                $validatedData['start_date'],

                            'end_date' =>
                                $validatedData['end_date'],

                            'created_by_user_id' =>
                                $createdByUserId
                        ]
                    );


            if ($payrollPeriodId < 1) {

                throw new RuntimeException(
                    'The payroll period could not be created.'
                );
            }


            $historyId =
                $this->historyRepository
                    ->create(
                        [
                            'payroll_period_id' =>
                                $payrollPeriodId,

                            'action' =>
                                'created',

                            'previous_status' =>
                                null,

                            'new_status' =>
                                'open',

                            'reason' =>
                                null,

                            'user_id' =>
                                $createdByUserId
                        ]
                    );


            if ($historyId < 1) {

                throw new RuntimeException(
                    'The payroll period creation history could not be recorded.'
                );
            }


            if ($startedTransaction) {

                $this->db
                    ->commit();
            }


            return $payrollPeriodId;

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


    public function updateDraft(
        int $payrollPeriodId,
        array $data,
        int $updatedByUserId
    ): bool
    {
        if ($updatedByUserId < 1) {
            throw new InvalidArgumentException(
                'A valid updating user is required.'
            );
        }


        $validatedData =
            $this->validateCreateData(
                $data
            );

        $startedTransaction =
            !$this->db->inTransaction();


        try {
            if ($startedTransaction) {
                $this->db
                    ->beginTransaction();
            }


            $period =
                $this->requireEditableDraft(
                    $payrollPeriodId
                );

            $changes = [];

            foreach (
                [
                    'period_name',
                    'start_date',
                    'end_date'
                ]
                as
                $field
            ) {
                $previousValue =
                    (string)(
                        $period[$field]
                        ??
                        ''
                    );

                $updatedValue =
                    (string)$validatedData[$field];

                if (
                    $previousValue
                    ===
                    $updatedValue
                ) {
                    continue;
                }

                $changes[$field] = [
                    'from' =>
                        $previousValue,

                    'to' =>
                        $updatedValue
                ];
            }


            if ($changes === []) {
                if ($startedTransaction) {
                    $this->db
                        ->commit();
                }

                return false;
            }


            $overlappingPeriod =
                $this->payrollPeriodRepository
                    ->findOverlapping(
                        $validatedData['start_date'],
                        $validatedData['end_date'],
                        $payrollPeriodId
                    );


            if ($overlappingPeriod !== null) {
                throw new InvalidArgumentException(
                    sprintf(
                        'The payroll period overlaps "%s" (%s through %s).',
                        (string)$overlappingPeriod['period_name'],
                        (string)$overlappingPeriod['start_date'],
                        (string)$overlappingPeriod['end_date']
                    )
                );
            }


            $updated =
                $this->payrollPeriodRepository
                    ->updateDraft(
                        $payrollPeriodId,
                        [
                            'period_name' =>
                                $validatedData['period_name'],

                            'start_date' =>
                                $validatedData['start_date'],

                            'end_date' =>
                                $validatedData['end_date']
                        ]
                    );


            if (!$updated) {
                throw new RuntimeException(
                    'The payroll period could not be updated. It may no longer be an editable draft.'
                );
            }


            $historyReason =
                json_encode(
                    [
                        'changes' =>
                            $changes
                    ],
                    JSON_UNESCAPED_SLASHES
                    |
                    JSON_UNESCAPED_UNICODE
                );


            if ($historyReason === false) {
                throw new RuntimeException(
                    'The payroll period update history could not be encoded.'
                );
            }


            $historyId =
                $this->historyRepository
                    ->create(
                        [
                            'payroll_period_id' =>
                                $payrollPeriodId,

                            'action' =>
                                'updated',

                            'previous_status' =>
                                'open',

                            'new_status' =>
                                'open',

                            'reason' =>
                                $historyReason,

                            'user_id' =>
                                $updatedByUserId
                        ]
                    );


            if ($historyId < 1) {
                throw new RuntimeException(
                    'The payroll period update history could not be recorded.'
                );
            }


            if ($startedTransaction) {
                $this->db
                    ->commit();
            }


            return true;

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


    /**
     * @return array{
     *     period_name:string,
     *     start_date:string,
     *     end_date:string,
     *     day_count:int
     * }
     */
    public function validateCreateData(
        array $data
    ): array
    {
        $periodName =
            trim(
                (string)(
                    $data['period_name']
                    ??
                    ''
                )
            );


        if ($periodName === '') {

            throw new InvalidArgumentException(
                'The payroll period name is required.'
            );
        }


        if (
            $this->textLength(
                $periodName
            )
            >
            self::MAX_PERIOD_NAME_LENGTH
        ) {

            throw new InvalidArgumentException(
                sprintf(
                    'The payroll period name may not exceed %d characters.',
                    self::MAX_PERIOD_NAME_LENGTH
                )
            );
        }


        $startDateValue =
            trim(
                (string)(
                    $data['start_date']
                    ??
                    ''
                )
            );


        $endDateValue =
            trim(
                (string)(
                    $data['end_date']
                    ??
                    ''
                )
            );


        $startDate =
            $this->parseDate(
                $startDateValue,
                'start date'
            );


        $endDate =
            $this->parseDate(
                $endDateValue,
                'end date'
            );


        if ($endDate < $startDate) {

            throw new InvalidArgumentException(
                'The payroll period end date must be on or after the start date.'
            );
        }


        $dayCount =
            (int)$startDate
                ->diff(
                    $endDate
                )
                ->format(
                    '%a'
                )
            +
            1;


        if ($dayCount > self::MAX_PERIOD_DAYS) {

            throw new InvalidArgumentException(
                sprintf(
                    'A payroll period may not exceed %d calendar days.',
                    self::MAX_PERIOD_DAYS
                )
            );
        }


        return [
            'period_name' =>
                $periodName,

            'start_date' =>
                $startDate->format(
                    'Y-m-d'
                ),

            'end_date' =>
                $endDate->format(
                    'Y-m-d'
                ),

            'day_count' =>
                $dayCount
        ];
    }


    private function parseDate(
        string $value,
        string $fieldName
    ): DateTimeImmutable
    {
        if ($value === '') {

            throw new InvalidArgumentException(
                sprintf(
                    'The payroll period %s is required.',
                    $fieldName
                )
            );
        }


        $date =
            DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $value
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
            $value
        ) {

            throw new InvalidArgumentException(
                sprintf(
                    'The payroll period %s must use a valid YYYY-MM-DD date.',
                    $fieldName
                )
            );
        }


        return $date;
    }


    private function textLength(
        string $value
    ): int
    {
        if (function_exists('mb_strlen')) {

            return
                mb_strlen(
                    $value,
                    'UTF-8'
                );
        }


        return
            strlen(
                $value
            );
    }
}
