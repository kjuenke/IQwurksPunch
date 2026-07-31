<?php
declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\CommandInterface;
use App\Core\AppInfo;
use App\Services\UpgradePlanService;
use Throwable;

final class UpgradePlanCommand implements CommandInterface
{
    private UpgradePlanService $plans;


    public function __construct(
        ?UpgradePlanService $plans = null
    )
    {
        $this->plans =
            $plans
            ??
            new UpgradePlanService(
                dirname(
                    __DIR__,
                    3
                )
            );
    }


    public function name(): string
    {
        return 'upgrade:plan';
    }


    public function description(): string
    {
        return 'Display the proposed IQwurksPunch upgrade workflow without making changes.';
    }


    public function execute(
        array $arguments = []
    ): int
    {
        if ($arguments !== []) {

            fwrite(
                STDERR,
                'The upgrade:plan command does not accept arguments.'
                .
                PHP_EOL
            );


            return 1;
        }


        try {

            $plan =
                $this->plans->plan();


            echo
                AppInfo::name()
                .
                ' '
                .
                AppInfo::version()
                .
                ' Upgrade Plan'
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
                    $plan['generated_at']
                    ??
                    'Not recorded'
                )
                .
                PHP_EOL;


            echo
                'Application version: '
                .
                (
                    $plan['application_version']
                    ??
                    'unknown'
                )
                .
                PHP_EOL;


            echo
                'Overall status: '
                .
                (
                    $plan['overall_status']
                    ??
                    'FAIL'
                )
                .
                PHP_EOL;


            echo
                'Can begin: '
                .
                (
                    (
                        $plan['can_begin']
                        ??
                        false
                    )
                        ? 'yes'
                        : 'no'
                )
                .
                PHP_EOL;


            echo
                'Maintenance active: '
                .
                (
                    (
                        $plan['maintenance_active']
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
                    $plan['pending_migration_count']
                    ??
                    0
                )
                .
                PHP_EOL;


            $newestBackup =
                $plan['newest_backup']
                ??
                null;


            echo
                'Newest backup: '
                .
                (
                    is_array(
                        $newestBackup
                    )
                        ? (
                            $newestBackup['filename']
                            ??
                            'unknown'
                        )
                        : 'none'
                )
                .
                PHP_EOL;


            $planningErrors =
                $plan['planning_errors']
                ??
                [];


            if (
                is_array(
                    $planningErrors
                )
                &&
                $planningErrors !== []
            ) {
                echo
                    PHP_EOL
                    .
                    'Planning errors:'
                    .
                    PHP_EOL;


                foreach ($planningErrors as $error) {

                    echo
                        '  - '
                        .
                        (string)$error
                        .
                        PHP_EOL;
                }
            }


            echo
                PHP_EOL
                .
                'Proposed stages:'
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
                $plan['stages']
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
                    '   '
                    .
                    (
                        $stage['action']
                        ??
                        'No action was recorded.'
                    )
                    .
                    PHP_EOL;


                $commands =
                    $stage['commands']
                    ??
                    [];


                if (!is_array($commands)) {
                    $commands = [];
                }


                foreach ($commands as $command) {

                    echo
                        '   Command: '
                        .
                        (string)$command
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


                echo PHP_EOL;
            }


            echo
                str_repeat(
                    '-',
                    72
                )
                .
                PHP_EOL;


            echo
                'Readiness checks passed: '
                .
                (int)(
                    $plan['pass_count']
                    ??
                    0
                )
                .
                PHP_EOL;


            echo
                'Readiness warnings: '
                .
                (int)(
                    $plan['warning_count']
                    ??
                    0
                )
                .
                PHP_EOL;


            echo
                'Readiness failures: '
                .
                (int)(
                    $plan['failure_count']
                    ??
                    0
                )
                .
                PHP_EOL;


            echo
                'Plan stages: '
                .
                (int)(
                    $plan['stage_count']
                    ??
                    count(
                        $stages
                    )
                )
                .
                PHP_EOL;


            echo
                'Duration: '
                .
                number_format(
                    (float)(
                        $plan['duration_milliseconds']
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
                'This command made no changes.'
                .
                PHP_EOL;


            return
                (
                    $plan['blocked']
                    ??
                    true
                )
                    ? 1
                    : 0;

        } catch (Throwable $exception) {

            fwrite(
                STDERR,
                'Upgrade plan generation failed unexpectedly: '
                .
                $exception->getMessage()
                .
                PHP_EOL
            );


            return 1;
        }
    }
}
