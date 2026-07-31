<?php
declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class UpgradeExecutionJournaledApplyService implements UpgradeExecutionApplyInterface
{
    private string $projectRoot;

    private UpgradeExecutionPreviewInterface $previews;

    private UpgradeExecutionApplyInterface $upgrades;

    private UpgradeExecutionJournalInterface $journal;


    public function __construct(
        ?string $projectRoot = null,
        ?UpgradeExecutionPreviewInterface $previews = null,
        ?UpgradeExecutionApplyInterface $upgrades = null,
        ?UpgradeExecutionJournalInterface $journal = null
    )
    {
        $projectRoot =
            $projectRoot
            ??
            dirname(
                __DIR__,
                2
            );


        $projectRoot =
            rtrim(
                trim(
                    $projectRoot
                ),
                DIRECTORY_SEPARATOR
            );


        if ($projectRoot === '') {
            throw new RuntimeException(
                'The journaled upgrade project root cannot be empty.'
            );
        }


        if (!is_dir($projectRoot)) {
            throw new RuntimeException(
                'The journaled upgrade project root does not exist: '
                .
                $projectRoot
            );
        }


        $resolvedRoot =
            realpath(
                $projectRoot
            );


        if ($resolvedRoot === false) {
            throw new RuntimeException(
                'The journaled upgrade project root could not be resolved.'
            );
        }


        $this->projectRoot =
            $resolvedRoot;


        $this->previews =
            $previews
            ??
            new UpgradeExecutionService(
                $this->projectRoot
            );


        $this->upgrades =
            $upgrades
            ??
            new UpgradeExecutionApplyService(
                $this->projectRoot,
                $this->previews
            );


        $this->journal =
            $journal
            ??
            new UpgradeExecutionJournalService(
                $this->projectRoot
                .
                '/storage/logs/upgrade-executions'
            );
    }


    /**
     * @return array<string,mixed>
     */
    public function apply(
        string $confirmation
    ): array
    {
        $preview =
            $this->previews->preview();


        $expectedConfirmation =
            trim(
                (string)(
                    $preview['confirmation_phrase']
                    ??
                    ''
                )
            );


        if ($expectedConfirmation === '') {
            throw new RuntimeException(
                'The upgrade preview did not provide a confirmation phrase.'
            );
        }


        $confirmation =
            trim(
                $confirmation
            );


        if ($confirmation !== $expectedConfirmation) {
            throw new InvalidArgumentException(
                'The upgrade confirmation must exactly match: '
                .
                $expectedConfirmation
            );
        }


        if (
            (
                $preview['blocked']
                ??
                true
            )
            ===
            true
            ||
            (
                $preview['can_apply']
                ??
                false
            )
            !==
            true
        ) {
            throw new RuntimeException(
                'The upgrade preview is blocked and cannot be applied.'
            );
        }


        $version =
            trim(
                (string)(
                    $preview['application_version']
                    ??
                    'unknown'
                )
            );


        if ($version === '') {
            $version =
                'unknown';
        }


        $journalRecord =
            $this->journal->start(
                $version,
                $expectedConfirmation,
                [
                    'process_id' =>
                        getmypid(),

                    'hostname' =>
                        gethostname()
                        ?:
                        null,

                    'project_root' =>
                        $this->projectRoot,

                    'preview_status' =>
                        $preview['overall_status']
                        ??
                        'UNKNOWN',

                    'maintenance_active' =>
                        (
                            $preview['maintenance_active']
                            ??
                            false
                        )
                        ===
                        true,

                    'pending_migration_count' =>
                        (int)(
                            $preview['pending_migration_count']
                            ??
                            0
                        )
                ]
            );


        $executionId =
            trim(
                (string)(
                    $journalRecord['execution_id']
                    ??
                    ''
                )
            );


        if ($executionId === '') {
            throw new RuntimeException(
                'The upgrade journal did not return an execution ID.'
            );
        }


        try {

            $result =
                $this->upgrades->apply(
                    $expectedConfirmation
                );

        } catch (Throwable $exception) {

            $result =
                $this->unexpectedFailureResult(
                    $version,
                    $expectedConfirmation,
                    $executionId,
                    $journalRecord,
                    $exception
                );
        }


        $result['execution_id'] =
            $executionId;


        $expectedJournalStatus =
            (
                $result['successful']
                ??
                false
            )
                ? 'completed'
                : 'failed';


        $result['journal_status'] =
            $expectedJournalStatus;


        $result['journal_error'] =
            null;


        try {

            $completedRecord =
                $this->journal->complete(
                    $executionId,
                    $result
                );


            $result['journal_status'] =
                $completedRecord['status']
                ??
                $expectedJournalStatus;


            $result['journal_completed_at'] =
                $completedRecord['completed_at']
                ??
                null;

        } catch (Throwable $journalException) {

            $result['journal_status'] =
                'write_failed';


            $result['journal_error'] =
                $journalException->getMessage();


            $result['journal_completed_at'] =
                null;
        }


        return $result;
    }


    /**
     * @param array<string,mixed> $journalRecord
     *
     * @return array<string,mixed>
     */
    private function unexpectedFailureResult(
        string $version,
        string $confirmation,
        string $executionId,
        array $journalRecord,
        Throwable $exception
    ): array
    {
        return [
            'mode' =>
                'apply',

            'successful' =>
                false,

            'application_version' =>
                $version,

            'confirmation' =>
                $confirmation,

            'execution_id' =>
                $executionId,

            'started_at' =>
                $journalRecord['started_at']
                ??
                date(
                    DATE_ATOM
                ),

            'completed_at' =>
                date(
                    DATE_ATOM
                ),

            'failed_stage' =>
                'execution_bootstrap',

            'error_class' =>
                $exception::class,

            'error_message' =>
                $exception->getMessage(),

            'pre_upgrade_backup' =>
                null,

            'post_upgrade_backup' =>
                null,

            'rollback_available' =>
                false,

            'maintenance_was_active' =>
                false,

            'maintenance_activated_by_upgrade' =>
                false,

            'maintenance_final_active' =>
                false,

            'maintenance_protection_error' =>
                null,

            'stage_results' => [
                [
                    'id' =>
                        'execution_bootstrap',

                    'name' =>
                        'Upgrade execution bootstrap',

                    'successful' =>
                        false,

                    'status' =>
                        'FAILED',

                    'details' =>
                        [],

                    'error_message' =>
                        $exception->getMessage()
                ]
            ],

            'changes_made' =>
                false,

            'duration_milliseconds' =>
                0.0
        ];
    }
}
