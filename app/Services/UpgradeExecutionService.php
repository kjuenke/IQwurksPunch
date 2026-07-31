<?php
declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;

final class UpgradeExecutionService implements UpgradeExecutionPreviewInterface
{
    private string $projectRoot;

    private UpgradePlanService $plans;

    private ProcessRunnerInterface $processes;

    private string $phpBinary;

    private string $composerBinary;


    public function __construct(
        ?string $projectRoot = null,
        ?UpgradePlanService $plans = null,
        ?ProcessRunnerInterface $processes = null,
        ?string $phpBinary = null,
        ?string $composerBinary = null
    )
    {
        $projectRoot =
            $projectRoot
            ??
            dirname(
                __DIR__,
                2
            );


        $projectRoot =
            rtrim(
                trim(
                    $projectRoot
                ),
                DIRECTORY_SEPARATOR
            );


        if ($projectRoot === '') {
            throw new RuntimeException(
                'The upgrade-execution project root cannot be empty.'
            );
        }


        $this->projectRoot =
            $projectRoot;


        $this->plans =
            $plans
            ??
            new UpgradePlanService(
                $projectRoot
            );


        $this->processes =
            $processes
            ??
            new ProcessRunnerService(
                900.0
            );


        $this->phpBinary =
            $this->normalizeExecutable(
                $phpBinary
                ??
                PHP_BINARY,
                'PHP'
            );


        $this->composerBinary =
            $this->normalizeExecutable(
                $composerBinary
                ??
                '/usr/bin/composer',
                'Composer'
            );
    }


    /**
     * Build the future upgrade workflow without executing any step.
     *
     * @return array<string,mixed>
     */
    public function preview(): array
    {
        $startedAt =
            microtime(
                true
            );


        $plan =
            $this->plans->plan();


        $version =
            trim(
                (string)(
                    $plan['application_version']
                    ??
                    'unknown'
                )
            );


        if ($version === '') {
            $version =
                'unknown';
        }


        $blocked =
            (bool)(
                $plan['blocked']
                ??
                true
            );


        $maintenanceActive =
            (bool)(
                $plan['maintenance_active']
                ??
                false
            );


        $pendingMigrations =
            $plan['pending_migrations']
            ??
            [];


        if (!is_array($pendingMigrations)) {
            $pendingMigrations = [];
        }


        $pendingMigrations =
            array_values(
                array_map(
                    static fn (
                        mixed $migration
                    ): string =>
                        trim(
                            (string)$migration
                        ),
                    $pendingMigrations
                )
            );


        $confirmationPhrase =
            'UPGRADE '
            .
            $version;


        $stages =
            $this->stages(
                $version,
                $blocked,
                $maintenanceActive,
                $pendingMigrations
            );


        $processCommandCount = 0;

        $mutatingStageCount = 0;


        foreach ($stages as $stage) {

            if (
                (
                    $stage['mutating']
                    ??
                    false
                )
                ===
                true
            ) {
                $mutatingStageCount++;
            }


            $commands =
                $stage['process_commands']
                ??
                [];


            if (is_array($commands)) {
                $processCommandCount +=
                    count(
                        $commands
                    );
            }
        }


        return [
            'mode' =>
                'preview',

            'generated_at' =>
                date(
                    'Y-m-d H:i:s T'
                ),

            'application_version' =>
                $version,

            'overall_status' =>
                $blocked
                    ? UpgradeReadinessService::FAIL
                    : (
                        $plan['overall_status']
                        ??
                        UpgradeReadinessService::WARN
                    ),

            'blocked' =>
                $blocked,

            'can_apply' =>
                !$blocked,

            'confirmation_phrase' =>
                $confirmationPhrase,

            'maintenance_active' =>
                $maintenanceActive,

            'pending_migrations' =>
                $pendingMigrations,

            'pending_migration_count' =>
                count(
                    $pendingMigrations
                ),

            'pre_upgrade_backup_filename' =>
                null,

            'post_upgrade_backup_filename' =>
                null,

            'rollback_available' =>
                false,

            'plan' =>
                $plan,

            'stages' =>
                $stages,

            'stage_count' =>
                count(
                    $stages
                ),

            'mutating_stage_count' =>
                $mutatingStageCount,

            'process_command_count' =>
                $processCommandCount,

            'process_runner_class' =>
                $this->processes::class,

            'changes_made' =>
                false,

            'duration_milliseconds' =>
                round(
                    (
                        microtime(
                            true
                        )
                        -
                        $startedAt
                    )
                    *
                    1000,
                    2
                )
        ];
    }


