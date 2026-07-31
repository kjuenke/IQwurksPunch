<?php
declare(strict_types=1);

use App\Services\UpgradeExecutionApplyInterface;
use App\Services\UpgradeExecutionJournaledApplyService;
use App\Services\UpgradeExecutionJournalInterface;
use App\Services\UpgradeExecutionPreviewInterface;
use PHPUnit\Framework\TestCase;

final class UpgradeExecutionJournaledApplyServiceTest extends TestCase
{
    private string $projectRoot;


    protected function setUp(): void
    {
        parent::setUp();


        $this->projectRoot =
            sys_get_temp_dir()
            .
            '/iqwurks-journaled-upgrade-'
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


    public function testSuccessfulUpgradeIsJournaledAsCompleted(): void
    {
        $upgrades =
            $this->applyEngine(
                [
                    'successful' =>
                        true,

                    'application_version' =>
                        '0.9.0-dev',

                    'changes_made' =>
                        true
                ]
            );


        $journal =
            $this->journal();


        $result =
            $this->service(
                $this->preview(),
                $upgrades,
                $journal
            )->apply(
                'UPGRADE 0.9.0-dev'
            );


        self::assertTrue(
            $result['successful']
        );


        self::assertSame(
            'execution-test-1',
            $result['execution_id']
        );


        self::assertSame(
            'completed',
            $result['journal_status']
        );


        self::assertNull(
            $result['journal_error']
        );


        self::assertSame(
            1,
            $upgrades->calls
        );


        self::assertSame(
            1,
            $journal->startCalls
        );


        self::assertSame(
            1,
            $journal->completeCalls
        );


        self::assertTrue(
            $journal->completedResult['successful']
        );
    }


    public function testFailedUpgradeIsJournaledAsFailed(): void
    {
        $upgrades =
            $this->applyEngine(
                [
                    'successful' =>
                        false,

                    'application_version' =>
                        '0.9.0-dev',

                    'failed_stage' =>
                        'migrations',

                    'error_message' =>
                        'Migration failed.',

                    'changes_made' =>
                        true
                ]
            );


        $journal =
            $this->journal();


        $result =
            $this->service(
                $this->preview(),
                $upgrades,
                $journal
            )->apply(
                'UPGRADE 0.9.0-dev'
            );


        self::assertFalse(
            $result['successful']
        );


        self::assertSame(
            'failed',
            $result['journal_status']
        );


        self::assertSame(
            'migrations',
            $journal->completedResult['failed_stage']
        );


        self::assertSame(
            1,
            $journal->completeCalls
        );
    }


    public function testIncorrectConfirmationCreatesNoJournal(): void
    {
        $upgrades =
            $this->applyEngine(
                [
                    'successful' =>
                        true
                ]
            );


        $journal =
            $this->journal();


        try {

            $this->service(
                $this->preview(),
                $upgrades,
                $journal
            )->apply(
                'UPGRADE WRONG'
            );


            self::fail(
                'Incorrect confirmation should have been rejected.'
            );

        } catch (\InvalidArgumentException $exception) {

            self::assertStringContainsString(
                'must exactly match',
                $exception->getMessage()
            );
        }


        self::assertSame(
            0,
            $upgrades->calls
        );


        self::assertSame(
            0,
            $journal->startCalls
        );


        self::assertSame(
            0,
            $journal->completeCalls
        );
    }


    public function testBlockedPreviewCreatesNoJournal(): void
    {
        $upgrades =
            $this->applyEngine(
                [
                    'successful' =>
                        true
                ]
            );


        $journal =
            $this->journal();


        try {

            $this->service(
                $this->preview(
                    true
                ),
                $upgrades,
                $journal
            )->apply(
                'UPGRADE 0.9.0-dev'
            );


            self::fail(
                'Blocked preview should have prevented execution.'
            );

        } catch (\RuntimeException $exception) {

            self::assertStringContainsString(
                'blocked',
                $exception->getMessage()
            );
        }


        self::assertSame(
            0,
            $upgrades->calls
        );


        self::assertSame(
            0,
            $journal->startCalls
        );
    }


    public function testUnexpectedEngineExceptionIsRecordedAsFailure(): void
    {
        $upgrades =
            new class implements UpgradeExecutionApplyInterface
            {
                public int $calls = 0;


                public function apply(
                    string $confirmation
                ): array
                {
                    $this->calls++;


                    throw new \RuntimeException(
                        'Unexpected engine failure.'
                    );
                }
            };


        $journal =
            $this->journal();


        $result =
            $this->service(
                $this->preview(),
                $upgrades,
                $journal
            )->apply(
                'UPGRADE 0.9.0-dev'
            );


        self::assertFalse(
            $result['successful']
        );


        self::assertSame(
            'execution_bootstrap',
            $result['failed_stage']
        );


        self::assertSame(
            'Unexpected engine failure.',
            $result['error_message']
        );


        self::assertSame(
            'failed',
            $result['journal_status']
        );


        self::assertSame(
            1,
            $journal->completeCalls
        );
    }


    private function service(
        UpgradeExecutionPreviewInterface $preview,
        UpgradeExecutionApplyInterface $upgrades,
        UpgradeExecutionJournalInterface $journal
    ): UpgradeExecutionJournaledApplyService
    {
        return
            new UpgradeExecutionJournaledApplyService(
                $this->projectRoot,
                $preview,
                $upgrades,
                $journal
            );
    }


    private function preview(
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

                        'maintenance_active' =>
                            false,

                        'pending_migration_count' =>
                            0
                    ];
                }
            };
    }


    /**
     * @param array<string,mixed> $result
     */
    private function applyEngine(
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


                    return
                        $this->result;
                }
            };
    }


    private function journal(): UpgradeExecutionJournalInterface
    {
        return
            new class implements UpgradeExecutionJournalInterface
            {
                public int $startCalls = 0;

                public int $completeCalls = 0;

                /**
                 * @var array<string,mixed>
                 */
                public array $completedResult = [];


                public function start(
                    string $version,
                    string $confirmation,
                    array $context = []
                ): array
                {
                    $this->startCalls++;


                    return [
                        'execution_id' =>
                            'execution-test-1',

                        'status' =>
                            'running',

                        'application_version' =>
                            $version,

                        'confirmation' =>
                            $confirmation,

                        'started_at' =>
                            '2026-07-31T15:55:00-07:00',

                        'completed_at' =>
                            null,

                        'successful' =>
                            null,

                        'context' =>
                            $context,

                        'result' =>
                            null
                    ];
                }


                public function complete(
                    string $executionId,
                    array $result
                ): array
                {
                    $this->completeCalls++;


                    $this->completedResult =
                        $result;


                    return [
                        'execution_id' =>
                            $executionId,

                        'status' =>
                            (
                                $result['successful']
                                ??
                                false
                            )
                                ? 'completed'
                                : 'failed',

                        'completed_at' =>
                            '2026-07-31T15:56:00-07:00',

                        'successful' =>
                            (
                                $result['successful']
                                ??
                                false
                            ),

                        'result' =>
                            $result
                    ];
                }


                public function read(
                    string $executionId
                ): array
                {
                    return [];
                }


                public function latest(): ?array
                {
                    return null;
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
