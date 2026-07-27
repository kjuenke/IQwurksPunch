<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\PayrollExceptionResolutionRepository;
use App\Repositories\PayrollPeriodHistoryRepository;
use App\Repositories\PayrollPeriodRepository;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

final class PayrollApprovalService
{
    private const AUTHORIZED_ROLES = [
        'admin',
        'supervisor'
    ];

    private const MAX_REASON_LENGTH = 1000;

    private const MIN_REOPEN_REASON_LENGTH = 10;


    public function __construct(
        private readonly PDO $db,
        private readonly PayrollPeriodRepository $payrollPeriodRepository,
        private readonly PayrollPeriodHistoryRepository $historyRepository,
        private readonly PayrollExceptionResolutionRepository $exceptionRepository
    )
    {
    }


    public function beginReview(
        int $payrollPeriodId,
        int $actingUserId
    ): array
    {
        return
            $this->transactional(
                function () use (
                    $payrollPeriodId,
                    $actingUserId
                ): array {

                    $this->requireAuthorizedUser(
                        $actingUserId
                    );


                    $period =
                        $this->requirePeriod(
                            $payrollPeriodId
                        );


                    $this->requireStatus(
                        $period,
                        'open',
                        'Only an open payroll period can begin review.'
                    );


                    $updated =
                        $this->payrollPeriodRepository
                            ->beginReview(
                                $payrollPeriodId,
                                $actingUserId
                            );


                    if (!$updated) {

                        throw new RuntimeException(
                            'The payroll period changed before review could begin. Reload the period and try again.'
                        );
                    }


                    $this->recordHistory(
                        payrollPeriodId:
                            $payrollPeriodId,

                        action:
                            'review_started',

                        previousStatus:
                            'open',

                        newStatus:
                            'under_review',

                        reason:
                            null,

                        actingUserId:
                            $actingUserId
                    );


                    return
                        $this->requirePeriod(
                            $payrollPeriodId
                        );
                }
            );
    }


    public function returnToOpen(
        int $payrollPeriodId,
        int $actingUserId,
        ?string $reason = null
    ): array
    {
        $normalizedReason =
            $this->normalizeOptionalReason(
                $reason
            );


        return
            $this->transactional(
                function () use (
                    $payrollPeriodId,
                    $actingUserId,
                    $normalizedReason
                ): array {

                    $this->requireAuthorizedUser(
                        $actingUserId
                    );


                    $period =
                        $this->requirePeriod(
                            $payrollPeriodId
                        );


                    $this->requireStatus(
                        $period,
                        'under_review',
                        'Only a payroll period under review can be returned to open.'
                    );


                    $updated =
                        $this->payrollPeriodRepository
                            ->returnToOpen(
                                $payrollPeriodId
                            );


                    if (!$updated) {

                        throw new RuntimeException(
                            'The payroll period changed before it could be returned to open. Reload the period and try again.'
                        );
                    }


                    $this->recordHistory(
                        payrollPeriodId:
                            $payrollPeriodId,

                        action:
                            'returned_to_open',

                        previousStatus:
                            'under_review',

                        newStatus:
                            'open',

                        reason:
                            $normalizedReason,

                        actingUserId:
                            $actingUserId
                    );


                    return
                        $this->requirePeriod(
                            $payrollPeriodId
                        );
                }
            );
    }


