<?php
declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\CommandInterface;
use App\Core\Container;
use App\Logging\LoggerInterface;
use App\Repositories\ReportDeliveryScheduleRepository;
use App\Services\ReportDeliveryScheduleService;
use App\Services\ReportEmailService;
use App\Services\WeeklyPayrollEmailService;
use Throwable;

class ScheduleRunCommand implements CommandInterface
{
    private ReportDeliveryScheduleService $schedule;

    private ReportEmailService $dailyReports;

    private WeeklyPayrollEmailService $weeklyReports;

    private LoggerInterface $logger;


    public function __construct()
    {
        $repository =
            new ReportDeliveryScheduleRepository(
                Container::db()
            );


        $this->schedule =
            new ReportDeliveryScheduleService(
                $repository
            );


        $this->dailyReports =
            new ReportEmailService();


        $this->weeklyReports =
            new WeeklyPayrollEmailService();


        $this->logger =
            Container::logger(
                'scheduler'
            );
    }


    public function name(): string
    {
        return 'schedule:run';
    }


    public function description(): string
    {
        return 'Send each scheduled payroll report that is due.';
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
                    )
            ]
        );


        try {

            $reportTypes = [
                ReportDeliveryScheduleService::DAILY_PAYROLL =>
                    'Daily payroll',

                ReportDeliveryScheduleService::WEEKLY_PAYROLL =>
                    'Weekly payroll'
            ];


            $enabledCount = 0;

            $dueCount = 0;

            $sentCount = 0;

            $failureCount = 0;


            foreach (
                $reportTypes
                as
                $reportType => $reportLabel
            ) {
                $schedule =
                    $this->schedule->get(
                        $reportType
                    );


                if (!$schedule) {

                    $failureCount++;


                    $this->logger->error(
                        'Report delivery schedule was not found.',
                        [
                            'report_type' =>
                                $reportType
                        ]
                    );


                    fwrite(
                        STDERR,
                        $reportLabel
                        .
                        ' schedule was not found.'
                        .
                        PHP_EOL
                    );


                    continue;
                }


                if (!(bool)$schedule['enabled']) {

                    $this->logger->debug(
                        'Scheduled report is disabled.',
                        [
                            'report_type' =>
                                $reportType
                        ]
                    );


                    continue;
                }


                $enabledCount++;


                $this->logger->debug(
                    'Evaluating scheduled report delivery.',
                    [
                        'report_type' =>
                            $reportType,

                        'send_time' =>
                            $schedule['send_time']
                            ??
                            null,

                        'send_day_of_week' =>
                            $schedule['send_day_of_week']
                            ??
                            null,

                        'last_sent_at' =>
                            $schedule['last_sent_at']
                            ??
                            null
                    ]
                );


                if (
                    !$this->schedule->isDue(
                        $reportType
                    )
                ) {
                    $this->logger->debug(
                        'Scheduled report is not due.',
                        [
                            'report_type' =>
                                $reportType
                        ]
                    );


                    continue;
                }


                $dueCount++;


                $this->logger->info(
                    'Scheduled report is due.',
                    [
                        'report_type' =>
                            $reportType
                    ]
                );


                echo
                    $reportLabel
                    .
                    ' report is due. Sending...'
                    .
                    PHP_EOL;


                try {

                    $sent =
                        $this->sendReport(
                            $reportType
                        );


                    if (!$sent) {

                        $failureCount++;


                        $error =
                            $reportLabel
                            .
                            ' report delivery returned a failure result.';


                        $this->schedule->markFailed(
                            $reportType,
                            $error
                        );


                        $this->logger->error(
                            'Scheduled report failed to send.',
                            [
                                'report_type' =>
                                    $reportType
                            ]
                        );


                        fwrite(
                            STDERR,
                            $error
                            .
                            PHP_EOL
                        );


                        continue;
                    }


                    if (
                        !$this->schedule->markSent(
                            $reportType
                        )
                    ) {
                        $failureCount++;


                        $error =
                            $reportLabel
                            .
                            ' report was sent, but its schedule could not be marked as sent.';


                        $this->schedule->markFailed(
                            $reportType,
                            $error
                        );


                        $this->logger->error(
                            'Report was sent, but its schedule could not be marked as sent.',
                            [
                                'report_type' =>
                                    $reportType
                            ]
                        );


                        fwrite(
                            STDERR,
                            $error
                            .
                            PHP_EOL
                        );


                        continue;
                    }


                    $sentCount++;


                    $this->logger->info(
                        'Scheduled report completed successfully.',
                        [
                            'report_type' =>
                                $reportType
                        ]
                    );


                    echo
                        $reportLabel
                        .
                        ' report sent successfully.'
                        .
                        PHP_EOL;

                } catch (Throwable $exception) {

                    $failureCount++;


                    $this->schedule->markFailed(
                        $reportType,
                        $exception->getMessage()
                    );


                    $this->logger->error(
                        'Scheduled report processing failed.',
                        [
                            'report_type' =>
                                $reportType,

                            'exception_class' =>
                                $exception::class,

                            'exception_message' =>
                                $exception->getMessage(),

                            'exception_file' =>
                                $exception->getFile(),

                            'exception_line' =>
                                $exception->getLine()
                        ]
                    );


                    fwrite(
                        STDERR,
                        $reportLabel
                        .
                        ' report failed: '
                        .
                        $exception->getMessage()
                        .
                        PHP_EOL
                    );
                }
            }


            if ($failureCount > 0) {

                $result =
                    $sentCount > 0
                        ? 'partial_failure'
                        : 'failed';


                $this->logCompletion(
                    $startedAt,
                    1,
                    $result,
                    [
                        'enabled_count' =>
                            $enabledCount,

                        'due_count' =>
                            $dueCount,

                        'sent_count' =>
                            $sentCount,

                        'failure_count' =>
                            $failureCount
                    ]
                );


                return 1;
            }


            if ($enabledCount === 0) {

                echo
                    'Automatic payroll report delivery is disabled.'
                    .
                    PHP_EOL;


                $this->logger->info(
                    'Automatic payroll report delivery is disabled.'
                );


                $this->logCompletion(
                    $startedAt,
                    0,
                    'disabled',
                    [
                        'enabled_count' =>
                            0,

                        'due_count' =>
                            0,

                        'sent_count' =>
                            0,

                        'failure_count' =>
                            0
                    ]
                );


                return 0;
            }


            if ($dueCount === 0) {

                echo
                    'No scheduled report is due.'
                    .
                    PHP_EOL;


                $this->logger->debug(
                    'No scheduled report is due.'
                );


                $this->logCompletion(
                    $startedAt,
                    0,
                    'not_due',
                    [
                        'enabled_count' =>
                            $enabledCount,

                        'due_count' =>
                            0,

                        'sent_count' =>
                            0,

                        'failure_count' =>
                            0
                    ]
                );


                return 0;
            }


            echo
                'All due scheduled reports completed successfully.'
                .
                PHP_EOL;


            $this->logCompletion(
                $startedAt,
                0,
                'sent',
                [
                    'enabled_count' =>
                        $enabledCount,

                    'due_count' =>
                        $dueCount,

                    'sent_count' =>
                        $sentCount,

                    'failure_count' =>
                        0
                ]
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
                        $exception->getTraceAsString()
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


    private function sendReport(
        string $reportType
    ): bool
    {
        if (
            $reportType
            ===
            ReportDeliveryScheduleService::DAILY_PAYROLL
        ) {
            return
                $this->dailyReports
                    ->sendDailyPayrollReport();
        }


        if (
            $reportType
            ===
            ReportDeliveryScheduleService::WEEKLY_PAYROLL
        ) {
            $referenceDate =
                $this->schedule
                    ->previousWeekReferenceDate();


            return
                $this->weeklyReports
                    ->sendWeeklyPayrollReport(
                        $referenceDate
                    );
        }


        return false;
    }


    /**
     * @param array<string,mixed> $context
     */
    private function logCompletion(
        float $startedAt,
        int $exitCode,
        string $result,
        array $context = []
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

                ...$context
            ]
        );
    }
}
