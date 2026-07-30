<?php
declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\CommandInterface;
use App\Core\Container;
use App\Repositories\EmailDeliveryAttemptRepository;
use App\Services\EmailDeliveryRetryExecutionService;
use App\Services\EmailDeliveryRetryPlanService;
use InvalidArgumentException;
use Throwable;

final class MailRetryCommand implements CommandInterface
{
    private EmailDeliveryAttemptRepository $attempts;

    private EmailDeliveryRetryExecutionService $execution;


    public function __construct(
        ?EmailDeliveryAttemptRepository $attempts = null,
        ?EmailDeliveryRetryExecutionService $execution = null
    )
    {
        $this->attempts =
            $attempts
            ??
            new EmailDeliveryAttemptRepository(
                Container::db()
            );


        $this->execution =
            $execution
            ??
            new EmailDeliveryRetryExecutionService();
    }


    public function name(): string
    {
        return 'mail:retry';
    }


    public function description(): string
    {
        return 'Preview or retry failed payroll-report email deliveries.';
    }


    public function execute(
        array $arguments = []
    ): int
    {
        try {

            $options =
                $this->parseArguments(
                    $arguments
                );


            $limit =
                $options['limit'];


            $send =
                $options['send'];


            $failures =
                array_values(
                    array_filter(
                        $this->attempts
                            ->retryableFailures(
                                $limit
                            ),
                        static fn (
                            array $attempt
                        ): bool =>
                            in_array(
                                (string)(
                                    $attempt['notification_type']
                                    ??
                                    ''
                                ),
                                [
                                    EmailDeliveryRetryPlanService::DAILY_PAYROLL,
                                    EmailDeliveryRetryPlanService::WEEKLY_PAYROLL,
                                    EmailDeliveryRetryPlanService::EXCEPTION_REPORTS
                                ],
                                true
                            )
                    )
                );


            echo
                'Retryable payroll-report deliveries: '
                .
                count(
                    $failures
                )
                .
                PHP_EOL;


            echo
                'Selection limit: '
                .
                $limit
                .
                PHP_EOL;


            echo
                'Mode: '
                .
                (
                    $send
                        ? 'SEND'
                        : 'PREVIEW'
                )
                .
                PHP_EOL;


            if ($failures === []) {

                echo
                    'No failed payroll-report deliveries are eligible for retry.'
                    .
                    PHP_EOL;


                return 0;
            }


            echo
                PHP_EOL
                .
                str_pad(
                    'ID',
                    8
                )
                .
                str_pad(
                    'Type',
                    22
                )
                .
                str_pad(
                    'Attempt',
                    14
                )
                .
                str_pad(
                    'Schedule',
                    12
                )
                .
                str_pad(
                    'Completed',
                    22
                )
                .
                'Subject'
                .
                PHP_EOL;


            echo
                str_repeat(
                    '-',
                    110
                )
                .
                PHP_EOL;


            foreach ($failures as $failure) {

                $attemptNumber =
                    (int)(
                        $failure['attempt_number']
                        ??
                        0
                    );


                $maxAttempts =
                    (int)(
                        $failure['max_attempts']
                        ??
                        0
                    );


                $scheduleId =
                    $failure['schedule_id']
                    ??
                    null;


                echo
                    str_pad(
                        (string)(
                            $failure['id']
                            ??
                            ''
                        ),
                        8
                    )
                    .
                    str_pad(
                        (string)(
                            $failure['notification_type']
                            ??
                            ''
                        ),
                        22
                    )
                    .
                    str_pad(
                        (
                            $attemptNumber
                            +
                            1
                        )
                        .
                        '/'
                        .
                        $maxAttempts,
                        14
                    )
                    .
                    str_pad(
                        $scheduleId === null
                            ? '-'
                            : (string)$scheduleId,
                        12
                    )
                    .
                    str_pad(
                        (string)(
                            $failure['completed_at']
                            ??
                            ''
                        ),
                        22
                    )
                    .
                    (string)(
                        $failure['subject']
                        ??
                        ''
                    )
                    .
                    PHP_EOL;
            }


            if (!$send) {

                echo
                    PHP_EOL
                    .
                    'Preview only. No email deliveries were retried.'
                    .
                    PHP_EOL;


                echo
                    'To retry these deliveries, run:'
                    .
                    PHP_EOL;


                echo
                    '  ./iqwurks mail:retry --send --limit='
                    .
                    $limit
                    .
                    PHP_EOL;


                return 0;
            }


            echo
                PHP_EOL
                .
                'Retrying eligible email deliveries...'
                .
                PHP_EOL;


            $sentCount = 0;

            $failedCount = 0;


            foreach ($failures as $failure) {

                $attemptId =
                    (int)(
                        $failure['id']
                        ??
                        0
                    );


                try {

                    echo
                        'Retrying delivery attempt '
                        .
                        $attemptId
                        .
                        '...'
                        .
                        PHP_EOL;


                    $sent =
                        $this->execution
                            ->retry(
                                $failure
                            );


                    if (!$sent) {

                        $failedCount++;


                        fwrite(
                            STDERR,
                            'Delivery attempt '
                            .
                            $attemptId
                            .
                            ' returned a failure result.'
                            .
                            PHP_EOL
                        );


                        continue;
                    }


                    $sentCount++;


                    echo
                        'Delivery attempt '
                        .
                        $attemptId
                        .
                        ' was retried successfully.'
                        .
                        PHP_EOL;

                } catch (Throwable $exception) {

                    $failedCount++;


                    fwrite(
                        STDERR,
                        'Delivery attempt '
                        .
                        $attemptId
                        .
                        ' could not be retried: '
                        .
                        $exception->getMessage()
                        .
                        PHP_EOL
                    );
                }
            }


            echo
                PHP_EOL
                .
                'Successful retries: '
                .
                $sentCount
                .
                PHP_EOL;


            echo
                'Failed retries: '
                .
                $failedCount
                .
                PHP_EOL;


            if ($failedCount > 0) {

                return 1;
            }


            echo
                'Email delivery retries completed successfully.'
                .
                PHP_EOL;


            return 0;

        } catch (InvalidArgumentException $exception) {

            fwrite(
                STDERR,
                'Email delivery retry failed: '
                .
                $exception->getMessage()
                .
                PHP_EOL
            );


            return 1;

        } catch (Throwable $exception) {

            fwrite(
                STDERR,
                'Email delivery retry failed unexpectedly: '
                .
                $exception->getMessage()
                .
                PHP_EOL
            );


            return 1;
        }
    }


    /**
     * @param array<int,mixed> $arguments
     *
     * @return array{
     *     send:bool,
     *     limit:int
     * }
     */
    private function parseArguments(
        array $arguments
    ): array
    {
        $send = false;

        $limit = 25;


        foreach ($arguments as $argument) {

            $argument =
                trim(
                    (string)$argument
                );


            if ($argument === '--send') {

                $send = true;


                continue;
            }


            if (
                preg_match(
                    '/^--limit=(\d+)$/',
                    $argument,
                    $matches
                )
                ===
                1
            ) {
                $limit =
                    (int)$matches[1];


                continue;
            }


            throw new InvalidArgumentException(
                'Unknown argument: '
                .
                $argument
                .
                '. Supported arguments are --send and --limit=N.'
            );
        }


        if (
            $limit < 1
            ||
            $limit > 250
        ) {
            throw new InvalidArgumentException(
                'The --limit value must be between 1 and 250.'
            );
        }


        return [
            'send' =>
                $send,

            'limit' =>
                $limit
        ];
    }
}
