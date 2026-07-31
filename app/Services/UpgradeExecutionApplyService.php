<?php
declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class UpgradeExecutionApplyService implements UpgradeExecutionApplyInterface
{
    private string $projectRoot;

    private UpgradeExecutionPreviewInterface $previews;

    private UpgradeExecutionOperationsInterface $operations;

    private ProcessRunnerInterface $processes;

    private string $lockFile;


    public function __construct(
        ?string $projectRoot = null,
        ?UpgradeExecutionPreviewInterface $previews = null,
        ?UpgradeExecutionOperationsInterface $operations = null,
        ?ProcessRunnerInterface $processes = null,
        ?string $lockFile = null
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
                'The upgrade-apply project root cannot be empty.'
            );
        }


        if (!is_dir($projectRoot)) {
            throw new RuntimeException(
                'The upgrade-apply project root does not exist: '
                .
                $projectRoot
            );
        }


        $resolvedRoot =
            realpath(
                $projectRoot
            );


        if ($resolvedRoot === false) {
            throw new RuntimeException(
                'The upgrade-apply project root could not be resolved.'
            );
        }


        $this->projectRoot =
            $resolvedRoot;


        $this->previews =
            $previews
            ??
            new UpgradeExecutionService(
                $this->projectRoot
            );


        $this->operations =
            $operations
            ??
            new UpgradeExecutionOperationsService(
                $this->projectRoot
            );


        $this->processes =
            $processes
            ??
            new ProcessRunnerService(
                900.0
            );


        $lockFile =
            $lockFile
            ??
            $this->projectRoot
            .
            '/storage/cache/upgrade-execution.lock';


        $lockFile =
            trim(
                $lockFile
            );


        if ($lockFile === '') {
            throw new RuntimeException(
                'The upgrade-execution lock file cannot be empty.'
            );
        }


        if (
            str_contains(
                $lockFile,
                "\0"
            )
        ) {
            throw new RuntimeException(
                'The upgrade-execution lock file cannot contain null bytes.'
            );
        }


        $this->lockFile =
            $lockFile;
    }


    /**
     * @return array<string,mixed>
     */
    public function apply(
        string $confirmation
    ): array
    {
        $startedTimestamp =
            microtime(
                true
            );


        $startedAt =
            date(
                'Y-m-d H:i:s T'
            );


        $preview =
            $this->previews->preview();


        $expectedConfirmation =
            trim(
                (string)(
                    $preview['confirmation_phrase']
                    ??
                    ''
                )
            );


        if ($expectedConfirmation === '') {
            throw new RuntimeException(
                'The upgrade preview did not provide a confirmation phrase.'
            );
        }


        $confirmation =
            trim(
                $confirmation
            );


        if ($confirmation !== $expectedConfirmation) {
            throw new InvalidArgumentException(
                'The upgrade confirmation must exactly match: '
                .
                $expectedConfirmation
            );
        }


        if (
            (
                $preview['blocked']
                ??
                true
            )
            ===
            true
            ||
            (
                $preview['can_apply']
                ??
                false
            )
            !==
            true
        ) {
            throw new RuntimeException(
                'The upgrade preview is blocked and cannot be applied.'
            );
        }


        $version =
            trim(
                (string)(
                    $preview['application_version']
                    ??
                    'unknown'
                )
            );


        if ($version === '') {
            $version =
                'unknown';
        }


        $lockHandle =
            $this->acquireLock(
                $expectedConfirmation,
                $version
            );


        $stageResults = [];

        $preUpgradeBackup = null;

        $postUpgradeBackup = null;

        $maintenanceWasActive = false;

        $maintenanceActivatedByUpgrade = false;

        $changesMade = false;

        $currentStage = 'preflight';


        try {

            $maintenanceStatus =
                $this->operations
                    ->maintenanceStatus();


            $maintenanceWasActive =
                (
                    $maintenanceStatus['active']
                    ??
                    false
                )
                ===
                true;


            $stageResults[] =
                $this->successfulStage(
                    'preflight',
                    'Pre-upgrade validation',
                    [
                        'preview_status' =>
                            $preview['overall_status']
                            ??
                            'UNKNOWN',

                        'confirmation' =>
                            $expectedConfirmation
                    ]
                );


            $currentStage =
                'maintenance';


            if (!$maintenanceWasActive) {

                $changesMade =
                    true;


                $maintenanceStatus =
                    $this->operations
                        ->activateMaintenance(
                            'IQwurksPunch controlled upgrade to '
                            .
                            $version
                        );


                if (
                    (
                        $maintenanceStatus['active']
                        ??
                        false
                    )
                    !==
                    true
                ) {
                    throw new RuntimeException(
                        'Maintenance mode did not become active.'
                    );
                }


                $maintenanceActivatedByUpgrade =
                    true;
            }


            $stageResults[] =
                $this->successfulStage(
                    'maintenance',
                    'Maintenance protection',
                    [
                        'was_already_active' =>
                            $maintenanceWasActive,

                        'activated_by_upgrade' =>
                            $maintenanceActivatedByUpgrade,

                        'status' =>
                            $maintenanceStatus
                    ]
                );


            $currentStage =
                'pre_upgrade_backup';


            $changesMade =
                true;


            $preUpgradeBackup =
                $this->operations
                    ->createVerifiedBackup();


            if (
                (
                    $preUpgradeBackup['verified']
                    ??
                    false
                )
                !==
                true
            ) {
                throw new RuntimeException(
                    'The pre-upgrade backup was not verified.'
                );
            }


            $stageResults[] =
                $this->successfulStage(
                    'pre_upgrade_backup',
                    'Verified pre-upgrade backup',
                    [
                        'backup' =>
                            $preUpgradeBackup
                    ]
                );


            $currentStage =
                'dependencies';


            $changesMade =
                true;


            $dependencyResult =
                $this->executeProcessStage(
                    $this->previewStage(
                        $preview,
                        'dependencies'
                    )
                );


            $stageResults[] =
                $dependencyResult;


            if (!$dependencyResult['successful']) {

                return
                    $this->failureResult(
                        $startedTimestamp,
                        $startedAt,
                        $version,
                        $expectedConfirmation,
                        $currentStage,
                        new RuntimeException(
                            (string)$dependencyResult['error_message']
                        ),
                        $stageResults,
                        $preUpgradeBackup,
                        $postUpgradeBackup,
                        $maintenanceWasActive,
                        $maintenanceActivatedByUpgrade,
                        $changesMade
                    );
            }


            $currentStage =
                'migrations';


            $changesMade =
                true;


            $migrationResult =
                $this->executeProcessStage(
                    $this->previewStage(
                        $preview,
                        'migrations'
                    )
                );


            $stageResults[] =
                $migrationResult;


            if (!$migrationResult['successful']) {

                return
                    $this->failureResult(
                        $startedTimestamp,
                        $startedAt,
                        $version,
                        $expectedConfirmation,
                        $currentStage,
                        new RuntimeException(
                            (string)$migrationResult['error_message']
                        ),
                        $stageResults,
                        $preUpgradeBackup,
                        $postUpgradeBackup,
                        $maintenanceWasActive,
                        $maintenanceActivatedByUpgrade,
                        $changesMade
                    );
            }


            $currentStage =
                'permissions';


            $changesMade =
                true;


            $permissionResult =
                $this->operations
                    ->applyPermissions();


            if (
                (
                    $permissionResult['successful']
                    ??
                    false
                )
                !==
                true
            ) {
                throw new RuntimeException(
                    'Runtime permission restoration did not complete successfully.'
                );
            }


            $stageResults[] =
                $this->successfulStage(
                    'permissions',
                    'Runtime ownership and permissions',
                    [
                        'result' =>
                            $permissionResult
                    ]
                );


            $currentStage =
                'validation';


            $validationResult =
                $this->executeProcessStage(
                    $this->previewStage(
                        $preview,
                        'validation'
                    )
                );


            $stageResults[] =
                $validationResult;


            if (!$validationResult['successful']) {

                return
                    $this->failureResult(
                        $startedTimestamp,
                        $startedAt,
                        $version,
                        $expectedConfirmation,
                        $currentStage,
                        new RuntimeException(
                            (string)$validationResult['error_message']
                        ),
                        $stageResults,
                        $preUpgradeBackup,
                        $postUpgradeBackup,
                        $maintenanceWasActive,
                        $maintenanceActivatedByUpgrade,
                        $changesMade
                    );
            }


            $currentStage =
                'completion';


            $changesMade =
                true;


            $postUpgradeBackup =
                $this->operations
                    ->createVerifiedBackup();


            if (
                (
                    $postUpgradeBackup['verified']
                    ??
                    false
                )
                !==
                true
            ) {
                throw new RuntimeException(
                    'The post-upgrade backup was not verified.'
                );
            }


            $deactivationResult =
                null;


            if ($maintenanceActivatedByUpgrade) {

                $deactivationResult =
                    $this->operations
                        ->deactivateMaintenance();


                if (
                    (
                        $deactivationResult['active']
                        ??
                        true
                    )
                    !==
                    false
                ) {
                    throw new RuntimeException(
                        'Maintenance mode did not deactivate after successful validation.'
                    );
                }
            }


            $finalMaintenanceStatus =
                $this->operations
                    ->maintenanceStatus();


            $stageResults[] =
                $this->successfulStage(
                    'completion',
                    'Post-upgrade backup and return to service',
                    [
                        'backup' =>
                            $postUpgradeBackup,

                        'maintenance_deactivation' =>
                            $deactivationResult,

                        'maintenance_final_status' =>
                            $finalMaintenanceStatus
                    ]
                );


            return [
                'mode' =>
                    'apply',

                'successful' =>
                    true,

                'application_version' =>
                    $version,

                'confirmation' =>
                    $expectedConfirmation,

                'started_at' =>
                    $startedAt,

                'completed_at' =>
                    date(
                        'Y-m-d H:i:s T'
                    ),

                'failed_stage' =>
                    null,

                'error_class' =>
                    null,

                'error_message' =>
                    null,

                'pre_upgrade_backup' =>
                    $preUpgradeBackup,

                'post_upgrade_backup' =>
                    $postUpgradeBackup,

                'rollback_available' =>
                    true,

                'maintenance_was_active' =>
                    $maintenanceWasActive,

                'maintenance_activated_by_upgrade' =>
                    $maintenanceActivatedByUpgrade,

                'maintenance_final_active' =>
                    (
                        $finalMaintenanceStatus['active']
                        ??
                        false
                    )
                    ===
                    true,

                'maintenance_protection_error' =>
                    null,

                'stage_results' =>
                    $stageResults,

                'changes_made' =>
                    $changesMade,

                'lock_file' =>
                    $this->lockFile,

                'duration_milliseconds' =>
                    $this->durationMilliseconds(
                        $startedTimestamp
                    )
            ];

        } catch (Throwable $exception) {

            if (
                !$this->containsFailedStage(
                    $stageResults,
                    $currentStage
                )
            ) {
                $stageResults[] =
                    $this->failedStage(
                        $currentStage,
                        $exception
                    );
            }


            return
                $this->failureResult(
                    $startedTimestamp,
                    $startedAt,
                    $version,
                    $expectedConfirmation,
                    $currentStage,
                    $exception,
                    $stageResults,
                    $preUpgradeBackup,
                    $postUpgradeBackup,
                    $maintenanceWasActive,
                    $maintenanceActivatedByUpgrade,
                    $changesMade
                );

        } finally {

            $this->releaseLock(
                $lockHandle
            );
        }
    }


    /**
     * @param array<string,mixed> $stage
     *
     * @return array<string,mixed>
     */
    private function executeProcessStage(
        array $stage
    ): array
    {
        $stageId =
            trim(
                (string)(
                    $stage['id']
                    ??
                    ''
                )
            );


        $stageName =
            trim(
                (string)(
                    $stage['name']
                    ??
                    $stageId
                )
            );


        $commands =
            $stage['process_commands']
            ??
            [];


        if (!is_array($commands)) {
            $commands = [];
        }


        $processResults = [];

        $executedCommandCount = 0;


        foreach ($commands as $commandDefinition) {

            if (!is_array($commandDefinition)) {
                throw new RuntimeException(
                    'An upgrade process definition is invalid.'
                );
            }


            if (
                (
                    $commandDefinition['template']
                    ??
                    false
                )
                ===
                true
            ) {
                continue;
            }


            $command =
                $commandDefinition['command']
                ??
                [];


            if (!is_array($command)) {
                throw new RuntimeException(
                    'An upgrade process command is invalid.'
                );
            }


            $command =
                array_values(
                    array_map(
                        static fn (
                            mixed $argument
                        ): string =>
                            (string)$argument,
                        $command
                    )
                );


            if ($command === []) {
                throw new RuntimeException(
                    'An upgrade process command is empty.'
                );
            }


            $workingDirectory =
                $commandDefinition['working_directory']
                ??
                $this->projectRoot;


            if ($workingDirectory !== null) {
                $workingDirectory =
                    (string)$workingDirectory;
            }


            $environment =
                $commandDefinition['environment']
                ??
                [];


            if (!is_array($environment)) {
                throw new RuntimeException(
                    'An upgrade process environment definition is invalid.'
                );
            }


            $timeoutSeconds =
                (float)(
                    $commandDefinition['timeout_seconds']
                    ??
                    300.0
                );


            $result =
                $this->processes->run(
                    $command,
                    $workingDirectory,
                    $environment,
                    $timeoutSeconds
                );


            $processResults[] =
                $result;


            $executedCommandCount++;


            if (
                (
                    $result['successful']
                    ??
                    false
                )
                !==
                true
            ) {
                $exitCode =
                    (int)(
                        $result['exit_code']
                        ??
                        -1
                    );


                $timedOut =
                    (
                        $result['timed_out']
                        ??
                        false
                    )
                    ===
                    true;


                return [
                    'id' =>
                        $stageId,

                    'name' =>
                        $stageName,

                    'successful' =>
                        false,

                    'status' =>
                        'FAILED',

                    'executed_command_count' =>
                        $executedCommandCount,

                    'process_results' =>
                        $processResults,

                    'error_message' =>
                        $timedOut
                            ? 'Upgrade process timed out during stage '
                                .
                                $stageId
                                .
                                '.'
                            : 'Upgrade process exited with code '
                                .
                                $exitCode
                                .
                                ' during stage '
                                .
                                $stageId
                                .
                                '.'
                ];
            }
        }


        if ($executedCommandCount === 0) {
            return [
                'id' =>
                    $stageId,

                'name' =>
                    $stageName,

                'successful' =>
                    false,

                'status' =>
                    'FAILED',

                'executed_command_count' =>
                    0,

                'process_results' =>
                    [],

                'error_message' =>
                    'No executable process commands were defined for stage '
                    .
                    $stageId
                    .
                    '.'
            ];
        }


        return [
            'id' =>
                $stageId,

            'name' =>
                $stageName,

            'successful' =>
                true,

            'status' =>
                'COMPLETED',

            'executed_command_count' =>
                $executedCommandCount,

            'process_results' =>
                $processResults,

            'error_message' =>
                null
        ];
    }


    /**
     * @param array<string,mixed> $preview
     *
     * @return array<string,mixed>
     */
    private function previewStage(
        array $preview,
        string $stageId
    ): array
    {
        $stages =
            $preview['stages']
            ??
            [];


        if (!is_array($stages)) {
            $stages = [];
        }


        foreach ($stages as $stage) {

            if (
                is_array(
                    $stage
                )
                &&
                (
                    $stage['id']
                    ??
                    null
                )
                ===
                $stageId
            ) {
                return $stage;
            }
        }


        throw new RuntimeException(
            'The upgrade preview is missing required stage: '
            .
            $stageId
        );
    }


    /**
     * @param array<string,mixed> $details
     *
     * @return array<string,mixed>
     */
    private function successfulStage(
        string $id,
        string $name,
        array $details = []
    ): array
    {
        return [
            'id' =>
                $id,

            'name' =>
                $name,

            'successful' =>
                true,

            'status' =>
                'COMPLETED',

            'details' =>
                $details,

            'error_message' =>
                null
        ];
    }


    /**
     * @return array<string,mixed>
     */
    private function failedStage(
        string $id,
        Throwable $exception
    ): array
    {
        return [
            'id' =>
                $id,

            'name' =>
                $id,

            'successful' =>
                false,

            'status' =>
                'FAILED',

            'details' =>
                [],

            'error_message' =>
                $exception->getMessage()
        ];
    }


    /**
     * @param array<int,array<string,mixed>> $stageResults
     */
    private function containsFailedStage(
        array $stageResults,
        string $stageId
    ): bool
    {
        foreach ($stageResults as $stageResult) {

            if (
                (
                    $stageResult['id']
                    ??
                    null
                )
                ===
                $stageId
                &&
                (
                    $stageResult['successful']
                    ??
                    true
                )
                ===
                false
            ) {
                return true;
            }
        }


        return false;
    }


    /**
     * @param array<int,array<string,mixed>> $stageResults
     * @param array<string,mixed>|null $preUpgradeBackup
     * @param array<string,mixed>|null $postUpgradeBackup
     *
     * @return array<string,mixed>
     */
    private function failureResult(
        float $startedTimestamp,
        string $startedAt,
        string $version,
        string $confirmation,
        string $failedStage,
        Throwable $exception,
        array $stageResults,
        ?array $preUpgradeBackup,
        ?array $postUpgradeBackup,
        bool $maintenanceWasActive,
        bool $maintenanceActivatedByUpgrade,
        bool $changesMade
    ): array
    {
        $maintenanceProtectionError =
            null;


        try {

            $maintenanceStatus =
                $this->operations
                    ->maintenanceStatus();


            if (
                (
                    $maintenanceStatus['active']
                    ??
                    false
                )
                !==
                true
            ) {
                $changesMade =
                    true;


                $maintenanceStatus =
                    $this->operations
                        ->activateMaintenance(
                            'IQwurksPunch upgrade failure protection for '
                            .
                            $version
                        );


                $maintenanceActivatedByUpgrade =
                    true;
            }

        } catch (Throwable $maintenanceException) {

            $maintenanceStatus = [
                'active' =>
                    false
            ];


            $maintenanceProtectionError =
                $maintenanceException->getMessage();
        }


        try {

            $maintenanceStatus =
                $this->operations
                    ->maintenanceStatus();

        } catch (Throwable $statusException) {

            if ($maintenanceProtectionError === null) {
                $maintenanceProtectionError =
                    $statusException->getMessage();
            }


            $maintenanceStatus = [
                'active' =>
                    false
            ];
        }


        return [
            'mode' =>
                'apply',

            'successful' =>
                false,

            'application_version' =>
                $version,

            'confirmation' =>
                $confirmation,

            'started_at' =>
                $startedAt,

            'completed_at' =>
                date(
                    'Y-m-d H:i:s T'
                ),

            'failed_stage' =>
                $failedStage,

            'error_class' =>
                $exception::class,

            'error_message' =>
                $exception->getMessage(),

            'pre_upgrade_backup' =>
                $preUpgradeBackup,

            'post_upgrade_backup' =>
                $postUpgradeBackup,

            'rollback_available' =>
                $preUpgradeBackup !== null,

            'maintenance_was_active' =>
                $maintenanceWasActive,

            'maintenance_activated_by_upgrade' =>
                $maintenanceActivatedByUpgrade,

            'maintenance_final_active' =>
                (
                    $maintenanceStatus['active']
                    ??
                    false
                )
                ===
                true,

            'maintenance_protection_error' =>
                $maintenanceProtectionError,

            'stage_results' =>
                $stageResults,

            'changes_made' =>
                $changesMade,

            'lock_file' =>
                $this->lockFile,

            'duration_milliseconds' =>
                $this->durationMilliseconds(
                    $startedTimestamp
                )
        ];
    }


    /**
     * @return resource
     */
    private function acquireLock(
        string $confirmation,
        string $version
    )
    {
        $directory =
            dirname(
                $this->lockFile
            );


        if (!is_dir($directory)) {
            throw new RuntimeException(
                'The upgrade lock directory does not exist: '
                .
                $directory
            );
        }


        if (!is_writable($directory)) {
            throw new RuntimeException(
                'The upgrade lock directory is not writable: '
                .
                $directory
            );
        }


        $handle =
            fopen(
                $this->lockFile,
                'c+'
            );


        if ($handle === false) {
            throw new RuntimeException(
                'The upgrade-execution lock file could not be opened.'
            );
        }


        if (
            !flock(
                $handle,
                LOCK_EX
                |
                LOCK_NB
            )
        ) {
            fclose(
                $handle
            );


            throw new RuntimeException(
                'Another controlled upgrade execution is already running.'
            );
        }


        $metadata =
            json_encode(
                [
                    'process_id' =>
                        getmypid(),

                    'version' =>
                        $version,

                    'confirmation' =>
                        $confirmation,

                    'started_at' =>
                        date(
                            DATE_ATOM
                        )
                ],
                JSON_THROW_ON_ERROR
                |
                JSON_PRETTY_PRINT
            );


        ftruncate(
            $handle,
            0
        );


        rewind(
            $handle
        );


        if (
            fwrite(
                $handle,
                $metadata
                .
                PHP_EOL
            )
            ===
            false
        ) {
            flock(
                $handle,
                LOCK_UN
            );


            fclose(
                $handle
            );


            throw new RuntimeException(
                'Upgrade lock metadata could not be written.'
            );
        }


        fflush(
            $handle
        );


        return $handle;
    }


    /**
     * @param resource $handle
     */
    private function releaseLock(
        $handle
    ): void
    {
        @ftruncate(
            $handle,
            0
        );


        @fflush(
            $handle
        );


        @flock(
            $handle,
            LOCK_UN
        );


        @fclose(
            $handle
        );
    }


    private function durationMilliseconds(
        float $startedTimestamp
    ): float
    {
        return
            round(
                (
                    microtime(
                        true
                    )
                    -
                    $startedTimestamp
                )
                *
                1000,
                2
            );
    }
}
