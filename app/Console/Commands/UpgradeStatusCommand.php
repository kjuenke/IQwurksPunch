<?php
declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\CommandInterface;
use App\Services\UpgradeExecutionJournalInterface;
use App\Services\UpgradeExecutionJournalService;
use InvalidArgumentException;
use Throwable;

final class UpgradeStatusCommand implements CommandInterface
{
    private UpgradeExecutionJournalInterface $journal;


    public function __construct(
        ?UpgradeExecutionJournalInterface $journal = null
    )
    {
        $projectRoot =
            dirname(
                __DIR__,
                3
            );


        $this->journal =
            $journal
            ??
            new UpgradeExecutionJournalService(
                $projectRoot
                .
                '/storage/logs/upgrade-executions'
            );
    }


    public function name(): string
    {
        return 'upgrade:status';
    }


    public function description(): string
    {
        return 'Display the latest or a specific controlled upgrade execution.';
    }


    public function execute(
        array $arguments = []
    ): int
    {
        try {

            $executionId =
                $this->parseArguments(
                    $arguments
                );


            $record =
                $executionId === null
                    ? $this->journal->latest()
                    : $this->journal->read(
                        $executionId
                    );


            if ($record === null) {

                echo
                    'No controlled upgrade execution has been journaled.'
                    .
                    PHP_EOL;


                return 0;
            }


            $this->displayRecord(
                $record
            );


            return 0;

        } catch (Throwable $exception) {

            fwrite(
                STDERR,
                'Upgrade status could not be displayed: '
                .
                $exception->getMessage()
                .
                PHP_EOL
            );


            return 1;
        }
    }


    /**
     * @param array<int,mixed> $arguments
     */
    private function parseArguments(
        array $arguments
    ): ?string
    {
        $executionId =
            null;


        foreach ($arguments as $argument) {

            $argument =
                trim(
                    (string)$argument
                );


            if (
                str_starts_with(
                    $argument,
                    '--id='
                )
            ) {
                if ($executionId !== null) {
                    throw new InvalidArgumentException(
                        'The --id argument may only be supplied once.'
                    );
                }


                $executionId =
                    trim(
                        substr(
                            $argument,
                            strlen(
                                '--id='
                            )
                        )
                    );


                if ($executionId === '') {
                    throw new InvalidArgumentException(
                        'The --id argument requires an execution ID.'
                    );
                }


                continue;
            }


            if (
                str_starts_with(
                    $argument,
                    '--'
                )
            ) {
                throw new InvalidArgumentException(
                    'Unknown upgrade status argument: '
                    .
                    $argument
                );
            }


            throw new InvalidArgumentException(
                'Positional arguments are not accepted by upgrade:status.'
            );
        }


        return $executionId;
    }


