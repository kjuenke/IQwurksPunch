<?php
declare(strict_types=1);

use App\Services\ProcessRunnerInterface;
use App\Services\UpgradeExecutionApplyService;
use App\Services\UpgradeExecutionOperationsInterface;
use App\Services\UpgradeExecutionPreviewInterface;
use PHPUnit\Framework\TestCase;

final class UpgradeExecutionApplyServiceTest extends TestCase
{
    private string $projectRoot;


    protected function setUp(): void
    {
        parent::setUp();


        $this->projectRoot =
            sys_get_temp_dir()
            .
            '/iqwurks-upgrade-apply-'
            .
            bin2hex(
                random_bytes(
                    8
                )
            );


        self::assertTrue(
            mkdir(
                $this->projectRoot
                .
                '/storage/cache',
                0770,
                true
            )
        );
    }


    protected function tearDown(): void
    {
        $this->removeDirectory(
            $this->projectRoot
        );


        parent::tearDown();
    }


    public function testSuccessfulApplyRunsStagesInProtectedOrder(): void
    {
        $events =
            new \ArrayObject();


        $operations =
            $this->operations(
                $events
            );


        $runner =
            $this->runner(
                $events
            );


        $result =
            $this->service(
                $this->preview(),
                $operations,
                $runner
            )->apply(
                'UPGRADE 0.9.0-dev'
            );


        self::assertTrue(
            $result['successful']
        );


        self::assertSame(
            [
                'maintenance:activate',
                'backup:1',
                'process:/usr/bin/composer install',
                'process:/usr/bin/php migrate.php',
                'process:/usr/bin/php migrate.php status',
                'permissions',
                'process:/var/www/IQwurksPunch/iqwurks doctor',
                'process:/usr/bin/php vendor/bin/phpunit',
                'backup:2',
                'maintenance:deactivate'
            ],
            $events->getArrayCopy()
        );


        self::assertSame(
            'backup-1.sqlite',
            $result['pre_upgrade_backup']['filename']
        );


        self::assertSame(
            'backup-2.sqlite',
            $result['post_upgrade_backup']['filename']
        );


        self::assertTrue(
            $result['rollback_available']
        );


        self::assertTrue(
            $result['maintenance_activated_by_upgrade']
        );


        self::assertFalse(
            $result['maintenance_final_active']
        );


        self::assertNull(
            $result['failed_stage']
        );
    }


    public function testExistingMaintenanceModeRemainsActiveAfterSuccess(): void
    {
        $events =
            new \ArrayObject();


        $result =
            $this->service(
                $this->preview(),
                $this->operations(
                    $events,
                    true
                ),
                $this->runner(
                    $events
                )
            )->apply(
                'UPGRADE 0.9.0-dev'
            );


        self::assertTrue(
            $result['successful']
        );


        self::assertTrue(
            $result['maintenance_was_active']
        );


        self::assertFalse(
            $result['maintenance_activated_by_upgrade']
        );


        self::assertTrue(
            $result['maintenance_final_active']
        );


        self::assertNotContains(
            'maintenance:activate',
            $events->getArrayCopy()
        );


        self::assertNotContains(
            'maintenance:deactivate',
            $events->getArrayCopy()
        );
    }


    public function testIncorrectConfirmationBlocksBeforeMutation(): void
    {
        $events =
            new \ArrayObject();


        $runner =
            $this->runner(
                $events
            );


        $service =
            $this->service(
                $this->preview(),
                $this->operations(
                    $events
                ),
                $runner
            );


        try {

            $service->apply(
                'UPGRADE WRONG'
            );


            self::fail(
                'The incorrect confirmation should have been rejected.'
            );

        } catch (\InvalidArgumentException $exception) {

            self::assertStringContainsString(
                'must exactly match',
                $exception->getMessage()
            );
        }


        self::assertSame(
            [],
            $events->getArrayCopy()
        );


        self::assertSame(
            0,
            $runner->callCount
        );
    }


    public function testBlockedPreviewPreventsMutation(): void
    {
        $events =
            new \ArrayObject();


        $runner =
            $this->runner(
                $events
            );


        $service =
            $this->service(
                $this->preview(
                    true
                ),
                $this->operations(
                    $events
                ),
                $runner
            );


        try {

            $service->apply(
                'UPGRADE 0.9.0-dev'
            );


            self::fail(
                'A blocked upgrade preview should not be applied.'
            );

        } catch (\RuntimeException $exception) {

            self::assertStringContainsString(
                'blocked',
                $exception->getMessage()
            );
        }


        self::assertSame(
            [],
            $events->getArrayCopy()
        );


        self::assertSame(
            0,
            $runner->callCount
        );
    }


