<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\AppInfo;
use App\Core\Container;
use App\Logging\LoggerInterface;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;

final class OperationalFailureNotificationService
{
    public const NOTIFICATION_TYPE =
        'operational_failures';


    private MailService $mail;

    private LoggerInterface $logger;


    public function __construct()
    {
        $this->mail =
            Container::mailService();


        $this->logger =
            Container::logger(
                'application'
            );
    }


    /**
     * @param array<string,mixed> $details
     */
    public function sendFailure(
        string $source,
        string $summary,
        array $details = []
    ): bool
    {
        $source =
            trim(
                $source
            );


        $summary =
            trim(
                $summary
            );


        $this->validateFailure(
            $source,
            $summary
        );


        $subject =
            $this->buildSubject(
                $source
            );


        $body =
            $this->buildBody(
                $source,
                $summary,
                $details
            );


        $this->logger->warning(
            'Operational failure notification delivery started.',
            [
                'source' =>
                    $source,

                'summary' =>
                    $summary,

                'notification_type' =>
                    self::NOTIFICATION_TYPE
            ]
        );


        try {

            $sent =
                $this->mail->send(
                    $subject,
                    $body,
                    self::NOTIFICATION_TYPE
                );


            if (!$sent) {

                $this->logger->error(
                    'Operational failure notification could not be delivered.',
                    [
                        'source' =>
                            $source,

                        'summary' =>
                            $summary,

                        'notification_type' =>
                            self::NOTIFICATION_TYPE
                    ]
                );


                return false;
            }


            $this->logger->info(
                'Operational failure notification delivered successfully.',
                [
                    'source' =>
                        $source,

                    'summary' =>
                        $summary,

                    'notification_type' =>
                        self::NOTIFICATION_TYPE
                ]
            );


            return true;

        } catch (Throwable $exception) {

            $this->logger->error(
                'Operational failure notification delivery raised an exception.',
                [
                    'source' =>
                        $source,

                    'summary' =>
                        $summary,

                    'notification_type' =>
                        self::NOTIFICATION_TYPE,

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


    private function validateFailure(
        string $source,
        string $summary
    ): void
    {
        if ($source === '') {

            throw new RuntimeException(
                'An operational failure source is required.'
            );
        }


        if ($summary === '') {

            throw new RuntimeException(
                'An operational failure summary is required.'
            );
        }
    }


    private function buildSubject(
        string $source
    ): string
    {
        return
            AppInfo::name()
            .
            ' Operational Failure: '
            .
            $source;
    }


    /**
     * @param array<string,mixed> $details
     */
    private function buildBody(
        string $source,
        string $summary,
        array $details
    ): string
    {
        $timezone =
            $this->timezone();


        $detectedAt =
            new DateTimeImmutable(
                'now',
                $timezone
            );


        $lines = [
            AppInfo::name()
                .
                ' Operational Failure Notification',

            '',

            'An application operation or diagnostic check failed.',

            '',

            'Source: '
                .
                $source,

            'Summary: '
                .
                $summary,

            'Detected At: '
                .
                $detectedAt->format(
                    'Y-m-d H:i:s T'
                ),

            'Timezone: '
                .
                $timezone->getName(),

            'Hostname: '
                .
                $this->hostname(),

            'PHP Version: '
                .
                PHP_VERSION
        ];


        $formattedDetails =
            $this->formatDetails(
                $details
            );


        if ($formattedDetails !== []) {

            $lines[] =
                '';


            $lines[] =
                'Details:';


            foreach ($formattedDetails as $detail) {

                $lines[] =
                    '- '
                    .
                    $detail;
            }
        }


        $lines[] =
            '';


        $lines[] =
            'Review the IQwurksPunch application logs and correct the underlying failure.';


        return implode(
            PHP_EOL,
            $lines
        );
    }


    /**
     * @param array<string,mixed> $details
     *
     * @return array<int,string>
     */
    private function formatDetails(
        array $details
    ): array
    {
        $formatted = [];


        foreach ($details as $key => $value) {

            $label =
                $this->formatLabel(
                    (string)$key
                );


            $formatted[] =
                $label
                .
                ': '
                .
                $this->formatValue(
                    $value
                );
        }


        return $formatted;
    }


    private function formatLabel(
        string $key
    ): string
    {
        $key =
            trim(
                str_replace(
                    [
                        '_',
                        '-'
                    ],
                    ' ',
                    $key
                )
            );


        if ($key === '') {

            return 'Detail';
        }


        return ucwords(
            $key
        );
    }


    private function formatValue(
        mixed $value
    ): string
    {
        if ($value === null) {

            return 'Not recorded';
        }


        if (is_bool($value)) {

            return
                $value
                    ? 'yes'
                    : 'no';
        }


        if (
            is_string(
                $value
            )
            ||
            is_int(
                $value
            )
            ||
            is_float(
                $value
            )
        ) {

            $formatted =
                trim(
                    (string)$value
                );


            return
                $formatted === ''
                    ? '(empty)'
                    : $formatted;
        }


        if (is_array($value)) {

            $encoded =
                json_encode(
                    $value,
                    JSON_UNESCAPED_SLASHES
                    |
                    JSON_UNESCAPED_UNICODE
                );


            return
                $encoded === false
                    ? '[unavailable array details]'
                    : $encoded;
        }


        if (is_object($value)) {

            return
                '[object '
                .
                $value::class
                .
                ']';
        }


        return
            '[unsupported detail value]';
    }


    private function timezone(): DateTimeZone
    {
        $timezoneName =
            trim(
                date_default_timezone_get()
            );


        if ($timezoneName === '') {

            $timezoneName =
                'UTC';
        }


        try {

            return new DateTimeZone(
                $timezoneName
            );

        } catch (Throwable) {

            return new DateTimeZone(
                'UTC'
            );
        }
    }


    private function hostname(): string
    {
        $hostname =
            gethostname();


        if (
            is_string(
                $hostname
            )
            &&
            trim(
                $hostname
            ) !== ''
        ) {

            return trim(
                $hostname
            );
        }


        $systemName =
            php_uname(
                'n'
            );


        if (
            is_string(
                $systemName
            )
            &&
            trim(
                $systemName
            ) !== ''
        ) {

            return trim(
                $systemName
            );
        }


        return 'unknown';
    }
}
