<?php
declare(strict_types=1);

use App\Services\UpgradeExecutionInterruptionService;
use App\Services\UpgradeExecutionJournalInterface;
use PHPUnit\Framework\TestCase;

final class UpgradeExecutionInterruptionServiceTest extends TestCase
{
    private string $projectRoot;


    protected function setUp(): void
    {
        parent::setUp();


        $this->projectRoot =
            sys_get_temp_dir()
            .
            '/iqwurks-upgrade-interruption-'
            .
            bin2hex(
                random_bytes(
                    8
                )
            );


        self::assertTrue(
            mkdir(
                $this->projectRoot,
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


    public function testNoJournalRecordIsClear(): void
    {
        $result =
            $this->service(
                null,
                false,
                false
            )->assess();


        self::assertSame(
            UpgradeExecutionInterruptionService::CLEAR,
            $result['status']
        );


        self::assertFalse(
            $result['requires_operator_action']
        );


        self::assertFalse(
            $result['changes_made']
        );


        self::assertNull(
            $result['execution_id']
        );
    }


    public function testCompletedRecordRequiresNoInterruptionRecovery(): void
    {
        $result =
            $this->service(
                $this->record(
                    'completed'
                ),
                false,
                false
            )->assess();


        self::assertSame(
            UpgradeExecutionInterruptionService::COMPLETE,
            $result['status']
        );


        self::assertFalse(
            $result['requires_operator_action']
        );


        self::assertSame(
            'execution-test-1',
            $result['execution_id']
        );
    }


    public function testRunningProcessWithHeldLockIsActive(): void
    {
        $result =
            $this->service(
                $this->record(
                    'running'
                ),
                true,
                true
            )->assess();


        self::assertSame(
            UpgradeExecutionInterruptionService::ACTIVE,
            $result['status']
        );


        self::assertFalse(
            $result['requires_operator_action']
        );


        self::assertTrue(
            $result['process_active']
        );


        self::assertTrue(
            $result['lock']['held']
        );
    }


    public function testMissingProcessAndReleasedLockIsInterrupted(): void
    {
        $result =
            $this->service(
                $this->record(
                    'running'
                ),
                false,
                false,
                true
            )->assess();


        self::assertSame(
            UpgradeExecutionInterruptionService::INTERRUPTED,
            $result['status']
        );


        self::assertTrue(
            $result['requires_operator_action']
        );


        self::assertFalse(
            $result['process_active']
        );


        self::assertFalse(
            $result['lock']['held']
        );


        self::assertTrue(
            $result['maintenance_active']
        );
    }


    public function testProcessAndLockDisagreementIsInconsistent(): void
    {
        $result =
            $this->service(
                $this->record(
                    'running'
                ),
                true,
                false
            )->assess();


        self::assertSame(
            UpgradeExecutionInterruptionService::INCONSISTENT,
            $result['status']
        );


        self::assertTrue(
            $result['requires_operator_action']
        );


        self::assertTrue(
            $result['process_active']
        );


        self::assertFalse(
            $result['lock']['held']
        );
    }


    private function service(
        ?array $record,
        bool $processActive,
        bool $lockHeld,
        bool $maintenanceActive = false
    ): UpgradeExecutionInterruptionService
    {
        return
            new UpgradeExecutionInterruptionService(
                $this->projectRoot,
                $this->journal(
                    $record
                ),
                $this->projectRoot
                .
                '/upgrade-execution.lock',
                static fn (): array => [
                    'active' =>
                        $maintenanceActive
                ],
                static fn (
                    int $processId
                ): bool =>
                    $processActive
                    &&
                    $processId === 4321,
                static fn (): array => [
                    'path' =>
                        '/test/upgrade-execution.lock',

                    'exists' =>
                        true,

                    'readable' =>
                        true,

                    'held' =>
                        $lockHeld,

                    'metadata' => [
                        'process_id' =>
                            4321
                    ],

                    'error' =>
                        null
                ]
            );
    }


    /**
     * @param array<string,mixed>|null $record
     */
    private function journal(
        ?array $record
    ): UpgradeExecutionJournalInterface
    {
        return
            new class(
                $record
            ) implements UpgradeExecutionJournalInterface
            {
                private ?array $record;


                public function __construct(
                    ?array $record
                )
                {
                    $this->record =
                        $record;
                }


                public function start(
                    string $version,
                    string $confirmation,
                    array $context = []
                ): array
                {
                    return [];
                }


                public function complete(
                    string $executionId,
                    array $result
                ): array
                {
                    return [];
                }


                public function read(
                    string $executionId
                ): array
                {
                    if ($this->record === null) {
                        throw new \RuntimeException(
                            'Record not found.'
                        );
                    }


                    return $this->record;
                }


                public function latest(): ?array
                {
                    return $this->record;
                }
            };
    }


    /**
     * @return array<string,mixed>
     */
    private function record(
        string $status
    ): array
    {
        return [
            'execution_id' =>
                'execution-test-1',

            'status' =>
                $status,

            'application_version' =>
                '0.9.0-dev',

            'confirmation' =>
                'UPGRADE 0.9.0-dev',

            'started_at' =>
                '2026-07-31T16:30:00-07:00',

            'completed_at' =>
                $status === 'running'
                    ? null
                    : '2026-07-31T16:31:00-07:00',

            'successful' =>
                $status === 'completed'
                    ? true
                    : null,

            'context' => [
                'process_id' =>
                    4321,

                'hostname' =>
                    'iqwurks'
            ],

            'result' =>
                $status === 'running'
                    ? null
                    : [
                        'successful' =>
                            $status === 'completed'
                    ]
        ];
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
