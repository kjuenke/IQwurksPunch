<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Container;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;

final class ExceptionReportRetryClosureService
{
    private Closure $reportDataProvider;

    private Closure $exceptionReportSender;

    private Closure $mailSender;

    private Closure $companyProvider;

    private Closure $clock;


    public function __construct(
        ?callable $reportDataProvider = null,
        ?callable $exceptionReportSender = null,
        ?callable $mailSender = null,
        ?callable $companyProvider = null,
        ?callable $clock = null
    )
    {
        if (
            $reportDataProvider === null
            ||
            $exceptionReportSender === null
        ) {
            $exceptionReports =
                new ExceptionReportEmailService();


            $reportDataProvider =
                $reportDataProvider
                ??
                [
                    $exceptionReports,
                    'openExceptionReportData'
                ];


            $exceptionReportSender =
                $exceptionReportSender
                ??
                [
                    $exceptionReports,
                    'sendOpenExceptionReport'
                ];
        }


        if ($mailSender === null) {

            $mail =
                Container::mailService();


            $mailSender = [
                $mail,
                'send'
            ];
        }


        if ($companyProvider === null) {

            $settings =
                Container::companySettingsRepository();


            $companyProvider =
                static fn (): ?array =>
                    $settings->get();
        }


        $clock =
            $clock
            ??
            static fn (
                DateTimeZone $timezone
            ): DateTimeImmutable =>
                new DateTimeImmutable(
                    'now',
                    $timezone
                );


        $this->reportDataProvider =
            Closure::fromCallable(
                $reportDataProvider
            );


        $this->exceptionReportSender =
            Closure::fromCallable(
                $exceptionReportSender
            );


        $this->mailSender =
            Closure::fromCallable(
                $mailSender
            );


        $this->companyProvider =
            Closure::fromCallable(
                $companyProvider
            );


        $this->clock =
            Closure::fromCallable(
                $clock
            );
    }


    public function sendRetry(
        string $source,
        ?int $scheduleId,
        int $attemptNumber,
        int $maxAttempts,
        int $retryOfId
    ): bool
    {
        if ($source !== 'retry') {
            throw new InvalidArgumentException(
                'Exception-report retry closure delivery requires the retry source.'
            );
        }


        $report =
            (
                $this->reportDataProvider
            )();


        if (!is_array($report)) {
            throw new RuntimeException(
                'Exception-report retry data must be an array.'
            );
        }


        $exceptionCount =
            filter_var(
                $report['exception_count']
                ??
                null,
                FILTER_VALIDATE_INT
            );


        if (
            $exceptionCount === false
            ||
            $exceptionCount < 0
        ) {
            throw new RuntimeException(
                'Exception-report retry data contains an invalid exception count.'
            );
        }


        if ($exceptionCount > 0) {

            return
                (bool)(
                    $this->exceptionReportSender
                )(
                    $source,
                    $scheduleId,
                    $attemptNumber,
                    $maxAttempts,
                    $retryOfId
                );
        }


        $company =
            (
                $this->companyProvider
            )();


        if (!is_array($company)) {
            $company = [];
        }


        $companyName =
            trim(
                (string)(
                    $company['company_name']
                    ??
                    ''
                )
            );


        if ($companyName === '') {
            $companyName =
                'IQwurksPunch';
        }


        $timezoneName =
            trim(
                (string)(
                    $company['timezone']
                    ??
                    'America/Los_Angeles'
                )
            );


        if (
            !in_array(
                $timezoneName,
                timezone_identifiers_list(),
                true
            )
        ) {
            $timezoneName =
                'America/Los_Angeles';
        }


        $timezone =
            new DateTimeZone(
                $timezoneName
            );


        $generatedAt =
            (
                $this->clock
            )(
                $timezone
            );


        if (!$generatedAt instanceof DateTimeImmutable) {
            throw new RuntimeException(
                'Exception-report retry clock must return a DateTimeImmutable value.'
            );
        }


        $generatedAt =
            $generatedAt->setTimezone(
                $timezone
            );


        $subject =
            $companyName
            .
            ' Payroll Exception Report Retry: No Open Exceptions';


        $body =
            $this->closureBody(
                $companyName,
                $timezoneName,
                $generatedAt,
                $retryOfId,
                $attemptNumber,
                $maxAttempts
            );


        return
            (bool)(
                $this->mailSender
            )(
                $subject,
                $body,
                'exception_reports',
                [],
                $source,
                $scheduleId,
                $attemptNumber,
                $maxAttempts,
                $retryOfId
            );
    }


    private function closureBody(
        string $companyName,
        string $timezoneName,
        DateTimeImmutable $generatedAt,
        int $retryOfId,
        int $attemptNumber,
        int $maxAttempts
    ): string
    {
        return
            $companyName
            .
            " Payroll Exception Report Retry\n"
            .
            str_repeat(
                '=',
                72
            )
            .
            "\n\n"
            .
            'Generated: '
            .
            $generatedAt->format(
                'Y-m-d H:i:s T'
            )
            .
            "\n"
            .
            'Company Timezone: '
            .
            $timezoneName
            .
            "\n"
            .
            'Original Failed Attempt ID: '
            .
            $retryOfId
            .
            "\n"
            .
            'Retry Attempt: '
            .
            $attemptNumber
            .
            ' of '
            .
            $maxAttempts
            .
            "\n\n"
            .
            "The original payroll exception report delivery failed. During\n"
            .
            "this retry, IQwurksPunch found no open exceptions in payroll\n"
            .
            "periods whose workflow status is Open or Under Review.\n\n"
            .
            "No exception details remain to deliver. This status message\n"
            .
            "closes the failed delivery's retry chain successfully.\n";
    }
}
