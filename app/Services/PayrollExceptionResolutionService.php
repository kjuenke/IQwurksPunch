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

final class PayrollExceptionResolutionService
{
    private PDO $db;

    private PayrollPeriodRepository $payrollPeriods;

    private PayrollExceptionResolutionRepository $exceptions;

    private PayrollPeriodHistoryRepository $history;


    public function __construct(
        PDO $db,
        PayrollPeriodRepository $payrollPeriods,
        PayrollExceptionResolutionRepository $exceptions,
        PayrollPeriodHistoryRepository $history
    )
    {
        $this->db =
            $db;


        $this->payrollPeriods =
            $payrollPeriods;


        $this->exceptions =
            $exceptions;


        $this->history =
            $history;
    }


    /**
     * Mark an exception resolved after its underlying payroll problem
     * has been corrected.
     *
     * @return array<string,mixed>
     */
    public function resolve(
        int $payrollPeriodId,
        int $exceptionId,
        int $actingUserId,
        ?string $resolutionNote = null
    ): array
    {
        $normalizedNote =
            $this->normalizeOptionalNote(
                $resolutionNote
            );


        return
            $this->transition(
                $payrollPeriodId,
                $exceptionId,
                $actingUserId,
                'resolved',
                $normalizedNote
            );
    }


    /**
     * Accept an exception as a documented payroll variance.
     *
     * @return array<string,mixed>
     */
    public function accept(
        int $payrollPeriodId,
        int $exceptionId,
        int $actingUserId,
        string $resolutionNote
    ): array
    {
        $normalizedNote =
            trim(
                $resolutionNote
            );


        if (
            mb_strlen(
                $normalizedNote
            )
            <
            10
        ) {

            throw new InvalidArgumentException(
                'An acceptance explanation of at least 10 characters is required.'
            );
        }


        if (
            mb_strlen(
                $normalizedNote
            )
            >
            1000
        ) {

            throw new InvalidArgumentException(
                'The acceptance explanation cannot exceed 1000 characters.'
            );
        }


        return
            $this->transition(
                $payrollPeriodId,
                $exceptionId,
                $actingUserId,
                'accepted',
                $normalizedNote
            );
    }


    /**
     * @return array<string,mixed>
     */
    private function transition(
        int $payrollPeriodId,
        int $exceptionId,
        int $actingUserId,
        string $targetStatus,
        ?string $resolutionNote
    ): array
    {
        $this->validateIdentifiers(
            $payrollPeriodId,
            $exceptionId,
            $actingUserId
        );


        return
            $this->transactional(
                function () use (
                    $payrollPeriodId,
                    $exceptionId,
                    $actingUserId,
                    $targetStatus,
                    $resolutionNote
                ): array {

                    $this->requireAuthorizedUser(
                        $actingUserId
                    );


                    $period =
                        $this->requireEditablePeriod(
                            $payrollPeriodId
                        );


                    $exception =
                        $this->requirePeriodException(
                            $payrollPeriodId,
                            $exceptionId
                        );


                    $currentStatus =
                        (string)(
                            $exception['resolution_status']
                            ??
                            ''
                        );


                    if ($currentStatus !== 'open') {

                        throw new RuntimeException(
                            sprintf(
                                'This payroll exception is already %s.',
                                str_replace(
                                    '_',
                                    ' ',
                                    $currentStatus
                                )
                            )
                        );
                    }


                    $updated =
                        match ($targetStatus) {

                            'resolved' =>
                                $this->exceptions
                                    ->markResolved(
                                        $exceptionId,
                                        $actingUserId,
                                        $resolutionNote
                                    ),

                            'accepted' =>
                                $this->exceptions
                                    ->markAccepted(
                                        $exceptionId,
                                        $actingUserId,
                                        (string)$resolutionNote
                                    ),

                            default =>
                                throw new RuntimeException(
                                    'The requested payroll exception transition is unsupported.'
                                )
                        };


                    if (!$updated) {

                        throw new RuntimeException(
                            'The payroll exception changed before it could be updated.'
                        );
                    }


                    $periodStatus =
                        (string)(
                            $period['status']
                            ??
                            ''
                        );


                    $this->history
                        ->create(
                            [
                                'payroll_period_id' =>
                                    $payrollPeriodId,

                                'action' =>
                                    $targetStatus === 'accepted'
                                        ? 'exception_accepted'
                                        : 'exception_resolved',

                                'previous_status' =>
                                    $periodStatus,

                                'new_status' =>
                                    $periodStatus,

                                'reason' =>
                                    $this->historyReason(
                                        $exception,
                                        $resolutionNote
                                    ),

                                'user_id' =>
                                    $actingUserId
                            ]
                        );


                    $updatedException =
                        $this->exceptions
                            ->find(
                                $exceptionId
                            );


                    if ($updatedException === null) {

                        throw new RuntimeException(
                            'The payroll exception was updated but could not be reloaded.'
                        );
                    }


                    return $updatedException;
                }
            );
    }


