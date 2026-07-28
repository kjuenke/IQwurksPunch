<?php
declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\CommandInterface;
use App\Core\AppInfo;
use App\Core\Container;
use App\Services\OperationalFailureNotificationService;
use App\Services\SystemDoctorService;
use Throwable;

final class DoctorCommand implements CommandInterface
{
    private SystemDoctorService $doctor;


    public function __construct(
        ?SystemDoctorService $doctor = null
    )
    {
        $this->doctor =
            $doctor
            ??
            new SystemDoctorService(
                dirname(
                    __DIR__,
                    3
                )
            );
    }


    public function name(): string
    {
        return 'doctor';
    }


    public function description(): string
    {
        return 'Run application, database, backup, mail, and scheduler diagnostics.';
    }


    public function execute(
        array $arguments = []
    ): int
    {
        if ($arguments !== []) {

            fwrite(
                STDERR,
                'The doctor command does not accept arguments.'
                .
                PHP_EOL
            );


            return 1;
        }


        try {

            $result =
                $this->doctor->diagnose();


            echo
                AppInfo::name()
                .
                ' '
                .
                AppInfo::version()
                .
                ' System Doctor'
                .
                PHP_EOL;


            echo
                str_repeat(
                    '=',
                    72
                )
                .
                PHP_EOL;


            echo
                'Checked: '
                .
                $result['checked_at']
                .
                PHP_EOL
                .
                PHP_EOL;


            foreach (
                $result['checks']
                as $check
            ) {
                echo
                    '['
                    .
                    str_pad(
                        (string)$check['status'],
                        4
                    )
                    .
                    '] '
                    .
                    str_pad(
                        (string)$check['name'],
                        26
                    )
                    .
                    (string)$check['message']
                    .
                    PHP_EOL;


                foreach (
                    $check['details']
                    as $detail
                ) {
                    echo
                        '       - '
                        .
                        $detail
                        .
                        PHP_EOL;
                }
            }


            echo
                PHP_EOL
                .
                str_repeat(
                    '-',
                    72
                )
                .
                PHP_EOL;


            echo
                'Overall status: '
                .
                $result['overall_status']
                .
                PHP_EOL;


            echo
                'Passed: '
                .
                $result['pass_count']
                .
                PHP_EOL;


            echo
                'Warnings: '
                .
                $result['warning_count']
                .
                PHP_EOL;


            echo
                'Failures: '
                .
                $result['failure_count']
                .
                PHP_EOL;


            echo
                'Total checks: '
                .
                $result['total_count']
                .
                PHP_EOL;


            echo
                'Duration: '
                .
                number_format(
                    (float)$result['duration_milliseconds'],
                    2
                )
                .
                ' ms'
                .
                PHP_EOL;


            try {

                $logger =
                    Container::logger(
                        'doctor'
                    );


                $context = [
                    'overall_status' =>
                        $result['overall_status'],

                    'pass_count' =>
                        $result['pass_count'],

                    'warning_count' =>
                        $result['warning_count'],

                    'failure_count' =>
                        $result['failure_count'],

                    'total_count' =>
                        $result['total_count'],

                    'duration_milliseconds' =>
                        $result['duration_milliseconds']
                ];


                if (
                    $result['failure_count']
                    >
                    0
                ) {
                    $logger->error(
                        'System doctor detected one or more failures.',
                        $context
                    );

                } elseif (
                    $result['warning_count']
                    >
                    0
                ) {
                    $logger->warning(
                        'System doctor completed with warnings.',
                        $context
                    );

                } else {

                    $logger->info(
                        'System doctor completed successfully.',
                        $context
                    );
                }

            } catch (Throwable $loggingException) {

                fwrite(
                    STDERR,
                    'Warning: the doctor result could not be written to the application log.'
                    .
                    PHP_EOL
                );
            }


            if (
                $result['failure_count']
                >
                0
            ) {
                $this->notifyOperationalFailure(
                    'System Doctor',
                    'The system doctor detected one or more application failures.',
                    [
                        'command' =>
                            $this->name(),

                        'application_version' =>
                            AppInfo::version(),

                        'checked_at' =>
                            $result['checked_at']
                            ??
                            null,

                        'overall_status' =>
                            $result['overall_status']
                            ??
                            null,

                        'pass_count' =>
                            $result['pass_count']
                            ??
                            null,

                        'warning_count' =>
                            $result['warning_count']
                            ??
                            null,

                        'failure_count' =>
                            $result['failure_count']
                            ??
                            null,

                        'total_count' =>
                            $result['total_count']
                            ??
                            null,

                        'failed_checks' =>
                            $this->failedChecks(
                                $result['checks']
                                ??
                                []
                            ),

                        'duration_milliseconds' =>
                            $result['duration_milliseconds']
                            ??
                            null,

                        'exit_code' =>
                            1
                    ]
                );


                return 1;
            }


            return 0;

        } catch (Throwable $exception) {

            fwrite(
                STDERR,
                'System doctor failed unexpectedly: '
                .
                $exception->getMessage()
                .
                PHP_EOL
            );


            $this->notifyOperationalFailure(
                'System Doctor',
                'The system doctor failed unexpectedly.',
                [
                    'command' =>
                        $this->name(),

                    'application_version' =>
                        AppInfo::version(),

                    'exception_class' =>
                        $exception::class,

                    'exception_message' =>
                        $exception->getMessage(),

                    'exception_file' =>
                        $exception->getFile(),

                    'exception_line' =>
                        $exception->getLine(),

                    'exit_code' =>
                        1
                ]
            );


            return 1;
        }
    }


    /**
     * @param array<int,array<string,mixed>> $checks
     *
     * @return array<int,array<string,mixed>>
     */
    private function failedChecks(
        array $checks
    ): array
    {
        $failures = [];


        foreach ($checks as $check) {

            if (
                strtoupper(
                    trim(
                        (string)(
                            $check['status']
                            ??
                            ''
                        )
                    )
                )
                !==
                'FAIL'
            ) {
                continue;
            }


            $failures[] = [
                'name' =>
                    $check['name']
                    ??
                    'Unnamed check',

                'message' =>
                    $check['message']
                    ??
                    'No failure message was recorded.',

                'details' =>
                    $check['details']
                    ??
                    []
            ];
        }


        return $failures;
    }


    /**
     * Operational notification failures must never replace the original
     * system-doctor failure or alter its exit code.
     *
     * @param array<string,mixed> $details
     */
    private function notifyOperationalFailure(
        string $source,
        string $summary,
        array $details
    ): void
    {
        try {

            $notifications =
                new OperationalFailureNotificationService();


            $sent =
                $notifications->sendFailure(
                    $source,
                    $summary,
                    $details
                );


            if (!$sent) {

                fwrite(
                    STDERR,
                    'Warning: the operational failure notification could not be delivered.'
                    .
                    PHP_EOL
                );
            }

        } catch (Throwable $notificationException) {

            fwrite(
                STDERR,
                'Warning: the operational failure notification could not be delivered: '
                .
                $notificationException->getMessage()
                .
                PHP_EOL
            );
        }
    }
}