    public function testProcessFailureStopsExecutionAndLeavesMaintenanceActive(): void
    {
        $events =
            new \ArrayObject();


        $result =
            $this->service(
                $this->preview(),
                $this->operations(
                    $events
                ),
                $this->runner(
                    $events,
                    2
                )
            )->apply(
                'UPGRADE 0.9.0-dev'
            );


        self::assertFalse(
            $result['successful']
        );


        self::assertSame(
            'migrations',
            $result['failed_stage']
        );


        self::assertStringContainsString(
            'exited with code 2',
            $result['error_message']
        );


        self::assertSame(
            'backup-1.sqlite',
            $result['pre_upgrade_backup']['filename']
        );


        self::assertNull(
            $result['post_upgrade_backup']
        );


        self::assertTrue(
            $result['rollback_available']
        );


        self::assertTrue(
            $result['maintenance_final_active']
        );


        self::assertSame(
            [
                'maintenance:activate',
                'backup:1',
                'process:/usr/bin/composer install',
                'process:/usr/bin/php migrate.php'
            ],
            $events->getArrayCopy()
        );


        self::assertNotContains(
            'permissions',
            $events->getArrayCopy()
        );


        self::assertNotContains(
            'maintenance:deactivate',
            $events->getArrayCopy()
        );
    }


    private function service(
        UpgradeExecutionPreviewInterface $preview,
        UpgradeExecutionOperationsInterface $operations,
        ProcessRunnerInterface $runner
    ): UpgradeExecutionApplyService
    {
        return
            new UpgradeExecutionApplyService(
                $this->projectRoot,
                $preview,
                $operations,
                $runner,
                $this->projectRoot
                .
                '/storage/cache/upgrade-execution.lock'
            );
    }


    private function preview(
        bool $blocked = false
    ): UpgradeExecutionPreviewInterface
    {
        return
            new class(
                $blocked,
                $this->projectRoot
            ) implements UpgradeExecutionPreviewInterface
            {
                private bool $blocked;

                private string $projectRoot;


                public function __construct(
                    bool $blocked,
                    string $projectRoot
                )
                {
                    $this->blocked =
                        $blocked;


                    $this->projectRoot =
                        $projectRoot;
                }


                public function preview(): array
                {
                    return [
                        'application_version' =>
                            '0.9.0-dev',

                        'overall_status' =>
                            $this->blocked
                                ? 'FAIL'
                                : 'WARN',

                        'blocked' =>
                            $this->blocked,

                        'can_apply' =>
                            !$this->blocked,

                        'confirmation_phrase' =>
                            'UPGRADE 0.9.0-dev',

                        'stages' => [
                            $this->stage(
                                'dependencies',
                                'Composer locked install',
                                [
                                    $this->command(
                                        [
                                            '/usr/bin/composer',
                                            'install'
                                        ]
                                    )
                                ]
                            ),

                            $this->stage(
                                'migrations',
                                'Database migrations',
                                [
                                    $this->command(
                                        [
                                            '/usr/bin/php',
                                            'migrate.php'
                                        ]
                                    ),

                                    $this->command(
                                        [
                                            '/usr/bin/php',
                                            'migrate.php',
                                            'status'
                                        ]
                                    )
                                ]
                            ),

                            $this->stage(
                                'validation',
                                'Post-upgrade validation',
                                [
                                    $this->command(
                                        [
                                            '/var/www/IQwurksPunch/iqwurks',
                                            'doctor'
                                        ]
                                    ),

                                    $this->command(
                                        [
                                            '/usr/bin/php',
                                            'vendor/bin/phpunit'
                                        ]
                                    )
                                ]
                            )
                        ]
                    ];
                }


                /**
                 * @param array<int,array<string,mixed>> $commands
                 *
                 * @return array<string,mixed>
                 */
                private function stage(
                    string $id,
                    string $name,
                    array $commands
                ): array
                {
                    return [
                        'id' =>
                            $id,

                        'name' =>
                            $name,

                        'process_commands' =>
                            $commands
                    ];
                }


                /**
                 * @param array<int,string> $command
                 *
                 * @return array<string,mixed>
                 */
                private function command(
                    array $command
                ): array
                {
                    return [
                        'command' =>
                            $command,

                        'working_directory' =>
                            $this->projectRoot,

                        'environment' =>
                            [],

                        'timeout_seconds' =>
                            30.0,

                        'template' =>
                            false
                    ];
                }
            };
    }


