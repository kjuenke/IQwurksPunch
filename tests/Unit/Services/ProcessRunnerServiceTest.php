<?php
declare(strict_types=1);

use App\Services\ProcessRunnerService;
use PHPUnit\Framework\TestCase;

final class ProcessRunnerServiceTest extends TestCase
{
    private string $temporaryDirectory;


    protected function setUp(): void
    {
        parent::setUp();


        $this->temporaryDirectory =
            sys_get_temp_dir()
            .
            '/iqwurks-process-runner-'
            .
            bin2hex(
                random_bytes(
                    8
                )
            );


        self::assertTrue(
            mkdir(
                $this->temporaryDirectory,
                0770,
                true
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


    public function testCapturesOutputErrorAndExitCode(): void
    {
        $runner =
            new ProcessRunnerService(
                5.0
            );


        $result =
            $runner->run(
                [
                    PHP_BINARY,
                    '-r',
                    'fwrite(STDOUT, "standard-output"); fwrite(STDERR, "standard-error"); exit(3);'
                ]
            );


        self::assertSame(
            3,
            $result['exit_code']
        );


        self::assertFalse(
            $result['successful']
        );


        self::assertFalse(
            $result['timed_out']
        );


        self::assertSame(
            'standard-output',
            $result['stdout']
        );


        self::assertSame(
            'standard-error',
            $result['stderr']
        );


        self::assertGreaterThanOrEqual(
            0.0,
            $result['duration_milliseconds']
        );
    }


    public function testArgumentsAreNotInterpretedByAShell(): void
    {
        $runner =
            new ProcessRunnerService(
                5.0
            );


        $literalArgument =
            'alpha; echo shell-injection';


        $result =
            $runner->run(
                [
                    PHP_BINARY,
                    '-r',
                    'echo $argv[1];',
                    $literalArgument
                ]
            );


        self::assertSame(
            0,
            $result['exit_code']
        );


        self::assertTrue(
            $result['successful']
        );


        self::assertSame(
            $literalArgument,
            $result['stdout']
        );


        self::assertSame(
            '',
            $result['stderr']
        );
    }


    public function testWorkingDirectoryAndEnvironmentOverridesAreApplied(): void
    {
        $runner =
            new ProcessRunnerService(
                5.0
            );


        $result =
            $runner->run(
                [
                    PHP_BINARY,
                    '-r',
                    'echo getcwd() . PHP_EOL . getenv("IQWURKS_PROCESS_TEST");'
                ],
                $this->temporaryDirectory,
                [
                    'IQWURKS_PROCESS_TEST' =>
                        'environment-value'
                ]
            );


        self::assertSame(
            0,
            $result['exit_code']
        );


        self::assertSame(
            $this->temporaryDirectory
            .
            PHP_EOL
            .
            'environment-value',
            $result['stdout']
        );


        self::assertSame(
            $this->temporaryDirectory,
            $result['working_directory']
        );


        self::assertSame(
            [
                'IQWURKS_PROCESS_TEST' =>
                    'environment-value'
            ],
            $result['environment_overrides']
        );
    }


    public function testTimedOutProcessIsTerminated(): void
    {
        $runner =
            new ProcessRunnerService(
                0.10
            );


        $result =
            $runner->run(
                [
                    PHP_BINARY,
                    '-r',
                    'sleep(2); echo "late-output";'
                ]
            );


        self::assertSame(
            124,
            $result['exit_code']
        );


        self::assertFalse(
            $result['successful']
        );


        self::assertTrue(
            $result['timed_out']
        );


        self::assertStringNotContainsString(
            'late-output',
            $result['stdout']
        );
    }


    public function testEmptyCommandIsRejected(): void
    {
        $runner =
            new ProcessRunnerService();


        $this->expectException(
            \InvalidArgumentException::class
        );


        $this->expectExceptionMessage(
            'A process command is required.'
        );


        $runner->run(
            []
        );
    }


    public function testMissingWorkingDirectoryIsRejected(): void
    {
        $runner =
            new ProcessRunnerService();


        $missingDirectory =
            $this->temporaryDirectory
            .
            '/missing';


        $this->expectException(
            \InvalidArgumentException::class
        );


        $this->expectExceptionMessage(
            'The process working directory does not exist'
        );


        $runner->run(
            [
                PHP_BINARY,
                '-r',
                'echo "test";'
            ],
            $missingDirectory
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
