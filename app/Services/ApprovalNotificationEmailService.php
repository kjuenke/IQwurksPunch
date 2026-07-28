<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Container;
use App\Logging\LoggerInterface;
use App\Repositories\CompanySettingsRepository;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;

final class ApprovalNotificationEmailService
{
    private CompanySettingsRepository $companySettings;

    private MailService $mail;

    private LoggerInterface $logger;


    public function __construct()
    {
        $this->companySettings =
            Container::companySettingsRepository();


        $this->mail =
            Container::mailService();


        $this->logger =
            Container::logger(
                'reports'
            );
    }


    /**
     * Send a notification after a payroll period has been approved.
     *
     * The approval transaction must already be complete before this method
     * is called. Email delivery failure does not modify payroll status.
     *
     * @param array<string,mixed> $period
     */
    public function sendApprovedPeriod(
        array $period
    ): bool
    {
        $this->validateApprovedPeriod(
            $period
        );


        $settings =
            $this->companySettings->get()
            ??
            [];


        $companyName =
            trim(
                (string)(
                    $settings['company_name']
                    ??
                    ''
                )
            );


        if ($companyName === '') {

            $companyName =
                'IQwurksPunch';
        }


        $timezone =
            $this->companyTimezone(
                $settings
            );


        $subject =
            $this->buildSubject(
                $companyName,
                $period
            );


        $body =
            $this->buildBody(
                $companyName,
                $timezone,
                $period
            );


        $this->logger->info(
            'Payroll approval notification delivery started.',
            [
                'payroll_period_id' =>
                    (int)(
                        $period['id']
                        ??
                        0
                    ),

                'period_name' =>
                    $period['period_name']
                    ??
                    null,

                'approved_by_user_id' =>
                    $period['approved_by_user_id']
                    ??
                    null,

                'notification_type' =>
                    'approval_notifications'
            ]
        );


        try {

            $sent =
                $this->mail->send(
                    $subject,
                    $body,
                    'approval_notifications'
                );


            if (!$sent) {

                $this->logger->error(
                    'Payroll approval notification returned a failure result.',
                    [
                        'payroll_period_id' =>
                            (int)(
                                $period['id']
                                ??
                                0
                            ),

                        'period_name' =>
                            $period['period_name']
                            ??
                            null,

                        'notification_type' =>
                            'approval_notifications'
                    ]
                );


                return false;
            }


            $this->logger->info(
                'Payroll approval notification delivered successfully.',
                [
                    'payroll_period_id' =>
                        (int)(
                            $period['id']
                            ??
                            0
                        ),

                    'period_name' =>
                        $period['period_name']
                        ??
                        null,

                    'notification_type' =>
                        'approval_notifications'
                ]
            );


            return true;

        } catch (Throwable $exception) {

            $this->logger->error(
                'Payroll approval notification delivery failed.',
                [
                    'payroll_period_id' =>
                        (int)(
                            $period['id']
                            ??
                            0
                        ),

                    'period_name' =>
                        $period['period_name']
                        ??
                        null,

                    'notification_type' =>
                        'approval_notifications',

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


            throw $exception;
        }
    }


    /**
     * @param array<string,mixed> $period
     */
    private function buildSubject(
        string $companyName,
        array $period
    ): string
    {
        $periodName =
            trim(
                (string)(
                    $period['period_name']
                    ??
                    ''
                )
            );


        if ($periodName === '') {

            $periodName =
                'Payroll Period';
        }


        return
            $companyName
            .
            ' Payroll Approved: '
            .
            $periodName;
    }


    /**
     * @param array<string,mixed> $period
     */
    private function buildBody(
        string $companyName,
        DateTimeZone $timezone,
        array $period
    ): string
    {
        $periodId =
            (int)(
                $period['id']
                ??
                0
            );


        $periodName =
            trim(
                (string)(
                    $period['period_name']
                    ??
                    ''
                )
            );


        if ($periodName === '') {

            $periodName =
                'Payroll Period #'
                .
                $periodId;
        }


        $startDate =
            trim(
                (string)(
                    $period['start_date']
                    ??
                    ''
                )
            );


        $endDate =
            trim(
                (string)(
                    $period['end_date']
                    ??
                    ''
                )
            );


        $approvedBy =
            trim(
                (string)(
                    $period['approved_by_username']
                    ??
                    ''
                )
            );


        if ($approvedBy === '') {

            $approvedByUserId =
                (int)(
                    $period['approved_by_user_id']
                    ??
                    0
                );


            $approvedBy =
                $approvedByUserId > 0
                    ? 'User #'
                        .
                        $approvedByUserId
                    : 'Unknown user';
        }


        $approvedAt =
            $this->formatWorkflowTimestamp(
                (string)(
                    $period['approved_at']
                    ??
                    ''
                ),
                $timezone
            );


        $reviewedBy =
            trim(
                (string)(
                    $period['reviewed_by_username']
                    ??
                    ''
                )
            );


        if ($reviewedBy === '') {

            $reviewedByUserId =
                (int)(
                    $period['reviewed_by_user_id']
                    ??
                    0
                );


            $reviewedBy =
                $reviewedByUserId > 0
                    ? 'User #'
                        .
                        $reviewedByUserId
                    : 'Not recorded';
        }


        $reviewStartedAt =
            $this->formatOptionalWorkflowTimestamp(
                (string)(
                    $period['review_started_at']
                    ??
                    ''
                ),
                $timezone
            );


        $generatedAt =
            new DateTimeImmutable(
                'now',
                $timezone
            );


        $lines = [
            $companyName
                .
                ' Payroll Approval Notification',

            '',

            'A payroll period has been approved successfully.',

            '',

            'Payroll Period ID: '
                .
                $periodId,

            'Payroll Period: '
                .
                $periodName,

            'Date Range: '
                .
                $startDate
                .
                ' through '
                .
                $endDate,

            'Status: Approved',

            '',

            'Approved By: '
                .
                $approvedBy,

            'Approved At: '
                .
                $approvedAt,

            '',

            'Review Started By: '
                .
                $reviewedBy,

            'Review Started At: '
                .
                $reviewStartedAt,

            '',

            'Generated: '
                .
                $generatedAt->format(
                    'Y-m-d H:i:s T'
                ),

            'Company Timezone: '
                .
                $timezone->getName(),

            '',

            'This notification confirms approval only. The payroll period is not final until it is locked in IQwurksPunch.'
        ];


        return
            implode(
                "\n",
                $lines
            )
            .
            "\n";
    }


    /**
     * @param array<string,mixed> $period
     */
    private function validateApprovedPeriod(
        array $period
    ): void
    {
        $periodId =
            (int)(
                $period['id']
                ??
                0
            );


        if ($periodId < 1) {

            throw new RuntimeException(
                'A valid payroll period is required for an approval notification.'
            );
        }


        $status =
            trim(
                (string)(
                    $period['status']
                    ??
                    ''
                )
            );


        if ($status !== 'approved') {

            throw new RuntimeException(
                'An approval notification may be sent only for an approved payroll period.'
            );
        }


        if (
            empty(
                $period['approved_by_user_id']
            )
            ||
            trim(
                (string)(
                    $period['approved_at']
                    ??
                    ''
                )
            ) === ''
        ) {

            throw new RuntimeException(
                'The approved payroll period does not contain complete approval metadata.'
            );
        }


        if (
            trim(
                (string)(
                    $period['start_date']
                    ??
                    ''
                )
            ) === ''
            ||
            trim(
                (string)(
                    $period['end_date']
                    ??
                    ''
                )
            ) === ''
        ) {

            throw new RuntimeException(
                'The approved payroll period does not contain a complete date range.'
            );
        }
    }


    /**
     * @param array<string,mixed> $settings
     */
    private function companyTimezone(
        array $settings
    ): DateTimeZone
    {
        $timezoneName =
            trim(
                (string)(
                    $settings['timezone']
                    ??
                    ''
                )
            );


        if ($timezoneName === '') {

            $timezoneName =
                date_default_timezone_get();
        }


        try {

            return
                new DateTimeZone(
                    $timezoneName
                );

        } catch (Throwable) {

            return
                new DateTimeZone(
                    date_default_timezone_get()
                );
        }
    }


    private function formatWorkflowTimestamp(
        string $value,
        DateTimeZone $timezone
    ): string
    {
        $formatted =
            $this->formatOptionalWorkflowTimestamp(
                $value,
                $timezone
            );


        return
            $formatted === 'Not recorded'
                ? 'Unknown'
                : $formatted;
    }


    private function formatOptionalWorkflowTimestamp(
        string $value,
        DateTimeZone $timezone
    ): string
    {
        $value =
            trim(
                $value
            );


        if ($value === '') {

            return 'Not recorded';
        }


        $utc =
            new DateTimeZone(
                'UTC'
            );


        $timestamp =
            DateTimeImmutable::createFromFormat(
                '!Y-m-d H:i:s',
                $value,
                $utc
            );


        if (!$timestamp) {

            try {

                $timestamp =
                    new DateTimeImmutable(
                        $value,
                        $utc
                    );

            } catch (Throwable) {

                return $value;
            }
        }


        return
            $timestamp
                ->setTimezone(
                    $timezone
                )
                ->format(
                    'Y-m-d H:i:s T'
                );
    }
}
