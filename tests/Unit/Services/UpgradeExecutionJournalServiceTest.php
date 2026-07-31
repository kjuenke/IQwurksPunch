<?php
declare(strict_types=1);

use App\Services\UpgradeExecutionJournalService;
use PHPUnit\Framework\TestCase;

final class UpgradeExecutionJournalServiceTest extends TestCase
{
    private string $temporaryDirectory;


    protected function setUp(): void
    {
        parent::setUp();


        $this->temporaryDirectory =
            sys_get_temp_dir()
            .
            '/iqwurks-upgrade-journal-'
            .
            bin2hex(
                random_bytes(
                    8
                )
            );
    }


    protected function tearDown(): void
    {
        $this->removeDirectory(
            $this->temporaryDirectory
        );


        parent::tearDown();
    }


    public function testStartCreatesRunningRecordAndLatestPointer(): void
    {
        $journal =
            new UpgradeExecutionJournalService(
                $this->temporaryDirectory
            );


        $record =
            $journal->start(
                '0.9.0-dev',
                'UPGRADE 0.9.0-dev',
                [
                    'process_id' =>
                        1234
                ]
            );


        self::assertSame(
            'running',
            $record['status']
        );


        self::assertSame(
            '0.9.0-dev',
            $record['application_version']
        );


        self::assertSame(
            'UPGRADE 0.9.0-dev',
            $record['confirmation']
        );


        self::assertNull(
            $record['completed_at']
        );


        self::assertNull(
            $record['successful']
        );


        self::assertSame(
            [
                'process_id' =>
                    1234
            ],
            $record['context']
        );


        self::assertFileExists(
            $this->temporaryDirectory
            .
            '/'
            .
            $record['execution_id']
            .
            '.json'
        );


        self::assertFileExists(
            $this->temporaryDirectory
            .
            '/latest.json'
        );


        self::assertSame(
            $record,
            $journal->latest()
        );
    }


    public function testCompleteRecordsSuccessfulResult(): void
    {
        $journal =
            new UpgradeExecutionJournalService(
                $this->temporaryDirectory
            );


        $started =
            $journal->start(
                '0.9.0-dev',
                'UPGRADE 0.9.0-dev'
            );


        $completed =
            $journal->complete(
                $started['execution_id'],
                [
                    'successful' =>
                        true,

                    'pre_upgrade_backup' => [
                        'filename' =>
                            'pre.sqlite'
                    ]
                ]
            );


        self::assertSame(
            'completed',
            $completed['status']
        );


        self::assertTrue(
            $completed['successful']
        );


        self::assertNotNull(
            $completed['completed_at']
        );


        self::assertSame(
            'pre.sqlite',
            $completed['result']['pre_upgrade_backup']['filename']
        );


        self::assertSame(
            $completed,
            $journal->read(
                $started['execution_id']
            )
        );


        self::assertSame(
            $completed,
            $journal->latest()
        );
    }


    public function testCompleteRecordsFailedResult(): void
    {
        $journal =
            new UpgradeExecutionJournalService(
                $this->temporaryDirectory
            );


        $started =
            $journal->start(
                '0.9.0-dev',
                'UPGRADE 0.9.0-dev'
            );


        $completed =
            $journal->complete(
                $started['execution_id'],
                [
                    'successful' =>
                        false,

                    'failed_stage' =>
                        'migrations',

                    'error_message' =>
                        'Migration failed.'
                ]
            );


        self::assertSame(
            'failed',
            $completed['status']
        );


        self::assertFalse(
            $completed['successful']
        );


        self::assertSame(
            'migrations',
            $completed['result']['failed_stage']
        );


        self::assertSame(
            'Migration failed.',
            $completed['result']['error_message']
        );
    }


    public function testUnsafeExecutionIdIsRejected(): void
    {
        $journal =
            new UpgradeExecutionJournalService(
                $this->temporaryDirectory
            );


        $this->expectException(
            \RuntimeException::class
        );


        $this->expectExceptionMessage(
            'The upgrade execution ID is invalid.'
        );


        $journal->read(
            '../outside'
        );
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
