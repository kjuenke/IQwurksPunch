<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuditRepository;
use App\Repositories\PayrollPeriodHistoryRepository;
use App\Repositories\PayrollPeriodRemovalRepository;
use App\Repositories\UserRepository;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

class PayrollPeriodRemovalService
{
    private const DELETE_CONFIRMATION =
        'DELETE';

    private const VOID_CONFIRMATION =
        'VOID';

    private const MINIMUM_REASON_LENGTH =
        10;

    private const MAXIMUM_REASON_LENGTH =
        1000;

    private PDO $db;

    private PayrollPeriodRemovalRepository $periods;

    private PayrollPeriodHistoryRepository $history;

    private UserRepository $users;

    private AuditRepository $audit;


    public function __construct(
        PDO $db,
        PayrollPeriodRemovalRepository $periods,
        PayrollPeriodHistoryRepository $history,
        UserRepository $users,
        AuditRepository $audit
    )
    {
        $this->db =
            $db;

        $this->periods =
            $periods;

        $this->history =
            $history;

        $this->users =
            $users;

        $this->audit =
            $audit;
    }


    /**
     * @return array<string,mixed>
     */
    public function analyze(
        int $payrollPeriodId,
        int $actingUserId
    ): array
    {
        $this->requireActiveAdministrator(
            $actingUserId
        );

        $period =
            $this->requirePeriod(
                $payrollPeriodId
            );

        $dependencies =
            $this->periods
                ->dependencySummary(
                    $payrollPeriodId
                );


        return [
            'period' =>
                $period,

            'dependencies' =>
                $dependencies,

            'is_archived' =>
                $this->isArchived(
                    $period
                ),

            'is_voided' =>
                $this->isVoided(
                    $period
                ),

            'can_delete_draft' =>
                $this->canDeleteDraft(
                    $period,
                    $dependencies
                ),

            'can_archive' =>
                !$this->isArchived(
                    $period
                )
                &&
                !$this->isVoided(
                    $period
                ),

            'can_void' =>
                !$this->isArchived(
                    $period
                )
                &&
                !$this->isVoided(
                    $period
                )
                &&
                in_array(
                    $this->status(
                        $period
                    ),
                    [
                        'approved',
                        'locked'
                    ],
                    true
                )
        ];
    }


    /**
     * @return array<string,mixed>
     */
    public function deleteDraft(
        int $payrollPeriodId,
        int $actingUserId,
        string $confirmation
    ): array
    {
        if (
            trim(
                $confirmation
            )
            !==
            self::DELETE_CONFIRMATION
        ) {
            throw new InvalidArgumentException(
                'Enter DELETE exactly to permanently remove the draft payroll period.'
            );
        }


        return $this->transactional(
            function () use (
                $payrollPeriodId,
                $actingUserId
            ): array {
                $this->requireActiveAdministrator(
                    $actingUserId
                );

                $period =
                    $this->requirePeriod(
                        $payrollPeriodId
                    );

                $dependencies =
                    $this->periods
                        ->dependencySummary(
                            $payrollPeriodId
                        );


                if (
                    !$this->canDeleteDraft(
                        $period,
                        $dependencies
                    )
                ) {
                    throw new RuntimeException(
                        'Only an untouched open payroll period can be permanently deleted. Archive the period instead.'
                    );
                }


                $this->recordAudit(
                    'payroll_period.deleted',
                    $period,
                    $actingUserId,
                    null
                );


                $deletedHistoryCount =
                    $this->periods
                        ->deleteHistory(
                            $payrollPeriodId
                        );


                if ($deletedHistoryCount !== 1) {
                    throw new RuntimeException(
                        'The draft payroll period history changed before deletion. Reload the period and try again.'
                    );
                }


                if (
                    !$this->periods
                        ->deletePeriod(
                            $payrollPeriodId
                        )
                ) {
                    throw new RuntimeException(
                        'The draft payroll period could not be deleted.'
                    );
                }


                return $period;
            }
        );
    }


