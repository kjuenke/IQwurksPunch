<?php
declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\CommandInterface;
use App\Core\AppInfo;
use App\Services\UpgradeExecutionPreviewInterface;
use App\Services\UpgradeExecutionService;
use Throwable;

final class UpgradePreviewCommand implements CommandInterface
{
    private UpgradeExecutionPreviewInterface $upgrades;


    public function __construct(
        ?UpgradeExecutionPreviewInterface $upgrades = null
    )
    {
        $this->upgrades =
            $upgrades
            ??
            new UpgradeExecutionService(
                dirname(
                    __DIR__,
                    3
                )
            );
    }


    public function name(): string
    {
        return 'upgrade:preview';
    }


    public function description(): string
    {
        return 'Preview the controlled upgrade execution without making changes.';
    }


    public function execute(
        array $arguments = []
    ): int
    {
        if ($arguments !== []) {

            fwrite(
                STDERR,
                'The upgrade:preview command does not accept arguments.'
                .
                PHP_EOL
            );


            return 1;
        }


        try {

            $preview =
                $this->upgrades->preview();


            echo
                AppInfo::name()
                .
                ' '
                .
                AppInfo::version()
                .
                ' Upgrade Execution Preview'
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
                'Generated: '
                .
                (
                    $preview['generated_at']
                    ??
                    'Not recorded'
                )
                .
                PHP_EOL;


            echo
                'Application version: '
                .
                (
                    $preview['application_version']
                    ??
                    'unknown'
                )
                .
                PHP_EOL;


            echo
                'Overall status: '
                .
                (
                    $preview['overall_status']
                    ??
                    'FAIL'
                )
                .
                PHP_EOL;


            echo
                'Can apply: '
                .
                (
                    (
                        $preview['can_apply']
                        ??
                        false
                    )
                        ? 'yes'
                        : 'no'
                )
                .
                PHP_EOL;


            echo
                'Required confirmation: '
                .
                (
                    $preview['confirmation_phrase']
                    ??
                    'Unavailable'
                )
                .
                PHP_EOL;


            echo
                'Maintenance active: '
                .
                (
                    (
                        $preview['maintenance_active']
                        ??
                        false
                    )
                        ? 'yes'
                        : 'no'
                )
                .
                PHP_EOL;


            echo
                'Pending migrations: '
                .
                (int)(
                    $preview['pending_migration_count']
                    ??
                    0
                )
                .
                PHP_EOL;


            echo
                'Process runner: '
                .
                (
                    $preview['process_runner_class']
                    ??
                    'unknown'
                )
                .
                PHP_EOL;


            echo
                'Process commands: '
                .
                (int)(
                    $preview['process_command_count']
                    ??
                    0
                )
                .
                PHP_EOL;


            echo
                PHP_EOL
                .
                'Controlled execution stages:'
                .
                PHP_EOL;


            echo
                str_repeat(
                    '-',
                    72
                )
                .
                PHP_EOL;


            $stages =
                $preview['stages']
                ??
                [];


            if (!is_array($stages)) {
                $stages = [];
            }


            foreach ($stages as $stage) {

                echo
                    (int)(
                        $stage['number']
                        ??
                        0
                    )
                    .
                    '. ['
                    .
                    (
                        $stage['status']
                        ??
                        'UNKNOWN'
                    )
                    .
                    '] '
                    .
                    (
                        $stage['name']
                        ??
                        'Unnamed stage'
                    )
                    .
                    PHP_EOL;


                echo
                    '   Mutation: '
                    .
                    (
                        (
                            $stage['mutating']
                            ??
                            false
                        )
                            ? 'yes'
                            : 'no'
                    )
                    .
                    PHP_EOL;


                echo
                    '   Execution type: '
                    .
                    (
                        $stage['execution_type']
                        ??
                        'unknown'
                    )
                    .
                    PHP_EOL;


                echo
                    '   Action: '
                    .
                    (
                        $stage['action']
                        ??
                        'No action was recorded.'
                    )
                    .
                    PHP_EOL;


                $processCommands =
                    $stage['process_commands']
                    ??
                    [];


                if (!is_array($processCommands)) {
                    $processCommands = [];
                }


                foreach ($processCommands as $processCommand) {

                    echo
                        '   Process: '
                        .
                        (
                            $processCommand['display_command']
                            ??
                            'Unavailable'
                        )
                        .
                        PHP_EOL;


                    echo
                        '   Working directory: '
                        .
                        (
                            $processCommand['working_directory']
                            ??
                            'default'
                        )
                        .
                        PHP_EOL;


                    echo
                        '   Timeout: '
                        .
                        number_format(
                            (float)(
                                $processCommand['timeout_seconds']
                                ??
                                0.0
                            ),
                            2
                        )
                        .
                        ' seconds'
                        .
                        PHP_EOL;


                    $environment =
                        $processCommand['environment']
                        ??
                        [];


                    if (
                        is_array(
                            $environment
                        )
                        &&
                        $environment !== []
                    ) {
                        foreach (
                            $environment
                            as $name => $value
                        ) {
                            echo
                                '   Environment: '
                                .
                                (string)$name
                                .
                                '='
                                .
                                (
                                    $value === null
                                        ? '<unset>'
                                        : (string)$value
                                )
                                .
                                PHP_EOL;
                        }
                    }


                    if (
                        (
                            $processCommand['template']
                            ??
                            false
                        )
                        ===
                        true
                    ) {
                        echo
                            '   Template only: yes'
                            .
                            PHP_EOL;
                    }


                    echo
                        '   Executes during preview: '
                        .
                        (
                            (
                                $processCommand['will_execute_in_preview']
                                ??
                                false
                            )
                                ? 'yes'
                                : 'no'
                        )
                        .
                        PHP_EOL;
                }


                $details =
                    $stage['details']
                    ??
                    [];


                if (!is_array($details)) {
                    $details = [];
                }


                foreach ($details as $detail) {

                    echo
                        '   - '
                        .
                        (string)$detail
                        .
                        PHP_EOL;
                }


                echo
                    '   Rollback: '
                    .
                    (
                        $stage['rollback']
                        ??
                        'No rollback information was recorded.'
                    )
                    .
                    PHP_EOL
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


            echo
                'Stages: '
                .
                (int)(
                    $preview['stage_count']
                    ??
                    count(
                        $stages
                    )
                )
                .
                PHP_EOL;


            echo
                'Mutating stages described: '
                .
                (int)(
                    $preview['mutating_stage_count']
                    ??
                    0
                )
                .
                PHP_EOL;


            echo
                'Changes made: '
                .
                (
                    (
                        $preview['changes_made']
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
                        $preview['duration_milliseconds']
                        ??
                        0.0
                    ),
                    2
                )
                .
                ' ms'
                .
                PHP_EOL;


            echo
                'This command did not execute any process or modify system state.'
                .
                PHP_EOL;


            return
                (
                    $preview['blocked']
                    ??
                    true
                )
                    ? 1
                    : 0;

        } catch (Throwable $exception) {

            fwrite(
                STDERR,
                'Upgrade execution preview failed unexpectedly: '
                .
                $exception->getMessage()
                .
                PHP_EOL
            );


            return 1;
        }
    }
}
