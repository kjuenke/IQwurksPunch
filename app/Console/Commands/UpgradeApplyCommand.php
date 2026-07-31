<?php
declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\CommandInterface;
use App\Services\UpgradeExecutionApplyInterface;
use App\Services\UpgradeExecutionApplyService;
use InvalidArgumentException;
use Throwable;

final class UpgradeApplyCommand implements CommandInterface
{
    private UpgradeExecutionApplyInterface $upgrades;


    public function __construct(
        ?UpgradeExecutionApplyInterface $upgrades = null
    )
    {
        $this->upgrades =
            $upgrades
            ??
            new UpgradeExecutionApplyService(
                dirname(
                    __DIR__,
                    3
                )
            );
    }


    public function name(): string
    {
        return 'upgrade:apply';
    }


    public function description(): string
    {
        return 'Apply the controlled upgrade after exact confirmation.';
    }


    public function execute(
        array $arguments = []
    ): int
    {
        try {

            $confirmation =
                $this->parseArguments(
                    $arguments
                );


            echo
                'IQwurksPunch Controlled Upgrade'
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
                'The confirmation phrase was supplied.'
                .
                PHP_EOL;


            echo
                'The guarded engine is rechecking readiness before mutation...'
                .
                PHP_EOL
                .
                PHP_EOL;


            $result =
                $this->upgrades->apply(
                    $confirmation
                );


            $this->displayResult(
                $result
            );


            return
                (
                    $result['successful']
                    ??
                    false
                )
                    ? 0
                    : 1;

        } catch (Throwable $exception) {

            fwrite(
                STDERR,
                'Controlled upgrade was not completed: '
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
    ): string
    {
        $confirmation =
            null;


        foreach ($arguments as $argument) {

            $argument =
                trim(
                    (string)$argument
                );


            if (
                str_starts_with(
                    $argument,
                    '--confirm='
                )
            ) {
                if ($confirmation !== null) {
                    throw new InvalidArgumentException(
                        'The --confirm argument may only be supplied once.'
                    );
                }


                $confirmation =
                    substr(
                        $argument,
                        strlen(
                            '--confirm='
                        )
                    );


                continue;
            }


            if (
                str_starts_with(
                    $argument,
                    '--'
                )
            ) {
                throw new InvalidArgumentException(
                    'Unknown upgrade argument: '
                    .
                    $argument
                );
            }


            throw new InvalidArgumentException(
                'Positional arguments are not accepted by upgrade:apply.'
            );
        }


        if (
            $confirmation === null
            ||
            trim(
                $confirmation
            )
            ===
            ''
        ) {
            throw new InvalidArgumentException(
                'Exact confirmation is required. Run ./iqwurks upgrade:preview and then use --confirm="PHRASE".'
            );
        }


        return
            trim(
                $confirmation
            );
    }


    /**
     * @param array<string,mixed> $result
     */
    private function displayResult(
        array $result
    ): void
    {
        $successful =
            (
                $result['successful']
                ??
                false
            )
            ===
            true;


        echo
            'Result: '
            .
            (
                $successful
                    ? 'SUCCESS'
                    : 'FAILED'
            )
            .
            PHP_EOL;


        echo
            'Application version: '
            .
            (
                $result['application_version']
                ??
                'unknown'
            )
            .
            PHP_EOL;


        echo
            'Started: '
            .
            (
                $result['started_at']
                ??
                'Not recorded'
            )
            .
            PHP_EOL;


        echo
            'Completed: '
            .
            (
                $result['completed_at']
                ??
                'Not recorded'
            )
            .
            PHP_EOL;


        echo
            'Changes made: '
            .
            (
                (
                    $result['changes_made']
                    ??
                    false
                )
                    ? 'yes'
                    : 'no'
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
            (
                (
                    $result['rollback_available']
                    ??
                    false
                )
                    ? 'yes'
                    : 'no'
            )
            .
            PHP_EOL;


        echo
            'Maintenance was already active: '
            .
            (
                (
                    $result['maintenance_was_active']
                    ??
                    false
                )
                    ? 'yes'
                    : 'no'
            )
            .
            PHP_EOL;


        echo
            'Maintenance activated by upgrade: '
            .
            (
                (
                    $result['maintenance_activated_by_upgrade']
                    ??
                    false
                )
                    ? 'yes'
                    : 'no'
            )
            .
            PHP_EOL;


        echo
            'Maintenance active now: '
            .
            (
                (
                    $result['maintenance_final_active']
                    ??
                    false
                )
                    ? 'yes'
                    : 'no'
            )
            .
            PHP_EOL;


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
            'Stage results:'
            .
            PHP_EOL;


        echo
            str_repeat(
                '-',
                72
            )
            .
            PHP_EOL;


        foreach ($stageResults as $stageResult) {

            if (!is_array($stageResult)) {
                continue;
            }


            echo
                '['
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


            $processResults =
                $stageResult['process_results']
                ??
                [];


            if (!is_array($processResults)) {
                $processResults = [];
            }


            foreach ($processResults as $processResult) {

                if (!is_array($processResult)) {
                    continue;
                }


                echo
                    '  Process: '
                    .
                    (
                        $processResult['display_command']
                        ??
                        'Unavailable'
                    )
                    .
                    PHP_EOL;


                echo
                    '  Exit code: '
                    .
                    (int)(
                        $processResult['exit_code']
                        ??
                        -1
                    )
                    .
                    PHP_EOL;


                echo
                    '  Timed out: '
                    .
                    (
                        (
                            $processResult['timed_out']
                            ??
                            false
                        )
                            ? 'yes'
                            : 'no'
                    )
                    .
                    PHP_EOL;


                echo
                    '  Standard output: '
                    .
                    strlen(
                        (string)(
                            $processResult['stdout']
                            ??
                            ''
                        )
                    )
                    .
                    ' bytes'
                    .
                    PHP_EOL;


                echo
                    '  Standard error: '
                    .
                    strlen(
                        (string)(
                            $processResult['stderr']
                            ??
                            ''
                        )
                    )
                    .
                    ' bytes'
                    .
                    PHP_EOL;
            }


            $stageError =
                trim(
                    (string)(
                        $stageResult['error_message']
                        ??
                        ''
                    )
                );


            if ($stageError !== '') {

                echo
                    '  Error: '
                    .
                    $stageError
                    .
                    PHP_EOL;
            }
        }


        echo
            str_repeat(
                '-',
                72
            )
            .
            PHP_EOL;


        if (!$successful) {

            echo
                'Failed stage: '
                .
                (
                    $result['failed_stage']
                    ??
                    'unknown'
                )
                .
                PHP_EOL;


            echo
                'Failure: '
                .
                (
                    $result['error_message']
                    ??
                    'No failure message was recorded.'
                )
                .
                PHP_EOL;


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
                PHP_EOL
                .
                'The application remains protected for investigation.'
                .
                PHP_EOL;


            if (
                (
                    $result['rollback_available']
                    ??
                    false
                )
                ===
                true
            ) {
                echo
                    'Use the recorded pre-upgrade backup only with its matching application release.'
                    .
                    PHP_EOL;
            }


            return;
        }


        echo
            PHP_EOL
            .
            'The controlled upgrade completed successfully.'
            .
            PHP_EOL;
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