    private function validateIdentifiers(
        int $payrollPeriodId,
        int $exceptionId,
        int $actingUserId
    ): void
    {
        if ($payrollPeriodId < 1) {

            throw new InvalidArgumentException(
                'A valid payroll period ID is required.'
            );
        }


        if ($exceptionId < 1) {

            throw new InvalidArgumentException(
                'A valid payroll exception ID is required.'
            );
        }


        if ($actingUserId < 1) {

            throw new InvalidArgumentException(
                'A valid acting user is required.'
            );
        }
    }


    private function normalizeOptionalNote(
        ?string $resolutionNote
    ): ?string
    {
        if ($resolutionNote === null) {

            return null;
        }


        $normalized =
            trim(
                $resolutionNote
            );


        if ($normalized === '') {

            return null;
        }


        if (
            mb_strlen(
                $normalized
            )
            >
            1000
        ) {

            throw new InvalidArgumentException(
                'The resolution note cannot exceed 1000 characters.'
            );
        }


        return $normalized;
    }


    /**
     * @return array<string,mixed>
     */
    private function requireEditablePeriod(
        int $payrollPeriodId
    ): array
    {
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
                'Payroll exceptions may be updated only while a payroll period is open or under review.'
            );
        }


        return $period;
    }


    /**
     * @return array<string,mixed>
     */
    private function requirePeriodException(
        int $payrollPeriodId,
        int $exceptionId
    ): array
    {
        $exception =
            $this->exceptions
                ->find(
                    $exceptionId
                );


        if ($exception === null) {

            throw new RuntimeException(
                'The payroll exception could not be found.'
            );
        }


        if (
            (int)(
                $exception['payroll_period_id']
                ??
                0
            )
            !==
            $payrollPeriodId
        ) {

            throw new RuntimeException(
                'The payroll exception does not belong to this payroll period.'
            );
        }


        return $exception;
    }


    /**
     * @return array<string,mixed>
     */
    private function requireAuthorizedUser(
        int $userId
    ): array
    {
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
                    $userId
            ]
        );


        $user =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );


        if (!is_array($user)) {

            throw new RuntimeException(
                'The acting user could not be found.'
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
                'The acting user is inactive.'
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


        if (
            !in_array(
                $role,
                [
                    'admin',
                    'supervisor'
                ],
                true
            )
        ) {

            throw new RuntimeException(
                'The acting user is not authorized to update payroll exceptions.'
            );
        }


        return $user;
    }


    /**
     * @param array<string,mixed> $exception
     */
    private function historyReason(
        array $exception,
        ?string $resolutionNote
    ): string
    {
        $description =
            trim(
                (string)(
                    $exception['description']
                    ??
                    'Payroll exception'
                )
            );


        $exceptionId =
            (int)(
                $exception['id']
                ??
                0
            );


        $reason =
            sprintf(
                'Exception #%d: %s',
                $exceptionId,
                $description
            );


        if (
            $resolutionNote !== null
            &&
            $resolutionNote !== ''
        ) {

            $reason .=
                ' — '
                .
                $resolutionNote;
        }


        return $reason;
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