    public function approve(
        int $payrollPeriodId,
        int $actingUserId,
        bool $confirmed
    ): array
    {
        if (!$confirmed) {

            throw new InvalidArgumentException(
                'Payroll approval must be explicitly confirmed.'
            );
        }


        return
            $this->transactional(
                function () use (
                    $payrollPeriodId,
                    $actingUserId
                ): array {

                    $this->requireAuthorizedUser(
                        $actingUserId
                    );


                    $period =
                        $this->requirePeriod(
                            $payrollPeriodId
                        );


                    $this->requireStatus(
                        $period,
                        'under_review',
                        'Only a payroll period under review can be approved.'
                    );


                    $openExceptionCount =
                        $this->exceptionRepository
                            ->countOpenForPeriod(
                                $payrollPeriodId
                            );


                    if ($openExceptionCount > 0) {

                        throw new RuntimeException(
                            sprintf(
                                'Payroll approval is blocked because %d unresolved payroll exception%s remain.',
                                $openExceptionCount,
                                $openExceptionCount === 1
                                    ? ''
                                    : 's'
                            )
                        );
                    }


                    $updated =
                        $this->payrollPeriodRepository
                            ->approve(
                                $payrollPeriodId,
                                $actingUserId
                            );


                    if (!$updated) {

                        throw new RuntimeException(
                            'The payroll period changed before approval could be completed. Reload the period and try again.'
                        );
                    }


                    $this->recordHistory(
                        payrollPeriodId:
                            $payrollPeriodId,

                        action:
                            'approved',

                        previousStatus:
                            'under_review',

                        newStatus:
                            'approved',

                        reason:
                            null,

                        actingUserId:
                            $actingUserId
                    );


                    return
                        $this->requirePeriod(
                            $payrollPeriodId
                        );
                }
            );
    }


    public function lock(
        int $payrollPeriodId,
        int $actingUserId,
        string $confirmation
    ): array
    {
        if (trim($confirmation) !== 'LOCK') {

            throw new InvalidArgumentException(
                'Type LOCK exactly to finalize the payroll period.'
            );
        }


        return
            $this->transactional(
                function () use (
                    $payrollPeriodId,
                    $actingUserId
                ): array {

                    $this->requireAuthorizedUser(
                        $actingUserId
                    );


                    $period =
                        $this->requirePeriod(
                            $payrollPeriodId
                        );


                    $this->requireStatus(
                        $period,
                        'approved',
                        'Only an approved payroll period can be locked.'
                    );


                    if (
                        empty(
                            $period['approved_by_user_id']
                        )
                        ||
                        empty(
                            $period['approved_at']
                        )
                    ) {

                        throw new RuntimeException(
                            'The payroll period does not contain complete approval metadata and cannot be locked.'
                        );
                    }


                    $updated =
                        $this->payrollPeriodRepository
                            ->lock(
                                $payrollPeriodId,
                                $actingUserId
                            );


                    if (!$updated) {

                        throw new RuntimeException(
                            'The payroll period changed before it could be locked. Reload the period and try again.'
                        );
                    }


                    $this->recordHistory(
                        payrollPeriodId:
                            $payrollPeriodId,

                        action:
                            'locked',

                        previousStatus:
                            'approved',

                        newStatus:
                            'locked',

                        reason:
                            null,

                        actingUserId:
                            $actingUserId
                    );


                    return
                        $this->requirePeriod(
                            $payrollPeriodId
                        );
                }
            );
    }


    public function reopen(
        int $payrollPeriodId,
        int $actingUserId,
        string $reason
    ): array
    {
        $normalizedReason =
            $this->validateReopenReason(
                $reason
            );


        return
            $this->transactional(
                function () use (
                    $payrollPeriodId,
                    $actingUserId,
                    $normalizedReason
                ): array {

                    $this->requireAuthorizedUser(
                        $actingUserId
                    );


                    $period =
                        $this->requirePeriod(
                            $payrollPeriodId
                        );


                    $previousStatus =
                        (string)$period['status'];


                    if (
                        !in_array(
                            $previousStatus,
                            [
                                'approved',
                                'locked'
                            ],
                            true
                        )
                    ) {

                        throw new RuntimeException(
                            'Only an approved or locked payroll period can be reopened.'
                        );
                    }


                    $updated =
                        $this->payrollPeriodRepository
                            ->reopenToReview(
                                $payrollPeriodId,
                                $actingUserId
                            );


                    if (!$updated) {

                        throw new RuntimeException(
                            'The payroll period changed before it could be reopened. Reload the period and try again.'
                        );
                    }


                    $this->recordHistory(
                        payrollPeriodId:
                            $payrollPeriodId,

                        action:
                            'reopened',

                        previousStatus:
                            $previousStatus,

                        newStatus:
                            'under_review',

                        reason:
                            $normalizedReason,

                        actingUserId:
                            $actingUserId
                    );


                    return
                        $this->requirePeriod(
                            $payrollPeriodId
                        );
                }
            );
    }


