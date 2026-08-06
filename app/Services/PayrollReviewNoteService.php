<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\PayrollPeriodHistoryRepository;
use App\Repositories\PayrollPeriodRepository;
use App\Repositories\PayrollReviewNoteRepository;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

final class PayrollReviewNoteService
{
    private const MAX_NOTE_LENGTH =
        1000;


    private PDO $db;

    private PayrollPeriodRepository $payrollPeriods;

    private PayrollReviewNoteRepository $reviewNotes;

    private PayrollPeriodHistoryRepository $history;


    public function __construct(
        PDO $db,
        PayrollPeriodRepository $payrollPeriods,
        PayrollReviewNoteRepository $reviewNotes,
        PayrollPeriodHistoryRepository $history
    )
    {
        $this->db =
            $db;


        $this->payrollPeriods =
            $payrollPeriods;


        $this->reviewNotes =
            $reviewNotes;


        $this->history =
            $history;
    }


    /**
     * @return array<string,mixed>
     */
    public function add(
        int $payrollPeriodId,
        int $actingUserId,
        string $note
    ): array
    {
        if ($payrollPeriodId < 1) {

            throw new InvalidArgumentException(
                'A valid payroll period ID is required.'
            );
        }


        if ($actingUserId < 1) {

            throw new InvalidArgumentException(
                'A valid acting user is required.'
            );
        }


        $normalizedNote =
            trim(
                $note
            );


        if ($normalizedNote === '') {

            throw new InvalidArgumentException(
                'A review note is required.'
            );
        }


        if (
            $this->textLength(
                $normalizedNote
            )
            >
            self::MAX_NOTE_LENGTH
        ) {

            throw new InvalidArgumentException(
                sprintf(
                    'A review note may not exceed %d characters.',
                    self::MAX_NOTE_LENGTH
                )
            );
        }


        return
            $this->transactional(
                function () use (
                    $payrollPeriodId,
                    $actingUserId,
                    $normalizedNote
                ): array {

                    $this->requireAuthorizedUser(
                        $actingUserId
                    );


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


                    PayrollPeriodLifecycle::assertActive(
                        $period
                    );


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
                            'Review notes may be added only while a payroll period is open or under review.'
                        );
                    }


                    $reviewNoteId =
                        $this->reviewNotes
                            ->create(
                                [
                                    'payroll_period_id' =>
                                        $payrollPeriodId,

                                    'note' =>
                                        $normalizedNote,

                                    'created_by_user_id' =>
                                        $actingUserId
                                ]
                            );


                    $this->history
                        ->create(
                            [
                                'payroll_period_id' =>
                                    $payrollPeriodId,

                                'action' =>
                                    'note_added',

                                'previous_status' =>
                                    $status,

                                'new_status' =>
                                    $status,

                                'reason' =>
                                    null,

                                'user_id' =>
                                    $actingUserId
                            ]
                        );


                    $reviewNote =
                        $this->reviewNotes
                            ->find(
                                $reviewNoteId
                            );


                    if ($reviewNote === null) {

                        throw new RuntimeException(
                            'The review note was created but could not be reloaded.'
                        );
                    }


                    return $reviewNote;
                }
            );
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

                WHERE id = :id

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
                'The acting user is not authorized to add payroll review notes.'
            );
        }


        return $user;
    }


    private function textLength(
        string $value
    ): int
    {
        if (
            function_exists(
                'mb_strlen'
            )
        ) {

            return mb_strlen(
                $value,
                'UTF-8'
            );
        }


        return strlen(
            $value
        );
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