    /**
     * @return array<string,mixed>
     */
    public function archive(
        int $payrollPeriodId,
        int $actingUserId,
        string $reason
    ): array
    {
        $normalizedReason =
            $this->validateReason(
                $reason,
                'archive'
            );


        return $this->transactional(
            function () use (
                $payrollPeriodId,
                $actingUserId,
                $normalizedReason
            ): array {
                $this->requireActiveAdministrator(
                    $actingUserId
                );

                $period =
                    $this->requirePeriod(
                        $payrollPeriodId
                    );


                if (
                    $this->isVoided(
                        $period
                    )
                ) {
                    throw new RuntimeException(
                        'A voided payroll period cannot be archived.'
                    );
                }


                if (
                    $this->isArchived(
                        $period
                    )
                ) {
                    throw new RuntimeException(
                        'The payroll period is already archived.'
                    );
                }


                if (
                    !$this->periods
                        ->markArchived(
                            $payrollPeriodId,
                            $actingUserId,
                            $normalizedReason
                        )
                ) {
                    throw new RuntimeException(
                        'The payroll period changed before it could be archived. Reload the period and try again.'
                    );
                }


                $status =
                    $this->status(
                        $period
                    );


                $this->recordHistory(
                    $payrollPeriodId,
                    'archived',
                    $status,
                    $status,
                    $normalizedReason,
                    $actingUserId
                );

                $this->recordAudit(
                    'payroll_period.archived',
                    $period,
                    $actingUserId,
                    $normalizedReason
                );


                return $this->requirePeriod(
                    $payrollPeriodId
                );
            }
        );
    }


    /**
     * @return array<string,mixed>
     */
    public function void(
        int $payrollPeriodId,
        int $actingUserId,
        string $reason,
        string $confirmation
    ): array
    {
        if (
            trim(
                $confirmation
            )
            !==
            self::VOID_CONFIRMATION
        ) {
            throw new InvalidArgumentException(
                'Enter VOID exactly to invalidate the finalized payroll period.'
            );
        }


        $normalizedReason =
            $this->validateReason(
                $reason,
                'void'
            );


        return $this->transactional(
            function () use (
                $payrollPeriodId,
                $actingUserId,
                $normalizedReason
            ): array {
                $this->requireActiveAdministrator(
                    $actingUserId
                );

                $period =
                    $this->requirePeriod(
                        $payrollPeriodId
                    );

                $status =
                    $this->status(
                        $period
                    );


                if (
                    !in_array(
                        $status,
                        [
                            'approved',
                            'locked'
                        ],
                        true
                    )
                ) {
                    throw new RuntimeException(
                        'Only an approved or locked payroll period can be voided.'
                    );
                }


                if (
                    $this->isArchived(
                        $period
                    )
                ) {
                    throw new RuntimeException(
                        'An archived payroll period cannot be voided.'
                    );
                }


                if (
                    $this->isVoided(
                        $period
                    )
                ) {
                    throw new RuntimeException(
                        'The payroll period is already voided.'
                    );
                }


                if (
                    !$this->periods
                        ->markVoided(
                            $payrollPeriodId,
                            $actingUserId,
                            $normalizedReason
                        )
                ) {
                    throw new RuntimeException(
                        'The payroll period changed before it could be voided. Reload the period and try again.'
                    );
                }


                $this->recordHistory(
                    $payrollPeriodId,
                    'voided',
                    $status,
                    $status,
                    $normalizedReason,
                    $actingUserId
                );

                $this->recordAudit(
                    'payroll_period.voided',
                    $period,
                    $actingUserId,
                    $normalizedReason
                );


                return $this->requirePeriod(
                    $payrollPeriodId
                );
            }
        );
    }


    /**
     * @param array<string,mixed> $period
     * @param array<string,int> $dependencies
     */
    private function canDeleteDraft(
        array $period,
        array $dependencies
    ): bool
    {
        return
            $this->status(
                $period
            )
            ===
            'open'
            &&
            !$this->isArchived(
                $period
            )
            &&
            !$this->isVoided(
                $period
            )
            &&
            !$this->hasWorkflowMetadata(
                $period
            )
            &&
            $dependencies['history_count']
            ===
            1
            &&
            $dependencies['created_history_count']
            ===
            1
            &&
            $dependencies['review_note_count']
            ===
            0
            &&
            $dependencies['exception_count']
            ===
            0
            &&
            $dependencies['resolution_count']
            ===
            0;
    }


    /**
     * @param array<string,mixed> $period
     */
    private function hasWorkflowMetadata(
        array $period
    ): bool
    {
        foreach (
            [
                'reviewed_by_user_id',
                'approved_by_user_id',
                'locked_by_user_id',
                'review_started_at',
                'approved_at',
                'locked_at'
            ]
            as $field
        ) {
            if (
                isset(
                    $period[$field]
                )
                &&
                $period[$field] !== ''
            ) {
                return true;
            }
        }


        return false;
    }