    private function requireAuthorizedUser(
        int $actingUserId
    ): array
    {
        if ($actingUserId < 1) {

            throw new InvalidArgumentException(
                'A valid acting user is required.'
            );
        }


        $statement =
            $this->db->prepare(
                "
                SELECT
                    id,
                    username,
                    role,
                    active

                FROM users

                WHERE id =
                    :id

                LIMIT 1
                "
            );


        $statement->execute(
            [
                'id' =>
                    $actingUserId
            ]
        );


        $user =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );


        if (!is_array($user)) {

            throw new RuntimeException(
                'The acting user was not found.'
            );
        }


        if ((int)$user['active'] !== 1) {

            throw new RuntimeException(
                'The acting user is inactive.'
            );
        }


        $role =
            strtolower(
                trim(
                    (string)$user['role']
                )
            );


        if (
            !in_array(
                $role,
                self::AUTHORIZED_ROLES,
                true
            )
        ) {

            throw new RuntimeException(
                'The acting user is not authorized to manage payroll approval.'
            );
        }


        return $user;
    }


    private function requirePeriod(
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


    private function requireStatus(
        array $period,
        string $requiredStatus,
        string $message
    ): void
    {
        if (
            (string)(
                $period['status']
                ??
                ''
            )
            !==
            $requiredStatus
        ) {

            throw new RuntimeException(
                $message
            );
        }
    }


    private function recordHistory(
        int $payrollPeriodId,
        string $action,
        ?string $previousStatus,
        string $newStatus,
        ?string $reason,
        int $actingUserId
    ): void
    {
        $historyId =
            $this->historyRepository
                ->create(
                    [
                        'payroll_period_id' =>
                            $payrollPeriodId,

                        'action' =>
                            $action,

                        'previous_status' =>
                            $previousStatus,

                        'new_status' =>
                            $newStatus,

                        'reason' =>
                            $reason,

                        'user_id' =>
                            $actingUserId
                    ]
                );


        if ($historyId < 1) {

            throw new RuntimeException(
                'The payroll workflow history could not be recorded.'
            );
        }
    }


    private function normalizeOptionalReason(
        ?string $reason
    ): ?string
    {
        if ($reason === null) {

            return null;
        }


        $normalizedReason =
            trim(
                $reason
            );


        if ($normalizedReason === '') {

            return null;
        }


        if (
            $this->textLength(
                $normalizedReason
            )
            >
            self::MAX_REASON_LENGTH
        ) {

            throw new InvalidArgumentException(
                sprintf(
                    'The workflow reason may not exceed %d characters.',
                    self::MAX_REASON_LENGTH
                )
            );
        }


        return $normalizedReason;
    }


    private function validateReopenReason(
        string $reason
    ): string
    {
        $normalizedReason =
            trim(
                $reason
            );


        if ($normalizedReason === '') {

            throw new InvalidArgumentException(
                'A reason is required to reopen a payroll period.'
            );
        }


        $reasonLength =
            $this->textLength(
                $normalizedReason
            );


        if (
            $reasonLength
            <
            self::MIN_REOPEN_REASON_LENGTH
        ) {

            throw new InvalidArgumentException(
                sprintf(
                    'The reopening reason must contain at least %d characters.',
                    self::MIN_REOPEN_REASON_LENGTH
                )
            );
        }


        if (
            $reasonLength
            >
            self::MAX_REASON_LENGTH
        ) {

            throw new InvalidArgumentException(
                sprintf(
                    'The reopening reason may not exceed %d characters.',
                    self::MAX_REASON_LENGTH
                )
            );
        }


        return $normalizedReason;
    }


    private function transactional(
        callable $operation
    ): mixed
    {
        $startedTransaction =
            !$this->db->inTransaction();


        try {

            if ($startedTransaction) {

                $this->db
                    ->beginTransaction();
            }


            $result =
                $operation();


            if ($startedTransaction) {

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
