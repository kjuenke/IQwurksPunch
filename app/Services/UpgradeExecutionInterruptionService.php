<?php
declare(strict_types=1);

namespace App\Services;

use Closure;
use JsonException;
use RuntimeException;
use Throwable;

final class UpgradeExecutionInterruptionService
{
    public const CLEAR = 'CLEAR';

    public const COMPLETE = 'COMPLETE';

    public const ACTIVE = 'ACTIVE';

    public const INTERRUPTED = 'INTERRUPTED';

    public const INCONSISTENT = 'INCONSISTENT';

    public const UNKNOWN = 'UNKNOWN';


    private string $projectRoot;

    private UpgradeExecutionJournalInterface $journal;

    private string $lockFile;

    private Closure $maintenanceStatusProvider;

    private Closure $processChecker;

    private Closure $lockInspector;


    public function __construct(
        ?string $projectRoot = null,
        ?UpgradeExecutionJournalInterface $journal = null,
        ?string $lockFile = null,
        ?callable $maintenanceStatusProvider = null,
        ?callable $processChecker = null,
        ?callable $lockInspector = null
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
                'The interrupted-upgrade project root cannot be empty.'
            );
        }


        if (!is_dir($projectRoot)) {
            throw new RuntimeException(
                'The interrupted-upgrade project root does not exist: '
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
                'The interrupted-upgrade project root could not be resolved.'
            );
        }


        $this->projectRoot =
            $resolvedRoot;


        $this->journal =
            $journal
            ??
            new UpgradeExecutionJournalService(
                $this->projectRoot
                .
                '/storage/logs/upgrade-executions'
            );


        $this->lockFile =
            $lockFile
            ??
            $this->projectRoot
            .
            '/storage/cache/upgrade-execution.lock';


        if (
            trim(
                $this->lockFile
            )
            ===
            ''
        ) {
            throw new RuntimeException(
                'The upgrade-execution lock path cannot be empty.'
            );
        }


        $this->maintenanceStatusProvider =
            $maintenanceStatusProvider === null
                ? $this->defaultMaintenanceStatusProvider()
                : Closure::fromCallable(
                    $maintenanceStatusProvider
                );


        $this->processChecker =
            $processChecker === null
                ? $this->defaultProcessChecker()
                : Closure::fromCallable(
                    $processChecker
                );


        $this->lockInspector =
            $lockInspector === null
                ? fn (): array =>
                    $this->inspectLockFile()
                : Closure::fromCallable(
                    $lockInspector
                );
    }


    /**
     * @return array<string,mixed>
     */
    public function assess(): array
    {
        $startedAt =
            microtime(
                true
            );


        $latest =
            $this->journal->latest();


        $maintenance =
            ($this->maintenanceStatusProvider)();


        if (!is_array($maintenance)) {
            throw new RuntimeException(
                'The maintenance-status provider did not return an array.'
            );
        }


        $lock =
            ($this->lockInspector)();


        if (!is_array($lock)) {
            throw new RuntimeException(
                'The upgrade-lock inspector did not return an array.'
            );
        }


        if ($latest === null) {
            return
                $this->result(
                    self::CLEAR,
                    false,
                    'No controlled upgrade execution has been journaled.',
                    null,
                    $maintenance,
                    $lock,
                    null,
                    false,
                    [
                        'No recovery action is required.'
                    ],
                    $startedAt
                );
        }


        $recordStatus =
            strtolower(
                trim(
                    (string)(
                        $latest['status']
                        ??
                        'unknown'
                    )
                )
            );


        if (
            in_array(
                $recordStatus,
                [
                    'completed',
                    'failed'
                ],
                true
            )
        ) {
            return
                $this->result(
                    self::COMPLETE,
                    false,
                    'The latest controlled upgrade reached a final journal state.',
                    $latest,
                    $maintenance,
                    $lock,
                    null,
                    false,
                    [
                        'Review the recorded result with upgrade:status.',
                        $recordStatus === 'failed'
                            ? 'Resolve the recorded failure before attempting another upgrade.'
                            : 'No interruption recovery is required.'
                    ],
                    $startedAt
                );
        }


        if ($recordStatus !== 'running') {
            return
                $this->result(
                    self::UNKNOWN,
                    true,
                    'The latest upgrade journal record has an unrecognized status.',
                    $latest,
                    $maintenance,
                    $lock,
                    null,
                    false,
                    [
                        'Inspect the journal record before performing another upgrade.',
                        'Do not remove maintenance protection until the application state is verified.'
                    ],
                    $startedAt
                );
        }


        $context =
            $latest['context']
            ??
            [];


        if (!is_array($context)) {
            $context = [];
        }


        $processId =
            filter_var(
                $context['process_id']
                ??
                null,
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' =>
                            1
                    ]
                ]
            );


        if ($processId === false) {
            return
                $this->result(
                    self::UNKNOWN,
                    true,
                    'The running upgrade record does not contain a valid process ID.',
                    $latest,
                    $maintenance,
                    $lock,
                    null,
                    false,
                    [
                        'Treat the execution as potentially interrupted.',
                        'Verify the application, database, backups, and maintenance state manually.'
                    ],
                    $startedAt
                );
        }


        $processActive =
            (bool)(
                ($this->processChecker)(
                    $processId
                )
            );


        $lockHeld =
            (
                $lock['held']
                ??
                false
            )
            ===
            true;


        if (
            $processActive
            &&
            $lockHeld
        ) {
            return
                $this->result(
                    self::ACTIVE,
                    false,
                    'The latest controlled upgrade appears to still be running.',
                    $latest,
                    $maintenance,
                    $lock,
                    $processId,
                    true,
                    [
                        'Do not start another upgrade.',
                        'Allow the active process to finish or investigate it directly.'
                    ],
                    $startedAt
                );
        }


        if (
            !$processActive
            &&
            !$lockHeld
        ) {
            return
                $this->result(
                    self::INTERRUPTED,
                    true,
                    'The journal reports a running upgrade, but its process and lock are no longer active.',
                    $latest,
                    $maintenance,
                    $lock,
                    $processId,
                    false,
                    [
                        'Keep or enable maintenance mode until verification is complete.',
                        'Inspect the latest journal record and available backups.',
                        'Run database, migration, installation, scheduler, and doctor checks.',
                        'Do not begin another upgrade until the interrupted execution is resolved.'
                    ],
                    $startedAt
                );
        }


        return
            $this->result(
                self::INCONSISTENT,
                true,
                'The journaled process state and upgrade-lock state do not agree.',
                $latest,
                $maintenance,
                $lock,
                $processId,
                $processActive,
                [
                    'Do not start another upgrade.',
                    'Inspect the process, lock metadata, maintenance status, and latest journal record.',
                    'Preserve maintenance protection until the application state is verified.'
                ],
                $startedAt
            );
    }


    private function defaultMaintenanceStatusProvider(): Closure
    {
        $configurationPath =
            $this->projectRoot
            .
            '/config/maintenance.php';


        return
            static function () use (
                $configurationPath
            ): array
            {
                if (
                    !is_file(
                        $configurationPath
                    )
                    ||
                    !is_readable(
                        $configurationPath
                    )
                ) {
                    throw new RuntimeException(
                        'The maintenance configuration is missing or unreadable.'
                    );
                }


                $configuration =
                    require $configurationPath;


                if (!is_array($configuration)) {
                    throw new RuntimeException(
                        'The maintenance configuration did not return an array.'
                    );
                }


                $maintenanceFile =
                    trim(
                        (string)(
                            $configuration['file']
                            ??
                            ''
                        )
                    );


                if ($maintenanceFile === '') {
                    throw new RuntimeException(
                        'The maintenance-mode file is not configured.'
                    );
                }


                return
                    (
                        new MaintenanceModeService(
                            $maintenanceFile
                        )
                    )->status();
            };
    }


    private function defaultProcessChecker(): Closure
    {
        return
            static function (
                int $processId
            ): bool
            {
                if ($processId <= 0) {
                    return false;
                }


                if (
                    PHP_OS_FAMILY === 'Linux'
                    &&
                    is_dir(
                        '/proc/'
                        .
                        $processId
                    )
                ) {
                    return true;
                }


                if (
                    function_exists(
                        'posix_kill'
                    )
                ) {
                    return
                        @posix_kill(
                            $processId,
                            0
                        );
                }


                return false;
            };
    }


    /**
     * @return array<string,mixed>
     */
    private function inspectLockFile(): array
    {
        if (!is_file($this->lockFile)) {
            return [
                'path' =>
                    $this->lockFile,

                'exists' =>
                    false,

                'readable' =>
                    false,

                'held' =>
                    false,

                'metadata' =>
                    null,

                'error' =>
                    null
            ];
        }


        if (!is_readable($this->lockFile)) {
            return [
                'path' =>
                    $this->lockFile,

                'exists' =>
                    true,

                'readable' =>
                    false,

                'held' =>
                    null,

                'metadata' =>
                    null,

                'error' =>
                    'The upgrade lock file is not readable.'
            ];
        }


        $metadata =
            null;


        $contents =
            file_get_contents(
                $this->lockFile
            );


        if (
            $contents !== false
            &&
            trim(
                $contents
            )
            !==
            ''
        ) {
            try {

                $decoded =
                    json_decode(
                        $contents,
                        true,
                        512,
                        JSON_THROW_ON_ERROR
                    );


                if (is_array($decoded)) {
                    $metadata =
                        $decoded;
                }

            } catch (JsonException) {

                $metadata = [
                    'invalid_json' =>
                        true,

                    'raw_length' =>
                        strlen(
                            $contents
                        )
                ];
            }
        }


        $handle =
            @fopen(
                $this->lockFile,
                'r'
            );


        if ($handle === false) {
            return [
                'path' =>
                    $this->lockFile,

                'exists' =>
                    true,

                'readable' =>
                    true,

                'held' =>
                    null,

                'metadata' =>
                    $metadata,

                'error' =>
                    'The upgrade lock file could not be opened for inspection.'
            ];
        }


        try {

            $acquired =
                @flock(
                    $handle,
                    LOCK_EX
                    |
                    LOCK_NB
                );


            if ($acquired) {
                @flock(
                    $handle,
                    LOCK_UN
                );
            }


            return [
                'path' =>
                    $this->lockFile,

                'exists' =>
                    true,

                'readable' =>
                    true,

                'held' =>
                    !$acquired,

                'metadata' =>
                    $metadata,

                'error' =>
                    null
            ];

        } finally {

            fclose(
                $handle
            );
        }
    }


    /**
     * @param array<string,mixed>|null $record
     * @param array<string,mixed> $maintenance
     * @param array<string,mixed> $lock
     * @param array<int,string> $recommendations
     *
     * @return array<string,mixed>
     */
    private function result(
        string $status,
        bool $requiresOperatorAction,
        string $summary,
        ?array $record,
        array $maintenance,
        array $lock,
        ?int $processId,
        bool $processActive,
        array $recommendations,
        float $startedAt
    ): array
    {
        return [
            'status' =>
                $status,

            'requires_operator_action' =>
                $requiresOperatorAction,

            'summary' =>
                $summary,

            'execution_id' =>
                $record['execution_id']
                ??
                null,

            'journal_status' =>
                $record['status']
                ??
                null,

            'application_version' =>
                $record['application_version']
                ??
                null,

            'started_at' =>
                $record['started_at']
                ??
                null,

            'completed_at' =>
                $record['completed_at']
                ??
                null,

            'process_id' =>
                $processId,

            'process_active' =>
                $processActive,

            'maintenance_active' =>
                (
                    $maintenance['active']
                    ??
                    false
                )
                ===
                true,

            'maintenance' =>
                $maintenance,

            'lock' =>
                $lock,

            'record' =>
                $record,

            'recommendations' =>
                array_values(
                    $recommendations
                ),

            'changes_made' =>
                false,

            'assessed_at' =>
                date(
                    DATE_ATOM
                ),

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
}
