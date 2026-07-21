<?php
declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\CommandInterface;
use App\Core\AppInfo;
use App\Core\Container;
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


            return 1;
        }
    }
}
