<?php
declare(strict_types=1);

namespace App\Services;

use Closure;
use RuntimeException;

final class EmailDeliveryRetryExecutionService
{
    private EmailDeliveryRetryPlanService $plans;

    private Closure $dailySender;

    private Closure $weeklySender;

    private Closure $exceptionSender;


    public function __construct(
        ?EmailDeliveryRetryPlanService $plans = null,
        ?callable $dailySender = null,
        ?callable $weeklySender = null,
        ?callable $exceptionSender = null
    )
    {
        $this->plans =
            $plans
            ??
            new EmailDeliveryRetryPlanService();


        if ($dailySender === null) {

            $dailyReports =
                new ReportEmailService();


            $dailySender = [
                $dailyReports,
                'sendDailyPayrollReport'
            ];
        }


        if ($weeklySender === null) {

            $weeklyReports =
                new WeeklyPayrollEmailService();


            $weeklySender = [
                $weeklyReports,
                'sendWeeklyPayrollReport'
            ];
        }


        if ($exceptionSender === null) {

            $exceptionReports =
                new ExceptionReportEmailService();


            $exceptionSender = [
                $exceptionReports,
                'sendOpenExceptionReport'
            ];
        }


        $this->dailySender =
            Closure::fromCallable(
                $dailySender
            );


        $this->weeklySender =
            Closure::fromCallable(
                $weeklySender
            );


        $this->exceptionSender =
            Closure::fromCallable(
                $exceptionSender
            );
    }


    /**
     * @param array<string,mixed> $failedAttempt
     */
    public function retry(
        array $failedAttempt
    ): bool
    {
        $plan =
            $this->plans
                ->plan(
                    $failedAttempt
                );


        $notificationType =
            (string)$plan['notification_type'];


        if (
            $notificationType
            ===
            EmailDeliveryRetryPlanService::DAILY_PAYROLL
        ) {
            $reportDate =
                $this->requiredPlanDate(
                    $plan['report_date']
                    ??
                    null,
                    'Daily payroll report date'
                );


            return
                (bool)(
                    $this->dailySender
                )(
                    (string)$plan['source'],
                    $plan['schedule_id'],
                    (int)$plan['attempt_number'],
                    (int)$plan['max_attempts'],
                    (int)$plan['retry_of_id'],
                    $reportDate
                );
        }


        if (
            $notificationType
            ===
            EmailDeliveryRetryPlanService::WEEKLY_PAYROLL
        ) {
            $referenceDate =
                $this->requiredPlanDate(
                    $plan['reference_date']
                    ??
                    null,
                    'Weekly payroll reference date'
                );


            return
                (bool)(
                    $this->weeklySender
                )(
                    $referenceDate,
                    (string)$plan['source'],
                    $plan['schedule_id'],
                    (int)$plan['attempt_number'],
                    (int)$plan['max_attempts'],
                    (int)$plan['retry_of_id']
                );
        }


        if (
            $notificationType
            ===
            EmailDeliveryRetryPlanService::EXCEPTION_REPORTS
        ) {
            return
                (bool)(
                    $this->exceptionSender
                )(
                    (string)$plan['source'],
                    $plan['schedule_id'],
                    (int)$plan['attempt_number'],
                    (int)$plan['max_attempts'],
                    (int)$plan['retry_of_id']
                );
        }


        throw new RuntimeException(
            'The retry plan contains an unsupported notification type.'
        );
    }


    private function requiredPlanDate(
        mixed $value,
        string $label
    ): string
    {
        if (!is_string($value)) {
            throw new RuntimeException(
                $label
                .
                ' is missing from the retry plan.'
            );
        }


        $value =
            trim(
                $value
            );


        if ($value === '') {
            throw new RuntimeException(
                $label
                .
                ' is missing from the retry plan.'
            );
        }


        return $value;
    }
}
