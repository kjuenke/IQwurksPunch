<?php
declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\CommandInterface;
use App\Core\Database;
use App\Logging\LoggerFactory;
use App\Logging\LoggerInterface;
use App\Repositories\ReportScheduleRepository;
use App\Services\ReportEmailService;
use App\Services\ReportScheduleService;
use Throwable;

class ScheduleRunCommand implements CommandInterface
{
    private ReportScheduleService $schedule;

    private ReportEmailService $reports;

    private LoggerInterface $logger;


    public function __construct()
    {
        $repository =
            new ReportScheduleRepository(
                Database::connection()
            );


        $this->schedule =
            new ReportScheduleService(
                $repository
            );


        $this->reports =
            new ReportEmailService();


        $this->logger =
            LoggerFactory::create(
                'scheduler'
            );
    }


    public function name(): string
    {
        return 'schedule:run';
    }


    public function description(): string
    {
        return 'Send the scheduled payroll report when it is due.';
    }


    public function execute(
        array $arguments = []
    ): int
    {
        $startedAt =
            microtime(
                true
            );


        $this->logger->info(
            'Scheduler command started.',
            [
                'command' =>
                    $this->name(),

                'process_id' =>
                    getmypid(),

                'php_version' =>
                    PHP_VERSION,

                'timezone' =>
                    date_default_timezone_get(),

                'memory_bytes' =>
                    memory_get_usage(
                        true
                    ),
            ]
        );


        try {

            $schedule =
                $this->schedule->get();


            if (!$schedule) {

                $this->logger->error(
                    'Report schedule settings were not found.'
                );


                fwrite(
                    STDERR,
                    'Report schedule settings were not found.'
                    .
                    PHP_EOL
                );


                $this->logCompletion(
                    $startedAt,
                    1,
                    'missing_schedule'
                );


                return 1;
            }


            if (!(bool)$schedule['enabled']) {

                $this->logger->info(
                    'Automatic report delivery is disabled.'
                );


                echo
                    'Automatic report delivery is disabled.'
                    .
                    PHP_EOL;


                $this->logCompletion(
                    $startedAt,
                    0,
                    'disabled'
                );


                return 0;
            }


            $this->logger->debug(
                'Evaluating scheduled report delivery.'
            );


            if (!$this->schedule->isDue()) {

                $this->logger->debug(
                    'No scheduled report is due.'
                );


                echo
                    'No scheduled report is due.'
                    .
                    PHP_EOL;


                $this->logCompletion(
                    $startedAt,
                    0,
                    'not_due'
                );


                return 0;
            }


            $this->logger->info(
                'Scheduled report is due.'
            );


            echo
                'Scheduled report is due. Sending...'
                .
                PHP_EOL;


            $sent =
                $this->reports
                    ->sendDailyPayrollReport();


            if (!$sent) {

                $this->logger->error(
                    'Scheduled payroll report failed to send.'
                );


                fwrite(
                    STDERR,
                    'Scheduled report failed to send.'
                    .
                    PHP_EOL
                );


                $this->logCompletion(
                    $startedAt,
                    1,
                    'send_failed'
                );


                return 1;
            }


            $this->logger->info(
                'Scheduled payroll report was sent.'
            );


            if (!$this->schedule->markSent()) {

                $this->logger->error(
                    'Report was sent, but last_sent_at could not be updated.'
                );


                fwrite(
                    STDERR,
                    'Report was sent, but last_sent_at could not be updated.'
                    .
                    PHP_EOL
                );


                $this->logCompletion(
                    $startedAt,
                    1,
                    'mark_sent_failed'
                );


                return 1;
            }


            $this->logger->info(
                'Scheduled payroll report completed successfully.'
            );


            echo
                'Scheduled payroll report sent successfully.'
                .
                PHP_EOL;


            $this->logCompletion(
                $startedAt,
                0,
                'sent'
            );


            return 0;

        } catch (Throwable $exception) {

            $this->logger->critical(
                'Unhandled scheduler exception.',
                [
                    'exception_class' =>
                        $exception::class,

                    'exception_message' =>
                        $exception->getMessage(),

                    'exception_file' =>
                        $exception->getFile(),

                    'exception_line' =>
                        $exception->getLine(),

                    'exception_trace' =>
                        $exception->getTraceAsString(),
                ]
            );


            fwrite(
                STDERR,
                'The scheduler encountered an unexpected error.'
                .
                PHP_EOL
            );


            $this->logCompletion(
                $startedAt,
                1,
                'exception'
            );


            return 1;
        }
    }


    private function logCompletion(
        float $startedAt,
        int $exitCode,
        string $result
    ): void
    {
        $durationMilliseconds =
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
            );


        $this->logger->info(
            'Scheduler command completed.',
            [
                'result' =>
                    $result,

                'exit_code' =>
                    $exitCode,

                'duration_milliseconds' =>
                    $durationMilliseconds,

                'memory_bytes' =>
                    memory_get_usage(
                        true
                    ),

                'peak_memory_bytes' =>
                    memory_get_peak_usage(
                        true
                    ),
            ]
        );
    }
}
