<?php
declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class ProcessRunnerService implements ProcessRunnerInterface
{
    private float $defaultTimeoutSeconds;


    public function __construct(
        float $defaultTimeoutSeconds = 300.0
    )
    {
        $this->assertValidTimeout(
            $defaultTimeoutSeconds
        );


        $this->defaultTimeoutSeconds =
            $defaultTimeoutSeconds;
    }


    /**
     * @param array<int,string> $command
     * @param array<string,scalar|null> $environment
     *
     * @return array{
     *     command:array<int,string>,
     *     display_command:string,
     *     working_directory:?string,
     *     environment_overrides:array<string,scalar|null>,
     *     timeout_seconds:float,
     *     started_at:string,
     *     completed_at:string,
     *     exit_code:int,
     *     successful:bool,
     *     timed_out:bool,
     *     stdout:string,
     *     stderr:string,
     *     duration_milliseconds:float
     * }
     */
    public function run(
        array $command,
        ?string $workingDirectory = null,
        array $environment = [],
        ?float $timeoutSeconds = null
    ): array
    {
        $command =
            $this->normalizeCommand(
                $command
            );


        $workingDirectory =
            $this->normalizeWorkingDirectory(
                $workingDirectory
            );


        $environmentOverrides =
            $this->normalizeEnvironmentOverrides(
                $environment
            );


        $processEnvironment =
            $this->processEnvironment(
                $environmentOverrides
            );


        $timeoutSeconds =
            $timeoutSeconds
            ??
            $this->defaultTimeoutSeconds;


        $this->assertValidTimeout(
            $timeoutSeconds
        );


        $startedAtTimestamp =
            microtime(
                true
            );


        $startedAt =
            date(
                'Y-m-d H:i:s T'
            );


        $descriptorSpecification = [
            0 => [
                'pipe',
                'r'
            ],

            1 => [
                'pipe',
                'w'
            ],

            2 => [
                'pipe',
                'w'
            ]
        ];


        $pipes = [];


        try {

            $process =
                @proc_open(
                    $command,
                    $descriptorSpecification,
                    $pipes,
                    $workingDirectory,
                    $processEnvironment,
                    [
                        'bypass_shell' =>
                            true,

                        'suppress_errors' =>
                            true
                    ]
                );

        } catch (Throwable $exception) {

            throw new RuntimeException(
                'The process could not be started: '
                .
                $exception->getMessage(),
                0,
                $exception
            );
        }


        if (!is_resource($process)) {
            throw new RuntimeException(
                'The process could not be started.'
            );
        }


        $stdout = '';

        $stderr = '';

        $timedOut = false;

        $observedExitCode = null;


        try {

            if (
                isset(
                    $pipes[0]
                )
                &&
                is_resource(
                    $pipes[0]
                )
            ) {
                fclose(
                    $pipes[0]
                );
            }


            foreach (
                [
                    1,
                    2
                ]
                as $pipeNumber
            ) {
                if (
                    isset(
                        $pipes[$pipeNumber]
                    )
                    &&
                    is_resource(
                        $pipes[$pipeNumber]
                    )
                ) {
                    stream_set_blocking(
                        $pipes[$pipeNumber],
                        false
                    );
                }
            }


            while (true) {

                $this->readAvailableOutput(
                    $pipes,
                    $stdout,
                    $stderr
                );


                $status =
                    proc_get_status(
                        $process
                    );


                if (!is_array($status)) {
                    throw new RuntimeException(
                        'The child-process status could not be read.'
                    );
                }


                if (!$status['running']) {

                    $exitCode =
                        (int)(
                            $status['exitcode']
                            ??
                            -1
                        );


                    if ($exitCode >= 0) {
                        $observedExitCode =
                            $exitCode;
                    }


                    break;
                }


                $elapsedSeconds =
                    microtime(
                        true
                    )
                    -
                    $startedAtTimestamp;


                if (
                    $elapsedSeconds
                    >=
                    $timeoutSeconds
                ) {
                    $timedOut =
                        true;


                    $this->terminateProcess(
                        $process,
                        $pipes,
                        $stdout,
                        $stderr
                    );


                    $status =
                        proc_get_status(
                            $process
                        );


                    if (
                        is_array(
                            $status
                        )
                        &&
                        !$status['running']
                    ) {
                        $exitCode =
                            (int)(
                                $status['exitcode']
                                ??
                                -1
                            );


                        if ($exitCode >= 0) {
                            $observedExitCode =
                                $exitCode;
                        }
                    }


                    break;
                }


                usleep(
                    10000
                );
            }


            $this->readAvailableOutput(
                $pipes,
                $stdout,
                $stderr
            );


            $this->closeOutputPipes(
                $pipes,
                $stdout,
                $stderr
            );


            $closeExitCode =
                proc_close(
                    $process
                );


            $exitCode =
                $timedOut
                    ? 124
                    : (
                        $observedExitCode
                        ??
                        $closeExitCode
                    );


            $durationMilliseconds =
                round(
                    (
                        microtime(
                            true
                        )
                        -
                        $startedAtTimestamp
                    )
                    *
                    1000,
                    2
                );


            return [
                'command' =>
                    $command,

                'display_command' =>
                    $this->displayCommand(
                        $command
                    ),

                'working_directory' =>
                    $workingDirectory,

                'environment_overrides' =>
                    $environmentOverrides,

                'timeout_seconds' =>
                    $timeoutSeconds,

                'started_at' =>
                    $startedAt,

                'completed_at' =>
                    date(
                        'Y-m-d H:i:s T'
                    ),

                'exit_code' =>
                    $exitCode,

                'successful' =>
                    !$timedOut
                    &&
                    $exitCode === 0,

                'timed_out' =>
                    $timedOut,

                'stdout' =>
                    $stdout,

                'stderr' =>
                    $stderr,

                'duration_milliseconds' =>
                    $durationMilliseconds
            ];

        } catch (Throwable $exception) {

            @proc_terminate(
                $process
            );


            $this->closePipes(
                $pipes
            );


            @proc_close(
                $process
            );


            throw new RuntimeException(
                'Process execution failed: '
                .
                $exception->getMessage(),
                0,
                $exception
            );
        }
    }


    /**
     * @param array<int,mixed> $command
     *
     * @return array<int,string>
     */
    private function normalizeCommand(
        array $command
    ): array
    {
        if ($command === []) {
            throw new InvalidArgumentException(
                'A process command is required.'
            );
        }


        $normalized = [];


        foreach ($command as $index => $argument) {

            if (!is_string($argument)) {
                throw new InvalidArgumentException(
                    'Process command argument '
                    .
                    $index
                    .
                    ' must be a string.'
                );
            }


            if (
                str_contains(
                    $argument,
                    "\0"
                )
            ) {
                throw new InvalidArgumentException(
                    'Process command arguments cannot contain null bytes.'
                );
            }


            if (
                $index === 0
                &&
                trim(
                    $argument
                )
                ===
                ''
            ) {
                throw new InvalidArgumentException(
                    'The process executable cannot be empty.'
                );
            }


            $normalized[] =
                $argument;
        }


        return $normalized;
    }


    private function normalizeWorkingDirectory(
        ?string $workingDirectory
    ): ?string
    {
        if ($workingDirectory === null) {

            $currentDirectory =
                getcwd();


            return
                $currentDirectory === false
                    ? null
                    : $currentDirectory;
        }


        $workingDirectory =
            trim(
                $workingDirectory
            );


        if ($workingDirectory === '') {
            throw new InvalidArgumentException(
                'The process working directory cannot be empty.'
            );
        }


        if (!is_dir($workingDirectory)) {
            throw new InvalidArgumentException(
                'The process working directory does not exist: '
                .
                $workingDirectory
            );
        }


        if (!is_readable($workingDirectory)) {
            throw new InvalidArgumentException(
                'The process working directory is not readable: '
                .
                $workingDirectory
            );
        }


        $resolved =
            realpath(
                $workingDirectory
            );


        if ($resolved === false) {
            throw new InvalidArgumentException(
                'The process working directory could not be resolved: '
                .
                $workingDirectory
            );
        }


        return $resolved;
    }


    /**
     * @param array<string,mixed> $environment
     *
     * @return array<string,scalar|null>
     */
    private function normalizeEnvironmentOverrides(
        array $environment
    ): array
    {
        $normalized = [];


        foreach ($environment as $name => $value) {

            if (!is_string($name)) {
                throw new InvalidArgumentException(
                    'Environment variable names must be strings.'
                );
            }


            $name =
                trim(
                    $name
                );


            if (
                $name === ''
                ||
                str_contains(
                    $name,
                    '='
                )
                ||
                str_contains(
                    $name,
                    "\0"
                )
            ) {
                throw new InvalidArgumentException(
                    'Invalid environment variable name.'
                );
            }


            if ($value === null) {

                $normalized[$name] =
                    null;


                continue;
            }


            if (!is_scalar($value)) {
                throw new InvalidArgumentException(
                    'Environment variable '
                    .
                    $name
                    .
                    ' must contain a scalar value or null.'
                );
            }


            $stringValue =
                (string)$value;


            if (
                str_contains(
                    $stringValue,
                    "\0"
                )
            ) {
                throw new InvalidArgumentException(
                    'Environment variable values cannot contain null bytes.'
                );
            }


            $normalized[$name] =
                $value;
        }


        return $normalized;
    }


    /**
     * @param array<string,scalar|null> $overrides
     *
     * @return array<string,string>|null
     */
    private function processEnvironment(
        array $overrides
    ): ?array
    {
        if ($overrides === []) {
            return null;
        }


        $currentEnvironment =
            getenv();


        if (!is_array($currentEnvironment)) {
            $currentEnvironment = [];
        }


        $environment = [];


        foreach ($currentEnvironment as $name => $value) {

            if (
                is_string(
                    $name
                )
                &&
                is_string(
                    $value
                )
            ) {
                $environment[$name] =
                    $value;
            }
        }


        foreach ($overrides as $name => $value) {

            if ($value === null) {

                unset(
                    $environment[$name]
                );


                continue;
            }


            $environment[$name] =
                (string)$value;
        }


        return $environment;
    }


    private function assertValidTimeout(
        float $timeoutSeconds
    ): void
    {
        if (
            !is_finite(
                $timeoutSeconds
            )
            ||
            $timeoutSeconds <= 0
        ) {
            throw new InvalidArgumentException(
                'The process timeout must be greater than zero seconds.'
            );
        }
    }


    /**
     * @param array<int,resource> $pipes
     */
    private function readAvailableOutput(
        array $pipes,
        string &$stdout,
        string &$stderr
    ): void
    {
        if (
            isset(
                $pipes[1]
            )
            &&
            is_resource(
                $pipes[1]
            )
        ) {
            $contents =
                stream_get_contents(
                    $pipes[1]
                );


            if (
                $contents !== false
                &&
                $contents !== ''
            ) {
                $stdout .=
                    $contents;
            }
        }


        if (
            isset(
                $pipes[2]
            )
            &&
            is_resource(
                $pipes[2]
            )
        ) {
            $contents =
                stream_get_contents(
                    $pipes[2]
                );


            if (
                $contents !== false
                &&
                $contents !== ''
            ) {
                $stderr .=
                    $contents;
            }
        }
    }


    /**
     * @param resource $process
     * @param array<int,resource> $pipes
     */
    private function terminateProcess(
        $process,
        array $pipes,
        string &$stdout,
        string &$stderr
    ): void
    {
        @proc_terminate(
            $process
        );


        $graceDeadline =
            microtime(
                true
            )
            +
            1.0;


        while (
            microtime(
                true
            )
            <
            $graceDeadline
        ) {
            $this->readAvailableOutput(
                $pipes,
                $stdout,
                $stderr
            );


            $status =
                proc_get_status(
                    $process
                );


            if (
                !is_array(
                    $status
                )
                ||
                !$status['running']
            ) {
                return;
            }


            usleep(
                10000
            );
        }


        @proc_terminate(
            $process,
            9
        );


        $killDeadline =
            microtime(
                true
            )
            +
            1.0;


        while (
            microtime(
                true
            )
            <
            $killDeadline
        ) {
            $this->readAvailableOutput(
                $pipes,
                $stdout,
                $stderr
            );


            $status =
                proc_get_status(
                    $process
                );


            if (
                !is_array(
                    $status
                )
                ||
                !$status['running']
            ) {
                return;
            }


            usleep(
                10000
            );
        }
    }


    /**
     * @param array<int,resource> $pipes
     */
    private function closeOutputPipes(
        array &$pipes,
        string &$stdout,
        string &$stderr
    ): void
    {
        foreach (
            [
                1 =>
                    'stdout',

                2 =>
                    'stderr'
            ]
            as
            $pipeNumber => $destination
        ) {
            if (
                !isset(
                    $pipes[$pipeNumber]
                )
                ||
                !is_resource(
                    $pipes[$pipeNumber]
                )
            ) {
                continue;
            }


            stream_set_blocking(
                $pipes[$pipeNumber],
                true
            );


            $contents =
                stream_get_contents(
                    $pipes[$pipeNumber]
                );


            if (
                $contents !== false
                &&
                $contents !== ''
            ) {
                if ($destination === 'stdout') {

                    $stdout .=
                        $contents;

                } else {

                    $stderr .=
                        $contents;
                }
            }


            fclose(
                $pipes[$pipeNumber]
            );
        }


        $pipes = [];
    }


    /**
     * @param array<int,resource> $pipes
     */
    private function closePipes(
        array &$pipes
    ): void
    {
        foreach ($pipes as $pipe) {

            if (is_resource($pipe)) {
                fclose(
                    $pipe
                );
            }
        }


        $pipes = [];
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
}
