<?php
declare(strict_types=1);

use App\Services\ExceptionReportRetryClosureService;
use PHPUnit\Framework\TestCase;

final class ExceptionReportRetryClosureServiceTest extends TestCase
{
    public function testOpenExceptionsDelegateToNormalReportSender(): void
    {
        $reportCalls = [];

        $mailCalls = [];


        $service =
            new ExceptionReportRetryClosureService(
                static fn (): array => [
                    'period_count' =>
                        1,

                    'exception_count' =>
                        2,

                    'periods' =>
                        []
                ],

                static function (
                    string $source,
                    ?int $scheduleId,
                    int $attemptNumber,
                    int $maxAttempts,
                    int $retryOfId
                ) use (
                    &$reportCalls
                ): bool {
                    $reportCalls[] = [
                        'source' =>
                            $source,

                        'schedule_id' =>
                            $scheduleId,

                        'attempt_number' =>
                            $attemptNumber,

                        'max_attempts' =>
                            $maxAttempts,

                        'retry_of_id' =>
                            $retryOfId
                    ];


                    return true;
                },

                static function (
                    mixed ...$arguments
                ) use (
                    &$mailCalls
                ): bool {
                    $mailCalls[] =
                        $arguments;


                    return true;
                },

                static fn (): array => [],

                static fn (
                    DateTimeZone $timezone
                ): DateTimeImmutable =>
                    new DateTimeImmutable(
                        '2026-07-30 10:00:00',
                        $timezone
                    )
            );


        self::assertTrue(
            $service->sendRetry(
                'retry',
                3,
                2,
                3,
                71
            )
        );


        self::assertCount(
            1,
            $reportCalls
        );


        self::assertSame(
            [
                'source' =>
                    'retry',

                'schedule_id' =>
                    3,

                'attempt_number' =>
                    2,

                'max_attempts' =>
                    3,

                'retry_of_id' =>
                    71
            ],
            $reportCalls[0]
        );


        self::assertSame(
            [],
            $mailCalls
        );
    }


    public function testResolvedExceptionsSendClosureEmailWithRetryMetadata(): void
    {
        $reportSenderCalled = false;

        $mailCalls = [];


        $service =
            new ExceptionReportRetryClosureService(
                static fn (): array => [
                    'period_count' =>
                        0,

                    'exception_count' =>
                        0,

                    'periods' =>
                        []
                ],

                static function (
                    mixed ...$arguments
                ) use (
                    &$reportSenderCalled
                ): bool {
                    $reportSenderCalled = true;


                    return true;
                },

                static function (
                    string $subject,
                    string $body,
                    string $notificationType,
                    array $attachments,
                    string $source,
                    ?int $scheduleId,
                    int $attemptNumber,
                    int $maxAttempts,
                    ?int $retryOfId
                ) use (
                    &$mailCalls
                ): bool {
                    $mailCalls[] = [
                        'subject' =>
                            $subject,

                        'body' =>
                            $body,

                        'notification_type' =>
                            $notificationType,

                        'attachments' =>
                            $attachments,

                        'source' =>
                            $source,

                        'schedule_id' =>
                            $scheduleId,

                        'attempt_number' =>
                            $attemptNumber,

                        'max_attempts' =>
                            $maxAttempts,

                        'retry_of_id' =>
                            $retryOfId
                    ];


                    return true;
                },

                static fn (): array => [
                    'company_name' =>
                        'RFE International, Inc.',

                    'timezone' =>
                        'America/Los_Angeles'
                ],

                static fn (
                    DateTimeZone $timezone
                ): DateTimeImmutable =>
                    new DateTimeImmutable(
                        '2026-07-30 10:15:00',
                        $timezone
                    )
            );


        self::assertTrue(
            $service->sendRetry(
                'retry',
                3,
                2,
                3,
                72
            )
        );


        self::assertFalse(
            $reportSenderCalled
        );


        self::assertCount(
            1,
            $mailCalls
        );


        $mail =
            $mailCalls[0];


        self::assertSame(
            'RFE International, Inc. Payroll Exception Report Retry: No Open Exceptions',
            $mail['subject']
        );


        self::assertSame(
            'exception_reports',
            $mail['notification_type']
        );


        self::assertSame(
            [],
            $mail['attachments']
        );


        self::assertSame(
            'retry',
            $mail['source']
        );


        self::assertSame(
            3,
            $mail['schedule_id']
        );


        self::assertSame(
            2,
            $mail['attempt_number']
        );


        self::assertSame(
            3,
            $mail['max_attempts']
        );


        self::assertSame(
            72,
            $mail['retry_of_id']
        );


        self::assertStringContainsString(
            'Generated: 2026-07-30 10:15:00 PDT',
            $mail['body']
        );


        self::assertStringContainsString(
            'Original Failed Attempt ID: 72',
            $mail['body']
        );


        self::assertStringContainsString(
            'Retry Attempt: 2 of 3',
            $mail['body']
        );


        self::assertStringContainsString(
            'found no open exceptions',
            $mail['body']
        );


        self::assertStringContainsString(
            "closes the failed delivery's retry chain successfully",
            $mail['body']
        );
    }


    public function testClosureMailFailureIsReturned(): void
    {
        $service =
            new ExceptionReportRetryClosureService(
                static fn (): array => [
                    'exception_count' =>
                        0
                ],

                static fn (
                    mixed ...$arguments
                ): bool =>
                    true,

                static fn (
                    mixed ...$arguments
                ): bool =>
                    false,

                static fn (): array => [],

                static fn (
                    DateTimeZone $timezone
                ): DateTimeImmutable =>
                    new DateTimeImmutable(
                        '2026-07-30 10:00:00',
                        $timezone
                    )
            );


        self::assertFalse(
            $service->sendRetry(
                'retry',
                null,
                2,
                3,
                73
            )
        );
    }


    public function testNonRetrySourceIsRejected(): void
    {
        $service =
            new ExceptionReportRetryClosureService(
                static fn (): array => [
                    'exception_count' =>
                        0
                ],

                static fn (
                    mixed ...$arguments
                ): bool =>
                    true,

                static fn (
                    mixed ...$arguments
                ): bool =>
                    true,

                static fn (): array => []
            );


        $this->expectException(
            InvalidArgumentException::class
        );


        $this->expectExceptionMessage(
            'Exception-report retry closure delivery requires the retry source.'
        );


        $service->sendRetry(
            'scheduled',
            3,
            2,
            3,
            74
        );
    }


    public function testInvalidReportDataIsRejected(): void
    {
        $service =
            new ExceptionReportRetryClosureService(
                static fn (): array => [
                    'exception_count' =>
                        'unknown'
                ],

                static fn (
                    mixed ...$arguments
                ): bool =>
                    true,

                static fn (
                    mixed ...$arguments
                ): bool =>
                    true,

                static fn (): array => []
            );


        $this->expectException(
            RuntimeException::class
        );


        $this->expectExceptionMessage(
            'Exception-report retry data contains an invalid exception count.'
        );


        $service->sendRetry(
            'retry',
            3,
            2,
            3,
            75
        );
    }
}
