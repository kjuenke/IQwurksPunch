<?php
declare(strict_types=1);

use App\Console\Commands\UpgradeApplyCommand;
use App\Services\UpgradeExecutionApplyInterface;
use PHPUnit\Framework\TestCase;

final class UpgradeApplyCommandTest extends TestCase
{
    public function testCommandIdentity(): void
    {
        $service =
            $this->service(
                $this->successfulResult()
            );


        $command =
            new UpgradeApplyCommand(
                $service
            );


        self::assertSame(
            'upgrade:apply',
            $command->name()
        );


        self::assertSame(
            'Apply the controlled upgrade after exact confirmation.',
            $command->description()
        );
    }


    public function testSuccessfulResultReturnsSuccessAndDisplaysSummary(): void
    {
        $service =
            $this->service(
                $this->successfulResult()
            );


        $command =
            new UpgradeApplyCommand(
                $service
            );


        ob_start();


        $exitCode =
            $command->execute(
                [
                    '--confirm=UPGRADE 0.9.0-dev'
                ]
            );


        $output =
            (string)ob_get_clean();


        self::assertSame(
            0,
            $exitCode
        );


        self::assertSame(
            1,
            $service->calls
        );


        self::assertSame(
            [
                'UPGRADE 0.9.0-dev'
            ],
            $service->confirmations
        );


        self::assertStringContainsString(
            'Result: SUCCESS',
            $output
        );


        self::assertStringContainsString(
            'Pre-upgrade backup: pre-upgrade.sqlite',
            $output
        );


        self::assertStringContainsString(
            'Post-upgrade backup: post-upgrade.sqlite',
            $output
        );


        self::assertStringContainsString(
            '[COMPLETED] Composer locked install',
            $output
        );


        self::assertStringContainsString(
            "Process: '/usr/bin/composer' 'install'",
            $output
        );


        self::assertStringContainsString(
            'The controlled upgrade completed successfully.',
            $output
        );
    }


    public function testFailedResultReturnsFailureAndDisplaysProtectionState(): void
    {
        $service =
            $this->service(
                $this->failedResult()
            );


        $command =
            new UpgradeApplyCommand(
                $service
            );


        ob_start();


        $exitCode =
            $command->execute(
                [
                    '--confirm=UPGRADE 0.9.0-dev'
                ]
            );


        $output =
            (string)ob_get_clean();


        self::assertSame(
            1,
            $exitCode
        );


        self::assertStringContainsString(
            'Result: FAILED',
            $output
        );


        self::assertStringContainsString(
            'Failed stage: migrations',
            $output
        );


        self::assertStringContainsString(
            'Maintenance active now: yes',
            $output
        );


        self::assertStringContainsString(
            'Rollback available: yes',
            $output
        );


        self::assertStringContainsString(
            'The application remains protected for investigation.',
            $output
        );
    }


    public function testMissingConfirmationDoesNotCallApplyService(): void
    {
        $service =
            $this->service(
                $this->successfulResult()
            );


        $command =
            new UpgradeApplyCommand(
                $service
            );


        $exitCode =
            $command->execute();


        self::assertSame(
            1,
            $exitCode
        );


        self::assertSame(
            0,
            $service->calls
        );


        self::assertSame(
            [],
            $service->confirmations
        );
    }


    /**
     * @param array<string,mixed> $result
     */
    private function service(
        array $result
    ): UpgradeExecutionApplyInterface
    {
        return
            new class(
                $result
            ) implements UpgradeExecutionApplyInterface
            {
                /**
                 * @var array<string,mixed>
                 */
                private array $result;

                public int $calls = 0;

                /**
                 * @var array<int,string>
                 */
                public array $confirmations = [];


                /**
                 * @param array<string,mixed> $result
                 */
                public function __construct(
                    array $result
                )
                {
                    $this->result =
                        $result;
                }


                public function apply(
                    string $confirmation
                ): array
                {
                    $this->calls++;


                    $this->confirmations[] =
                        $confirmation;


                    return
                        $this->result;
                }
            };
    }


    /**
     * @return array<string,mixed>
     */
    private function successfulResult(): array
    {
        return [
            'successful' =>
                true,

            'application_version' =>
                '0.9.0-dev',

            'started_at' =>
                '2026-07-31 15:05:00 PDT',

            'completed_at' =>
                '2026-07-31 15:06:00 PDT',

            'changes_made' =>
                true,

            'pre_upgrade_backup' => [
                'filename' =>
                    'pre-upgrade.sqlite'
            ],

            'post_upgrade_backup' => [
                'filename' =>
                    'post-upgrade.sqlite'
            ],

            'rollback_available' =>
                true,

            'maintenance_was_active' =>
                false,

            'maintenance_activated_by_upgrade' =>
                true,

            'maintenance_final_active' =>
                false,

            'duration_milliseconds' =>
                60000.0,

            'failed_stage' =>
                null,

            'error_message' =>
                null,

            'maintenance_protection_error' =>
                null,

            'stage_results' => [
                [
                    'id' =>
                        'dependencies',

                    'name' =>
                        'Composer locked install',

                    'status' =>
                        'COMPLETED',

                    'successful' =>
                        true,

                    'error_message' =>
                        null,

                    'process_results' => [
                        [
                            'display_command' =>
                                "'/usr/bin/composer' 'install'",

                            'exit_code' =>
                                0,

                            'timed_out' =>
                                false,

                            'stdout' =>
                                'Dependencies installed.',

                            'stderr' =>
                                ''
                        ]
                    ]
                ]
            ]
        ];
    }


    /**
     * @return array<string,mixed>
     */
    private function failedResult(): array
    {
        return [
            'successful' =>
                false,

            'application_version' =>
                '0.9.0-dev',

            'started_at' =>
                '2026-07-31 15:05:00 PDT',

            'completed_at' =>
                '2026-07-31 15:05:30 PDT',

            'changes_made' =>
                true,

            'pre_upgrade_backup' => [
                'filename' =>
                    'pre-upgrade.sqlite'
            ],

            'post_upgrade_backup' =>
                null,

            'rollback_available' =>
                true,

            'maintenance_was_active' =>
                false,

            'maintenance_activated_by_upgrade' =>
                true,

            'maintenance_final_active' =>
                true,

            'duration_milliseconds' =>
                30000.0,

            'failed_stage' =>
                'migrations',

            'error_message' =>
                'Upgrade process exited with code 2 during stage migrations.',

            'maintenance_protection_error' =>
                null,

            'stage_results' => [
                [
                    'id' =>
                        'migrations',

                    'name' =>
                        'Database migrations',

                    'status' =>
                        'FAILED',

                    'successful' =>
                        false,

                    'error_message' =>
                        'Upgrade process exited with code 2 during stage migrations.',

                    'process_results' => [
                        [
                            'display_command' =>
                                "'/usr/bin/php' 'migrate.php'",

                            'exit_code' =>
                                2,

                            'timed_out' =>
                                false,

                            'stdout' =>
                                '',

                            'stderr' =>
                                'Migration failed.'
                        ]
                    ]
                ]
            ]
        ];
    }
}
