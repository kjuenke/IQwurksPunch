<?php
declare(strict_types=1);

use App\Console\Commands\UpgradeStatusCommand;
use App\Services\UpgradeExecutionJournalInterface;
use PHPUnit\Framework\TestCase;

final class UpgradeStatusCommandTest extends TestCase
{
    public function testCommandIdentity(): void
    {
        $command =
            new UpgradeStatusCommand(
                $this->journal()
            );


        self::assertSame(
            'upgrade:status',
            $command->name()
        );


        self::assertSame(
            'Display the latest or a specific controlled upgrade execution.',
            $command->description()
        );
    }


    public function testNoJournalRecordReturnsSuccess(): void
    {
        $journal =
            $this->journal();


        $command =
            new UpgradeStatusCommand(
                $journal
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


        self::assertSame(
            1,
            $journal->latestCalls
        );


        self::assertStringContainsString(
            'No controlled upgrade execution has been journaled.',
            $output
        );
    }


    public function testLatestCompletedExecutionIsDisplayed(): void
    {
        $journal =
            $this->journal(
                $this->completedRecord()
            );


        $command =
            new UpgradeStatusCommand(
                $journal
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
            'Execution ID: execution-test-1',
            $output
        );


        self::assertStringContainsString(
            'Status: COMPLETED',
            $output
        );


        self::assertStringContainsString(
            'Successful: yes',
            $output
        );


        self::assertStringContainsString(
            'Pre-upgrade backup: pre.sqlite',
            $output
        );


        self::assertStringContainsString(
            'Post-upgrade backup: post.sqlite',
            $output
        );


        self::assertStringContainsString(
            '[COMPLETED] Database migrations',
            $output
        );
    }


    public function testSpecificExecutionIdUsesRead(): void
    {
        $journal =
            $this->journal(
                $this->completedRecord()
            );


        $command =
            new UpgradeStatusCommand(
                $journal
            );


        ob_start();


        $exitCode =
            $command->execute(
                [
                    '--id=execution-test-1'
                ]
            );


        ob_end_clean();


        self::assertSame(
            0,
            $exitCode
        );


        self::assertSame(
            0,
            $journal->latestCalls
        );


        self::assertSame(
            [
                'execution-test-1'
            ],
            $journal->readIds
        );
    }


    /**
     * @param array<string,mixed>|null $record
     */
    private function journal(
        ?array $record = null
    ): UpgradeExecutionJournalInterface
    {
        return
            new class(
                $record
            ) implements UpgradeExecutionJournalInterface
            {
                /**
                 * @var array<string,mixed>|null
                 */
                private ?array $record;

                public int $latestCalls = 0;

                /**
                 * @var array<int,string>
                 */
                public array $readIds = [];


                /**
                 * @param array<string,mixed>|null $record
                 */
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
                    $this->readIds[] =
                        $executionId;


                    if ($this->record === null) {
                        throw new \RuntimeException(
                            'Record not found.'
                        );
                    }


                    return $this->record;
                }


                public function latest(): ?array
                {
                    $this->latestCalls++;


                    return $this->record;
                }
            };
    }


    /**
     * @return array<string,mixed>
     */
    private function completedRecord(): array
    {
        return [
            'execution_id' =>
                'execution-test-1',

            'status' =>
                'completed',

            'application_version' =>
                '0.9.0-dev',

            'confirmation' =>
                'UPGRADE 0.9.0-dev',

            'started_at' =>
                '2026-07-31T16:00:00-07:00',

            'completed_at' =>
                '2026-07-31T16:01:00-07:00',

            'successful' =>
                true,

            'context' => [
                'process_id' =>
                    1234,

                'hostname' =>
                    'iqwurks',

                'preview_status' =>
                    'WARN',

                'pending_migration_count' =>
                    0
            ],

            'result' => [
                'successful' =>
                    true,

                'changes_made' =>
                    true,

                'failed_stage' =>
                    null,

                'pre_upgrade_backup' => [
                    'filename' =>
                        'pre.sqlite'
                ],

                'post_upgrade_backup' => [
                    'filename' =>
                        'post.sqlite'
                ],

                'rollback_available' =>
                    true,

                'maintenance_was_active' =>
                    false,

                'maintenance_activated_by_upgrade' =>
                    true,

                'maintenance_final_active' =>
                    false,

                'journal_status' =>
                    'completed',

                'journal_error' =>
                    null,

                'error_message' =>
                    null,

                'maintenance_protection_error' =>
                    null,

                'duration_milliseconds' =>
                    60000.0,

                'stage_results' => [
                    [
                        'id' =>
                            'migrations',

                        'name' =>
                            'Database migrations',

                        'status' =>
                            'COMPLETED',

                        'successful' =>
                            true
                    ]
                ]
            ]
        ];
    }
}