    /**
     * @param array<int,string> $pendingMigrations
     *
     * @return array<int,array<string,mixed>>
     */
    private function stages(
        string $version,
        bool $blocked,
        bool $maintenanceActive,
        array $pendingMigrations
    ): array
    {
        $iqwurks =
            $this->path(
                'iqwurks'
            );


        $migrationRunner =
            $this->path(
                'migrate.php'
            );


        $phpunit =
            $this->path(
                'vendor/bin/phpunit'
            );


        return [
            $this->stage(
                1,
                'preflight',
                'Pre-upgrade validation',
                $blocked
                    ? 'BLOCKED'
                    : 'READY',
                false,
                'internal',
                'Re-evaluate upgrade readiness and stop before mutation if any hard failure exists.',
                [],
                [
                    $blocked
                        ? 'One or more readiness failures must be resolved.'
                        : 'No hard readiness blockers were detected.',

                    'Warnings may be accepted only when they represent the expected inactive-maintenance state.'
                ],
                'No rollback is required because this stage is read-only.'
            ),

            $this->stage(
                2,
                'maintenance',
                'Maintenance protection',
                $maintenanceActive
                    ? 'ACTIVE'
                    : 'REQUIRED',
                true,
                'internal',
                'Enable maintenance mode before dependencies, migrations, or permissions are changed.',
                [],
                [
                    $maintenanceActive
                        ? 'Maintenance mode is already active.'
                        : 'The execution service will activate maintenance mode before the first upgrade mutation.',

                    'Reason: IQwurksPunch upgrade to '
                    .
                    $version
                ],
                'Maintenance mode remains active if any later stage fails.'
            ),

            $this->stage(
                3,
                'pre_upgrade_backup',
                'Verified pre-upgrade backup',
                'REQUIRED',
                true,
                'internal',
                'Create and verify a dedicated rollback backup immediately before the upgrade.',
                [],
                [
                    'The exact generated backup filename will be stored in the execution result.',
                    'The upgrade must stop if backup creation or verification fails.'
                ],
                'The verified backup becomes the database rollback source.'
            ),

            $this->stage(
                4,
                'dependencies',
                'Composer locked install',
                'REQUIRED',
                true,
                'process',
                'Install exactly the dependency versions recorded in composer.lock.',
                [
                    $this->processCommand(
                        [
                            $this->composerBinary,
                            'install',
                            '--no-interaction',
                            '--prefer-dist',
                            '--optimize-autoloader'
                        ],
                        [
                            'COMPOSER_ALLOW_SUPERUSER' =>
                                '1'
                        ],
                        900.0
                    )
                ],
                [
                    'composer.lock remains authoritative.',
                    'composer update is never used by the controlled upgrade workflow.'
                ],
                'Restore the matching application release before restoring the pre-upgrade database.'
            ),

            $this->stage(
                5,
                'migrations',
                'Database migrations',
                $pendingMigrations === []
                    ? 'READY'
                    : 'PENDING',
                true,
                'process',
                'Apply pending migrations and then display the complete migration status.',
                [
                    $this->processCommand(
                        [
                            $this->phpBinary,
                            $migrationRunner
                        ],
                        [],
                        300.0
                    ),

                    $this->processCommand(
                        [
                            $this->phpBinary,
                            $migrationRunner,
                            'status'
                        ],
                        [],
                        120.0
                    )
                ],
                [
                    'Pending migration count: '
                    .
                    count(
                        $pendingMigrations
                    ),

                    $pendingMigrations === []
                        ? 'No unapplied migrations are currently waiting.'
                        : 'Pending: '
                            .
                            implode(
                                ', ',
                                $pendingMigrations
                            )
                ],
                'Use the matching pre-upgrade application release and verified database backup.'
            ),

            $this->stage(
                6,
                'permissions',
                'Runtime ownership and permissions',
                'REQUIRED',
                true,
                'internal',
                'Restore validated ownership, setgid runtime directories, and executable entry points.',
                [],
                [
                    'Runtime owner and group: www-data:www-data',
                    'Runtime directory mode: 2770',
                    'SQLite database file mode: 0660',
                    'Backup file mode: 0640',
                    'iqwurks executable mode: 0750'
                ],
                'Permission changes are reapplied from the restored application release.'
            ),

            $this->stage(
                7,
                'validation',
                'Post-upgrade validation',
                'REQUIRED',
                false,
                'process',
                'Run application, database, scheduler, and automated-test validation before returning to service.',
                [
                    $this->processCommand(
                        [
                            $iqwurks,
                            'install:check'
                        ],
                        [],
                        120.0
                    ),

                    $this->processCommand(
                        [
                            $iqwurks,
                            'database:check'
                        ],
                        [],
                        120.0
                    ),

                    $this->processCommand(
                        [
                            $iqwurks,
                            'scheduler:check'
                        ],
                        [],
                        120.0
                    ),

                    $this->processCommand(
                        [
                            $iqwurks,
                            'doctor'
                        ],
                        [],
                        300.0
                    ),

                    $this->processCommand(
                        [
                            $this->phpBinary,
                            $phpunit
                        ],
                        [],
                        900.0
                    )
                ],
                [
                    'Any nonzero process exit code blocks completion.',
                    'Maintenance mode remains active until every required validation succeeds.'
                ],
                'No rollback is automatic during preview; the failure result identifies the pre-upgrade backup.'
            ),

            $this->stage(
                8,
                'completion',
                'Post-upgrade backup and return to service',
                'REQUIRED',
                true,
                'internal',
                'Create a verified post-upgrade backup and disable maintenance mode only after validation succeeds.',
                [],
                [
                    'The post-upgrade backup filename will be recorded.',
                    'Maintenance mode is disabled last.',
                    'Final maintenance status must be inactive.'
                ],
                'If completion fails, maintenance mode remains active for investigation.'
            ),

            $this->stage(
                9,
                'rollback',
                'Rollback reference',
                'AVAILABLE_AFTER_BACKUP',
                true,
                'manual_process',
                'Restore the matching application release and explicitly confirmed pre-upgrade database backup.',
                [
                    $this->processCommand(
                        [
                            $iqwurks,
                            'backup:restore',
                            'PRE_UPGRADE_BACKUP_FILENAME',
                            '--apply',
                            '--confirm=PRE_UPGRADE_BACKUP_FILENAME'
                        ],
                        [],
                        900.0,
                        true
                    )
                ],
                [
                    'The placeholder must be replaced with the exact backup created during stage 3.',
                    'The confirmation value must exactly match the selected filename.',
                    'Maintenance mode remains active throughout rollback.'
                ],
                'This stage is the rollback workflow.'
            )
        ];
    }