    private function operations(
        \ArrayObject $events,
        bool $initiallyActive = false
    ): UpgradeExecutionOperationsInterface
    {
        return
            new class(
                $events,
                $initiallyActive
            ) implements UpgradeExecutionOperationsInterface
            {
                private \ArrayObject $events;

                private bool $active;

                private int $backupCount = 0;


                public function __construct(
                    \ArrayObject $events,
                    bool $active
                )
                {
                    $this->events =
                        $events;


                    $this->active =
                        $active;
                }


                public function maintenanceStatus(): array
                {
                    return [
                        'active' =>
                            $this->active
                    ];
                }


                public function activateMaintenance(
                    string $reason
                ): array
                {
                    $this->events->append(
                        'maintenance:activate'
                    );


                    $this->active =
                        true;


                    return [
                        'active' =>
                            true,

                        'reason' =>
                            $reason
                    ];
                }


                public function deactivateMaintenance(): array
                {
                    $this->events->append(
                        'maintenance:deactivate'
                    );


                    $this->active =
                        false;


                    return [
                        'active' =>
                            false,

                        'removed' =>
                            true
                    ];
                }


                public function createVerifiedBackup(): array
                {
                    $this->backupCount++;


                    $this->events->append(
                        'backup:'
                        .
                        $this->backupCount
                    );


                    return [
                        'filename' =>
                            'backup-'
                            .
                            $this->backupCount
                            .
                            '.sqlite',

                        'path' =>
                            '/tmp/backup-'
                            .
                            $this->backupCount
                            .
                            '.sqlite',

                        'verified' =>
                            true,

                        'integrity' =>
                            'ok'
                    ];
                }


                public function applyPermissions(): array
                {
                    $this->events->append(
                        'permissions'
                    );


                    return [
                        'successful' =>
                            true
                    ];
                }
            };
    }


    private function runner(
        \ArrayObject $events,
        ?int $failAt = null
    ): ProcessRunnerInterface
    {
        return
            new class(
                $events,
                $failAt
            ) implements ProcessRunnerInterface
            {
                private \ArrayObject $events;

                private ?int $failAt;

                public int $callCount = 0;


                public function __construct(
                    \ArrayObject $events,
                    ?int $failAt
                )
                {
                    $this->events =
                        $events;


                    $this->failAt =
                        $failAt;
                }


                public function run(
                    array $command,
                    ?string $workingDirectory = null,
                    array $environment = [],
                    ?float $timeoutSeconds = null
                ): array
                {
                    $this->callCount++;


                    $this->events->append(
                        'process:'
                        .
                        implode(
                            ' ',
                            $command
                        )
                    );


                    $failed =
                        $this->failAt !== null
                        &&
                        $this->callCount === $this->failAt;


                    return [
                        'command' =>
                            $command,

                        'display_command' =>
                            implode(
                                ' ',
                                $command
                            ),

                        'working_directory' =>
                            $workingDirectory,

                        'environment_overrides' =>
                            $environment,

                        'timeout_seconds' =>
                            $timeoutSeconds
                            ??
                            30.0,

                        'started_at' =>
                            '2026-07-31 14:30:00 PDT',

                        'completed_at' =>
                            '2026-07-31 14:30:01 PDT',

                        'exit_code' =>
                            $failed
                                ? 2
                                : 0,

                        'successful' =>
                            !$failed,

                        'timed_out' =>
                            false,

                        'stdout' =>
                            '',

                        'stderr' =>
                            $failed
                                ? 'simulated failure'
                                : '',

                        'duration_milliseconds' =>
                            1.0
                    ];
                }
            };
    }


    private function removeDirectory(
        string $path
    ): void
    {
        if (!is_dir($path)) {
            return;
        }


        $items =
            scandir(
                $path
            );


        if ($items === false) {
            return;
        }


        foreach ($items as $item) {

            if (
                $item === '.'
                ||
                $item === '..'
            ) {
                continue;
            }


            $itemPath =
                $path
                .
                DIRECTORY_SEPARATOR
                .
                $item;


            if (is_dir($itemPath)) {

                $this->removeDirectory(
                    $itemPath
                );


                continue;
            }


            unlink(
                $itemPath
            );
        }


        rmdir(
            $path
        );
    }
}
