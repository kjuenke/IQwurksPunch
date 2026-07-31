<?php
declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\CommandInterface;
use App\Core\AppInfo;
use App\Services\UpgradeReadinessService;
use Throwable;

final class UpgradeCheckCommand implements CommandInterface
{
    private UpgradeReadinessService $readiness;


    public function __construct(
        ?UpgradeReadinessService $readiness = null
    )
    {
        $this->readiness =
            $readiness
            ??
            new UpgradeReadinessService(
                dirname(
                    __DIR__,
                    3
                )
            );
    }


    public function name(): string
    {
        return 'upgrade:check';
    }


    public function description(): string
    {
        return 'Check whether the current IQwurksPunch installation is ready to upgrade.';
    }


    public function execute(
        array $arguments = []
    ): int
    {
        if ($arguments !== []) {

            fwrite(
                STDERR,
                'The upgrade:check command does not accept arguments.'
                .
                PHP_EOL
            );


            return 1;
        }


        $startedAt =
            microtime(
                true
            );


        try {

            $checks =
                $this->readiness->check();


            $passCount = 0;

            $warningCount = 0;

            $failureCount = 0;


            foreach ($checks as $check) {

                $status =
                    strtoupper(
                        trim(
                            (string)(
                                $check['status']
                                ??
                                ''
                            )
                        )
                    );


                if (
                    $status
                    ===
                    UpgradeReadinessService::PASS
                ) {
                    $passCount++;


                    continue;
                }


                if (
                    $status
                    ===
                    UpgradeReadinessService::WARN
                ) {
                    $warningCount++;


                    continue;
                }


                $failureCount++;
            }


            $overallStatus =
                $failureCount > 0
                    ? UpgradeReadinessService::FAIL
                    : (
                        $warningCount > 0
                            ? UpgradeReadinessService::WARN
                            : UpgradeReadinessService::PASS
                    );


            echo
                AppInfo::name()
                .
                ' '
                .
                AppInfo::version()
                .
                ' Upgrade Readiness'
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
                'Checked: '
                .
                date(
                    'Y-m-d H:i:s T'
                )
                .
                PHP_EOL
                .
                PHP_EOL;


            foreach ($checks as $check) {

                echo
                    '['
                    .
                    str_pad(
                        (string)(
                            $check['status']
                            ??
                            'FAIL'
                        ),
                        4
                    )
                    .
                    '] '
                    .
                    str_pad(
                        (string)(
                            $check['name']
                            ??
                            'Unnamed check'
                        ),
                        26
                    )
                    .
                    (string)(
                        $check['message']
                        ??
                        'No result message was recorded.'
                    )
                    .
                    PHP_EOL;


                $details =
                    $check['details']
                    ??
                    [];


                if (!is_array($details)) {
                    $details = [];
                }


                foreach ($details as $detail) {

                    echo
                        '       - '
                        .
                        (string)$detail
                        .
                        PHP_EOL;
                }
            }


            $durationMilliseconds =
                (
                    microtime(
                        true
                    )
                    -
                    $startedAt
                )
                *
                1000;


            echo
                PHP_EOL
                .
                str_repeat(
                    '-',
                    72
                )
                .
                PHP_EOL;


            echo
                'Overall status: '
                .
                $overallStatus
                .
                PHP_EOL;


            echo
                'Passed: '
                .
                $passCount
                .
                PHP_EOL;


            echo
                'Warnings: '
                .
                $warningCount
                .
                PHP_EOL;


            echo
                'Failures: '
                .
                $failureCount
                .
                PHP_EOL;


            echo
                'Total checks: '
                .
                count(
                    $checks
                )
                .
                PHP_EOL;


            echo
                'Duration: '
                .
                number_format(
                    $durationMilliseconds,
                    2
                )
                .
                ' ms'
                .
                PHP_EOL;


            return
                $failureCount > 0
                    ? 1
                    : 0;

        } catch (Throwable $exception) {

            fwrite(
                STDERR,
                'Upgrade readiness check failed unexpectedly: '
                .
                $exception->getMessage()
                .
                PHP_EOL
            );


            return 1;
        }
    }
}