    /**
     * @param array<string,mixed> $record
     */
    private function displayRecord(
        array $record
    ): void
    {
        $result =
            $record['result']
            ??
            null;


        if (!is_array($result)) {
            $result = [];
        }


        echo
            'IQwurksPunch Upgrade Execution Status'
            .
            PHP_EOL;


        echo
            str_repeat(
                '=',
                72
            )
            .
            PHP_EOL;


        echo
            'Execution ID: '
            .
            (
                $record['execution_id']
                ??
                'unknown'
            )
            .
            PHP_EOL;


        echo
            'Status: '
            .
            strtoupper(
                (string)(
                    $record['status']
                    ??
                    'unknown'
                )
            )
            .
            PHP_EOL;


        echo
            'Application version: '
            .
            (
                $record['application_version']
                ??
                'unknown'
            )
            .
            PHP_EOL;


        echo
            'Started: '
            .
            (
                $record['started_at']
                ??
                'Not recorded'
            )
            .
            PHP_EOL;


        echo
            'Completed: '
            .
            (
                $record['completed_at']
                ??
                'Not completed'
            )
            .
            PHP_EOL;


        echo
            'Successful: '
            .
            $this->yesNoUnknown(
                $record['successful']
                ??
                null
            )
            .
            PHP_EOL;


        $context =
            $record['context']
            ??
            [];


        if (!is_array($context)) {
            $context = [];
        }


        echo
            'Process ID: '
            .
            (
                $context['process_id']
                ??
                'Not recorded'
            )
            .
            PHP_EOL;


        echo
            'Hostname: '
            .
            (
                $context['hostname']
                ??
                'Not recorded'
            )
            .
            PHP_EOL;


        echo
            'Preview status: '
            .
            (
                $context['preview_status']
                ??
                'Not recorded'
            )
            .
            PHP_EOL;


        echo
            'Pending migrations at start: '
            .
            (int)(
                $context['pending_migration_count']
                ??
                0
            )
            .
            PHP_EOL;


        echo
            PHP_EOL
            .
            'Execution result:'
            .
            PHP_EOL;


        echo
            str_repeat(
                '-',
                72
            )
            .
            PHP_EOL;


        if ($result === []) {

            echo
                'The upgrade execution is still running or no final result was recorded.'
                .
                PHP_EOL;


            echo
                str_repeat(
                    '-',
                    72
                )
                .
                PHP_EOL;


            return;
        }


        echo
            'Changes made: '
            .
            $this->yesNo(
                $result['changes_made']
                ??
                false
            )
            .
            PHP_EOL;


        echo
            'Failed stage: '
            .
            (
                $result['failed_stage']
                ??
                'None'
            )
            .
            PHP_EOL;


        echo
            'Pre-upgrade backup: '
            .
            $this->backupFilename(
                $result['pre_upgrade_backup']
                ??
                null
            )
            .
            PHP_EOL;


        echo
            'Post-upgrade backup: '
            .
            $this->backupFilename(
                $result['post_upgrade_backup']
                ??
                null
            )
            .
            PHP_EOL;


        echo
            'Rollback available: '
            .
            $this->yesNo(
                $result['rollback_available']
                ??
                false
            )
            .
            PHP_EOL;


        echo
            'Maintenance was already active: '
            .
            $this->yesNo(
                $result['maintenance_was_active']
                ??
                false
            )
            .
            PHP_EOL;


        echo
            'Maintenance activated by upgrade: '
            .
            $this->yesNo(
                $result['maintenance_activated_by_upgrade']
                ??
                false
            )
            .
            PHP_EOL;


        echo
            'Maintenance active after execution: '
            .
            $this->yesNo(
                $result['maintenance_final_active']
                ??
                false
            )
            .
            PHP_EOL;


        echo
            'Journal status: '
            .
            (
                $result['journal_status']
                ??
                $record['status']
                ??
                'unknown'
            )
            .
            PHP_EOL;


        $journalError =
            trim(
                (string)(
                    $result['journal_error']
                    ??
                    ''
                )
            );


        if ($journalError !== '') {

            echo
                'Journal error: '
                .
                $journalError
                .
                PHP_EOL;
        }


        $errorMessage =
            trim(
                (string)(
                    $result['error_message']
                    ??
                    ''
                )
            );


        if ($errorMessage !== '') {

            echo
                'Failure: '
                .
                $errorMessage
                .
                PHP_EOL;
        }


        $maintenanceProtectionError =
            trim(
                (string)(
                    $result['maintenance_protection_error']
                    ??
                    ''
                )
            );


        if ($maintenanceProtectionError !== '') {

            echo
                'Maintenance protection error: '
                .
                $maintenanceProtectionError
                .
                PHP_EOL;
        }


        echo
            'Duration: '
            .
            number_format(
                (float)(
                    $result['duration_milliseconds']
                    ??
                    0.0
                ),
                2
            )
            .
            ' ms'
            .
            PHP_EOL;


        $stageResults =
            $result['stage_results']
            ??
            [];


        if (!is_array($stageResults)) {
            $stageResults = [];
        }


        echo
            PHP_EOL
            .
            'Stages:'
            .
            PHP_EOL;


        foreach ($stageResults as $stageResult) {

            if (!is_array($stageResult)) {
                continue;
            }


            echo
                '  ['
                .
                (
                    $stageResult['status']
                    ??
                    'UNKNOWN'
                )
                .
                '] '
                .
                (
                    $stageResult['name']
                    ??
                    $stageResult['id']
                    ??
                    'Unnamed stage'
                )
                .
                PHP_EOL;
        }


        echo
            str_repeat(
                '-',
                72
            )
            .
            PHP_EOL;
    }


    private function yesNo(
        mixed $value
    ): string
    {
        return
            $value === true
                ? 'yes'
                : 'no';
    }


    private function yesNoUnknown(
        mixed $value
    ): string
    {
        if ($value === null) {
            return 'not completed';
        }


        return
            $value === true
                ? 'yes'
                : 'no';
    }


    private function backupFilename(
        mixed $backup
    ): string
    {
        if (!is_array($backup)) {
            return 'Not created';
        }


        $filename =
            trim(
                (string)(
                    $backup['filename']
                    ??
                    ''
                )
            );


        return
            $filename === ''
                ? 'Not recorded'
                : $filename;
    }
}
