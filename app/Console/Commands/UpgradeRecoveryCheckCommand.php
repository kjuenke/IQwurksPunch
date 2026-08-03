<?php
declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\CommandInterface;
use App\Services\UpgradeExecutionInterruptionService;
use Closure;
use InvalidArgumentException;
use Throwable;

final class UpgradeRecoveryCheckCommand implements CommandInterface
{
    private Closure $assessor;


    public function __construct(
        ?callable $assessor = null
    )
    {
        $this->assessor =
            $assessor === null
                ? static fn (): array =>
                    (
                        new UpgradeExecutionInterruptionService(
                            dirname(
                                __DIR__,
                                3
                            )
                        )
                    )->assess()
                : Closure::fromCallable(
                    $assessor
                );
    }


    public function name(): string
    {
        return 'upgrade:recovery-check';
    }


    public function description(): string
    {
        return 'Assess whether the latest controlled upgrade was interrupted.';
    }


    public function execute(
        array $arguments = []
    ): int
    {
        try {

            $this->validateArguments(
                $arguments
            );


            $assessment =
                ($this->assessor)();


            if (!is_array($assessment)) {
                throw new InvalidArgumentException(
                    'The interrupted-upgrade assessment did not return an array.'
                );
            }


            $this->displayAssessment(
                $assessment
            );


            return
                (
                    $assessment['requires_operator_action']
                    ??
                    true
                )
                    ? 1
                    : 0;

        } catch (Throwable $exception) {

            fwrite(
                STDERR,
                'Upgrade recovery assessment failed: '
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
    private function validateArguments(
        array $arguments
    ): void
    {
        if ($arguments === []) {
            return;
        }


        $argument =
            trim(
                (string)(
                    $arguments[0]
                    ??
                    ''
                )
            );


        if (
            str_starts_with(
                $argument,
                '--'
            )
        ) {
            throw new InvalidArgumentException(
                'Unknown recovery-check argument: '
                .
                $argument
            );
        }


        throw new InvalidArgumentException(
            'The upgrade:recovery-check command does not accept arguments.'
        );
    }


    /**
     * @param array<string,mixed> $assessment
     */
    private function displayAssessment(
        array $assessment
    ): void
    {
        echo
            'IQwurksPunch Upgrade Recovery Check'
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
            'Status: '
            .
            (
                $assessment['status']
                ??
                UpgradeExecutionInterruptionService::UNKNOWN
            )
            .
            PHP_EOL;


        echo
            'Operator action required: '
            .
            $this->yesNo(
                $assessment['requires_operator_action']
                ??
                true
            )
            .
            PHP_EOL;


        echo
            'Summary: '
            .
            (
                $assessment['summary']
                ??
                'No assessment summary was recorded.'
            )
            .
            PHP_EOL;


        echo
            'Execution ID: '
            .
            $this->displayValue(
                $assessment['execution_id']
                ??
                null
            )
            .
            PHP_EOL;


        echo
            'Journal status: '
            .
            $this->displayValue(
                $assessment['journal_status']
                ??
                null
            )
            .
            PHP_EOL;


        echo
            'Application version: '
            .
            $this->displayValue(
                $assessment['application_version']
                ??
                null
            )
            .
            PHP_EOL;


        echo
            'Execution started: '
            .
            $this->displayValue(
                $assessment['started_at']
                ??
                null
            )
            .
            PHP_EOL;


        echo
            'Execution completed: '
            .
            $this->displayValue(
                $assessment['completed_at']
                ??
                null
            )
            .
            PHP_EOL;


        echo
            'Process ID: '
            .
            $this->displayValue(
                $assessment['process_id']
                ??
                null
            )
            .
            PHP_EOL;


        echo
            'Process active: '
            .
            $this->yesNo(
                $assessment['process_active']
                ??
                false
            )
            .
            PHP_EOL;


        echo
            'Maintenance active: '
            .
            $this->yesNo(
                $assessment['maintenance_active']
                ??
                false
            )
            .
            PHP_EOL;


        $lock =
            $assessment['lock']
            ??
            [];


        if (!is_array($lock)) {
            $lock = [];
        }


        echo
            'Upgrade lock exists: '
            .
            $this->yesNo(
                $lock['exists']
                ??
                false
            )
            .
            PHP_EOL;


        echo
            'Upgrade lock held: '
            .
            $this->yesNoUnknown(
                $lock['held']
                ??
                null
            )
            .
            PHP_EOL;


        echo
            'Upgrade lock path: '
            .
            $this->displayValue(
                $lock['path']
                ??
                null
            )
            .
            PHP_EOL;


        $lockError =
            trim(
                (string)(
                    $lock['error']
                    ??
                    ''
                )
            );


        if ($lockError !== '') {

            echo
                'Upgrade lock error: '
                .
                $lockError
                .
                PHP_EOL;
        }


        $recommendations =
            $assessment['recommendations']
            ??
            [];


        if (!is_array($recommendations)) {
            $recommendations = [];
        }


        echo
            PHP_EOL
            .
            'Recommended actions:'
            .
            PHP_EOL;


        echo
            str_repeat(
                '-',
                72
            )
            .
            PHP_EOL;


        if ($recommendations === []) {

            echo
                '  - No recommendations were recorded.'
                .
                PHP_EOL;

        } else {

            foreach ($recommendations as $recommendation) {

                echo
                    '  - '
                    .
                    (string)$recommendation
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


        echo
            'Assessed: '
            .
            (
                $assessment['assessed_at']
                ??
                'Not recorded'
            )
            .
            PHP_EOL;


        echo
            'Duration: '
            .
            number_format(
                (float)(
                    $assessment['duration_milliseconds']
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
            'Changes made: '
            .
            $this->yesNo(
                $assessment['changes_made']
                ??
                false
            )
            .
            PHP_EOL;


        echo
            'This command did not modify the application or upgrade state.'
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
            return 'unknown';
        }


        return
            $this->yesNo(
                $value
            );
    }


    private function displayValue(
        mixed $value
    ): string
    {
        if ($value === null) {
            return 'Not recorded';
        }


        $value =
            trim(
                (string)$value
            );


        return
            $value === ''
                ? 'Not recorded'
                : $value;
    }
}
