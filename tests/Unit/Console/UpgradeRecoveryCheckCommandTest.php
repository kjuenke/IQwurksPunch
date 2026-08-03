<?php
declare(strict_types=1);

use App\Console\Commands\UpgradeRecoveryCheckCommand;
use App\Services\UpgradeExecutionInterruptionService;
use PHPUnit\Framework\TestCase;

final class UpgradeRecoveryCheckCommandTest extends TestCase
{
    public function testCommandIdentity(): void
    {
        $command =
            new UpgradeRecoveryCheckCommand(
                fn (): array =>
                    $this->assessment(
                        UpgradeExecutionInterruptionService::CLEAR,
                        false
                    )
            );


        self::assertSame(
            'upgrade:recovery-check',
            $command->name()
        );


        self::assertSame(
            'Assess whether the latest controlled upgrade was interrupted.',
            $command->description()
        );
    }


    public function testClearAssessmentReturnsSuccess(): void
    {
        $command =
            new UpgradeRecoveryCheckCommand(
                fn (): array =>
                    $this->assessment(
                        UpgradeExecutionInterruptionService::CLEAR,
                        false
                    )
            );


        ob_start();


        $exitCode =
            $command->execute();


        $output =
            (string)ob_get_clean();


        self::assertSame(
            0,
            $exitCode
        );


        self::assertStringContainsString(
            'Status: CLEAR',
            $output
        );


        self::assertStringContainsString(
            'Operator action required: no',
            $output
        );


        self::assertStringContainsString(
            'Changes made: no',
            $output
        );


        self::assertStringContainsString(
            'This command did not modify the application or upgrade state.',
            $output
        );
    }


    public function testInterruptedAssessmentReturnsFailure(): void
    {
        $command =
            new UpgradeRecoveryCheckCommand(
                fn (): array =>
                    $this->assessment(
                        UpgradeExecutionInterruptionService::INTERRUPTED,
                        true
                    )
            );


        ob_start();


        $exitCode =
            $command->execute();


        $output =
            (string)ob_get_clean();


        self::assertSame(
            1,
            $exitCode
        );


        self::assertStringContainsString(
            'Status: INTERRUPTED',
            $output
        );


        self::assertStringContainsString(
            'Operator action required: yes',
            $output
        );


        self::assertStringContainsString(
            'Process active: no',
            $output
        );


        self::assertStringContainsString(
            'Maintenance active: yes',
            $output
        );


        self::assertStringContainsString(
            'Do not begin another upgrade',
            $output
        );
    }


    public function testActiveAssessmentReturnsSuccess(): void
    {
        $assessment =
            $this->assessment(
                UpgradeExecutionInterruptionService::ACTIVE,
                false
            );


        $assessment['process_active'] =
            true;


        $assessment['lock']['held'] =
            true;


        $command =
            new UpgradeRecoveryCheckCommand(
                static fn (): array =>
                    $assessment
            );


        ob_start();


        $exitCode =
            $command->execute();


        $output =
            (string)ob_get_clean();


        self::assertSame(
            0,
            $exitCode
        );


        self::assertStringContainsString(
            'Status: ACTIVE',
            $output
        );


        self::assertStringContainsString(
            'Process active: yes',
            $output
        );


        self::assertStringContainsString(
            'Upgrade lock held: yes',
            $output
        );
    }


    /**
     * @return array<string,mixed>
     */
    private function assessment(
        string $status,
        bool $requiresOperatorAction
    ): array
    {
        return [
            'status' =>
                $status,

            'requires_operator_action' =>
                $requiresOperatorAction,

            'summary' =>
                $status === UpgradeExecutionInterruptionService::INTERRUPTED
                    ? 'The journal reports a running upgrade, but its process and lock are no longer active.'
                    : 'No interrupted upgrade requires recovery.',

            'execution_id' =>
                $status === UpgradeExecutionInterruptionService::CLEAR
                    ? null
                    : 'execution-test-1',

            'journal_status' =>
                $status === UpgradeExecutionInterruptionService::CLEAR
                    ? null
                    : 'running',

            'application_version' =>
                $status === UpgradeExecutionInterruptionService::CLEAR
                    ? null
                    : '0.9.0-dev',

            'started_at' =>
                $status === UpgradeExecutionInterruptionService::CLEAR
                    ? null
                    : '2026-08-03T08:55:00-07:00',

            'completed_at' =>
                null,

            'process_id' =>
                $status === UpgradeExecutionInterruptionService::CLEAR
                    ? null
                    : 4321,

            'process_active' =>
                false,

            'maintenance_active' =>
                $status === UpgradeExecutionInterruptionService::INTERRUPTED,

            'maintenance' => [
                'active' =>
                    $status === UpgradeExecutionInterruptionService::INTERRUPTED
            ],

            'lock' => [
                'path' =>
                    '/var/www/IQwurksPunch/storage/cache/upgrade-execution.lock',

                'exists' =>
                    $status !== UpgradeExecutionInterruptionService::CLEAR,

                'readable' =>
                    true,

                'held' =>
                    false,

                'metadata' =>
                    null,

                'error' =>
                    null
            ],

            'record' =>
                null,

            'recommendations' =>
                $status === UpgradeExecutionInterruptionService::INTERRUPTED
                    ? [
                        'Keep maintenance mode active until verification is complete.',
                        'Do not begin another upgrade until the interrupted execution is resolved.'
                    ]
                    : [
                        'No recovery action is required.'
                    ],

            'changes_made' =>
                false,

            'assessed_at' =>
                '2026-08-03T09:00:00-07:00',

            'duration_milliseconds' =>
                1.25
        ];
    }
}