    /**
     * @param array<int,array<string,mixed>> $processCommands
     * @param array<int,string> $details
     *
     * @return array<string,mixed>
     */
    private function stage(
        int $number,
        string $id,
        string $name,
        string $status,
        bool $mutating,
        string $executionType,
        string $action,
        array $processCommands,
        array $details,
        string $rollback
    ): array
    {
        return [
            'number' =>
                $number,

            'id' =>
                $id,

            'name' =>
                $name,

            'status' =>
                $status,

            'mutating' =>
                $mutating,

            'execution_type' =>
                $executionType,

            'action' =>
                $action,

            'process_commands' =>
                array_values(
                    $processCommands
                ),

            'details' =>
                array_values(
                    $details
                ),

            'rollback' =>
                $rollback
        ];
    }


    /**
     * @param array<int,string> $command
     * @param array<string,scalar|null> $environment
     *
     * @return array<string,mixed>
     */
    private function processCommand(
        array $command,
        array $environment,
        float $timeoutSeconds,
        bool $template = false
    ): array
    {
        if ($command === []) {
            throw new InvalidArgumentException(
                'An upgrade process command cannot be empty.'
            );
        }


        foreach ($command as $argument) {

            if (!is_string($argument)) {
                throw new InvalidArgumentException(
                    'Every upgrade process argument must be a string.'
                );
            }


            if (
                str_contains(
                    $argument,
                    "\0"
                )
            ) {
                throw new InvalidArgumentException(
                    'Upgrade process arguments cannot contain null bytes.'
                );
            }
        }


        if (
            !is_finite(
                $timeoutSeconds
            )
            ||
            $timeoutSeconds <= 0
        ) {
            throw new InvalidArgumentException(
                'The upgrade process timeout must be greater than zero.'
            );
        }


        return [
            'command' =>
                array_values(
                    $command
                ),

            'display_command' =>
                $this->displayCommand(
                    $command
                ),

            'working_directory' =>
                $this->projectRoot,

            'environment' =>
                $environment,

            'timeout_seconds' =>
                $timeoutSeconds,

            'template' =>
                $template,

            'will_execute_in_preview' =>
                false
        ];
    }


    /**
     * @param array<int,string> $command
     */
    private function displayCommand(
        array $command
    ): string
    {
        return
            implode(
                ' ',
                array_map(
                    static fn (
                        string $argument
                    ): string =>
                        escapeshellarg(
                            $argument
                        ),
                    $command
                )
            );
    }


    private function normalizeExecutable(
        string $executable,
        string $label
    ): string
    {
        $executable =
            trim(
                $executable
            );


        if ($executable === '') {
            throw new InvalidArgumentException(
                $label
                .
                ' executable cannot be empty.'
            );
        }


        if (
            str_contains(
                $executable,
                "\0"
            )
        ) {
            throw new InvalidArgumentException(
                $label
                .
                ' executable cannot contain null bytes.'
            );
        }


        return $executable;
    }


    private function path(
        string $relativePath
    ): string
    {
        return
            $this->projectRoot
            .
            DIRECTORY_SEPARATOR
            .
            ltrim(
                $relativePath,
                DIRECTORY_SEPARATOR
            );
    }
}
