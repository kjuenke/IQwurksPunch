<?php
declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\CommandInterface;
use App\Core\AppInfo;
use App\Core\Container;
use App\Services\MailDiagnosticService;
use PDO;
use Throwable;

final class MailCheckCommand implements CommandInterface
{
    private MailDiagnosticService $diagnostic;


    public function __construct(
        ?MailDiagnosticService $diagnostic = null
    )
    {
        if ($diagnostic !== null) {

            $this->diagnostic =
                $diagnostic;


            return;
        }


        $database =
            null;


        try {

            $database =
                Container::db();

        } catch (Throwable $exception) {

            $database =
                null;
        }


        $this->diagnostic =
            new MailDiagnosticService(
                dirname(
                    __DIR__,
                    3
                ),
                Container::logger(
                    'mail-diagnostic'
                ),
                $database instanceof PDO
                    ? $database
                    : null
            );
    }


    public function name(): string
    {
        return 'mail:check';
    }


    public function description(): string
    {
        return 'Check SMTP configuration, connectivity, TLS, and delivery status.';
    }


    public function execute(
        array $arguments = []
    ): int
    {
        if ($arguments !== []) {

            fwrite(
                STDERR,
                'The mail:check command does not accept arguments.'
                .
                PHP_EOL
            );


            return 1;
        }


        try {

            $result =
                $this->diagnostic->diagnose();


            echo
                AppInfo::name()
                .
                ' Mail Diagnostic'
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


            echo
                PHP_EOL
                .
                'No SMTP authentication was attempted and no email was sent.'
                .
                PHP_EOL;


            return
                $result['failure_count']
                >
                0
                    ? 1
                    : 0;

        } catch (Throwable $exception) {

            fwrite(
                STDERR,
                'Mail diagnostic failed unexpectedly: '
                .
                $exception->getMessage()
                .
                PHP_EOL
            );


            return 1;
        }
    }
}
