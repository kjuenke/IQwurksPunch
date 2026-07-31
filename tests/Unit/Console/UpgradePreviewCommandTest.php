<?php
declare(strict_types=1);

use App\Console\Commands\UpgradePreviewCommand;
use App\Services\UpgradeExecutionPreviewInterface;
use App\Services\UpgradeReadinessService;
use PHPUnit\Framework\TestCase;

final class UpgradePreviewCommandTest extends TestCase
{
    public function testCommandIdentity(): void
    {
        $command =
            new UpgradePreviewCommand(
                $this->previewService()
            );


        self::assertSame(
            'upgrade:preview',
            $command->name()
        );


        self::assertSame(
            'Preview the controlled upgrade execution without making changes.',
            $command->description()
        );
    }


    public function testReadyPreviewReturnsSuccessAndDisplaysExecutionDetails(): void
    {
        $command =
            new UpgradePreviewCommand(
                $this->previewService()
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
            'Upgrade Execution Preview',
            $output
        );


        self::assertStringContainsString(
            'Overall status: WARN',
            $output
        );


        self::assertStringContainsString(
            'Can apply: yes',
            $output
        );


        self::assertStringContainsString(
            'Required confirmation: UPGRADE 0.9.0-dev',
            $output
        );


        self::assertStringContainsString(
            'Process commands: 1',
            $output
        );


        self::assertStringContainsString(
            '1. [REQUIRED] Composer locked install',
            $output
        );


        self::assertStringContainsString(
            "Process: '/usr/bin/composer' 'install' '--no-interaction'",
            $output
        );


        self::assertStringContainsString(
            'Executes during preview: no',
            $output
        );


        self::assertStringContainsString(
            'Changes made: no',
            $output
        );


        self::assertStringContainsString(
            'This command did not execute any process or modify system state.',
            $output
        );
    }


    public function testBlockedPreviewReturnsFailure(): void
    {
        $command =
            new UpgradePreviewCommand(
                $this->previewService(
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
            'Overall status: FAIL',
            $output
        );


        self::assertStringContainsString(
            'Can apply: no',
            $output
        );


        self::assertStringContainsString(
            'Changes made: no',
            $output
        );
    }


    private function previewService(
        bool $blocked = false
    ): UpgradeExecutionPreviewInterface
    {
        return
            new class(
                $blocked
            ) implements UpgradeExecutionPreviewInterface
            {
                private bool $blocked;


                public function __construct(
                    bool $blocked
                )
                {
                    $this->blocked =
                        $blocked;
                }


                public function preview(): array
                {
                    return [
                        'generated_at' =>
                            '2026-07-31 13:47:00 PDT',

                        'application_version' =>
                            '0.9.0-dev',

                        'overall_status' =>
                            $this->blocked
                                ? UpgradeReadinessService::FAIL
                                : UpgradeReadinessService::WARN,

                        'blocked' =>
                            $this->blocked,

                        'can_apply' =>
                            !$this->blocked,

                        'confirmation_phrase' =>
                            'UPGRADE 0.9.0-dev',

                        'maintenance_active' =>
                            false,

                        'pending_migration_count' =>
                            0,

                        'process_runner_class' =>
                            'App\Services\ProcessRunnerService',

                        'process_command_count' =>
                            1,

                        'stage_count' =>
                            1,

                        'mutating_stage_count' =>
                            1,

                        'changes_made' =>
                            false,

                        'duration_milliseconds' =>
                            1.25,

                        'stages' => [
                            [
                                'number' =>
                                    1,

                                'id' =>
                                    'dependencies',

                                'name' =>
                                    'Composer locked install',

                                'status' =>
                                    'REQUIRED',

                                'mutating' =>
                                    true,

                                'execution_type' =>
                                    'process',

                                'action' =>
                                    'Install locked dependencies.',

                                'process_commands' => [
                                    [
                                        'display_command' =>
                                            "'/usr/bin/composer' 'install' '--no-interaction'",

                                        'working_directory' =>
                                            '/var/www/IQwurksPunch',

                                        'environment' => [
                                            'COMPOSER_ALLOW_SUPERUSER' =>
                                                '1'
                                        ],

                                        'timeout_seconds' =>
                                            900.0,

                                        'template' =>
                                            false,

                                        'will_execute_in_preview' =>
                                            false
                                    ]
                                ],

                                'details' => [
                                    'composer.lock remains authoritative.'
                                ],

                                'rollback' =>
                                    'Restore the matching application release.'
                            ]
                        ]
                    ];
                }
            };
    }
}