    private function validateReason(
        string $reason,
        string $action
    ): string
    {
        $normalizedReason =
            trim(
                $reason
            );

        $length =
            strlen(
                $normalizedReason
            );


        if (
            $length
            <
            self::MINIMUM_REASON_LENGTH
        ) {
            throw new InvalidArgumentException(
                ucfirst(
                    $action
                )
                .
                ' reason must contain at least '
                .
                self::MINIMUM_REASON_LENGTH
                .
                ' characters.'
            );
        }


        if (
            $length
            >
            self::MAXIMUM_REASON_LENGTH
        ) {
            throw new InvalidArgumentException(
                ucfirst(
                    $action
                )
                .
                ' reason may not exceed '
                .
                self::MAXIMUM_REASON_LENGTH
                .
                ' characters.'
            );
        }


        return $normalizedReason;
    }


    /**
     * @return array<string,mixed>
     */
    private function requireActiveAdministrator(
        int $actingUserId
    ): array
    {
        if ($actingUserId < 1) {
            throw new InvalidArgumentException(
                'A valid acting administrator is required.'
            );
        }


        $user =
            $this->users->findById(
                $actingUserId
            );


        if ($user === null) {
            throw new RuntimeException(
                'The acting administrator was not found.'
            );
        }


        if (
            (int)(
                $user['active']
                ??
                0
            )
            !==
            1
        ) {
            throw new RuntimeException(
                'The acting administrator is inactive.'
            );
        }


        $role =
            strtolower(
                trim(
                    (string)(
                        $user['role']
                        ??
                        ''
                    )
                )
            );


        if ($role !== 'admin') {
            throw new RuntimeException(
                'Only active administrators can delete, archive, or void payroll periods.'
            );
        }


        return $user;
    }


    /**
     * @return array<string,mixed>
     */
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
            $this->periods->find(
                $payrollPeriodId
            );


        if ($period === null) {
            throw new RuntimeException(
                'The requested payroll period was not found.'
            );
        }


        return $period;
    }


    /**
     * @param array<string,mixed> $period
     */
    private function status(
        array $period
    ): string
    {
        return strtolower(
            trim(
                (string)(
                    $period['status']
                    ??
                    ''
                )
            )
        );
    }


    /**
     * @param array<string,mixed> $period
     */
    private function isArchived(
        array $period
    ): bool
    {
        return
            isset(
                $period['archived_at']
            )
            &&
            trim(
                (string)$period['archived_at']
            )
            !==
            '';
    }


    /**
     * @param array<string,mixed> $period
     */
    private function isVoided(
        array $period
    ): bool
    {
        return
            isset(
                $period['voided_at']
            )
            &&
            trim(
                (string)$period['voided_at']
            )
            !==
            '';
    }


    private function recordHistory(
        int $payrollPeriodId,
        string $action,
        string $previousStatus,
        string $newStatus,
        string $reason,
        int $actingUserId
    ): void
    {
        $historyId =
            $this->history->create(
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
                'The payroll-period removal history could not be recorded.'
            );
        }
    }


    /**
     * @param array<string,mixed> $period
     */
    private function recordAudit(
        string $action,
        array $period,
        int $actingUserId,
        ?string $reason
    ): void
    {
        $details =
            json_encode(
                [
                    'payroll_period_id' =>
                        (int)$period['id'],

                    'period_name' =>
                        (string)$period['period_name'],

                    'start_date' =>
                        (string)$period['start_date'],

                    'end_date' =>
                        (string)$period['end_date'],

                    'status' =>
                        $this->status(
                            $period
                        ),

                    'reason' =>
                        $reason
                ],
                JSON_UNESCAPED_SLASHES
                |
                JSON_UNESCAPED_UNICODE
            );


        if (!is_string($details)) {
            throw new RuntimeException(
                'The payroll-period audit details could not be encoded.'
            );
        }


        if (
            !$this->audit->create(
                $action,
                $details,
                $actingUserId
            )
        ) {
            throw new RuntimeException(
                'The payroll-period removal audit record could not be created.'
            );
        }
    }


    /**
     * @template T
     *
     * @param callable():T $callback
     *
     * @return T
     */
    private function transactional(
        callable $callback
    ): mixed
    {
        $startedTransaction =
            !$this->db->inTransaction();


        if ($startedTransaction) {
            $this->db->beginTransaction();
        }


        try {
            $result =
                $callback();


            if (
                $startedTransaction
                &&
                $this->db->inTransaction()
            ) {
                $this->db->commit();
            }


            return $result;
        } catch (Throwable $exception) {
            if (
                $startedTransaction
                &&
                $this->db->inTransaction()
            ) {
                $this->db->rollBack();
            }


            throw $exception;
        }
    }
}
