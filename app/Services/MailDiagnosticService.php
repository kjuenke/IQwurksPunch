<?php
declare(strict_types=1);

namespace App\Services;

use App\Logging\LoggerInterface;
use PDO;
use RuntimeException;
use Symfony\Component\Mailer\Transport;
use Throwable;

final class MailDiagnosticService
{
    private const PASS = 'PASS';

    private const WARN = 'WARN';

    private const FAIL = 'FAIL';


    private string $projectRoot;

    private LoggerInterface $logger;

    private ?PDO $database;


    public function __construct(
        string $projectRoot,
        LoggerInterface $logger,
        ?PDO $database = null
    )
    {
        $this->projectRoot =
            rtrim(
                $projectRoot,
                DIRECTORY_SEPARATOR
            );


        $this->logger =
            $logger;


        $this->database =
            $database;
    }


    /**
     * @return array<string,mixed>
     */
    public function diagnose(): array
    {
        $startedAt =
            microtime(
                true
            );


        $checks = [];


        $configuration =
            $this->checkConfiguration(
                $checks
            );


        $this->checkConfigurationPermissions(
            $checks
        );


        if ($configuration !== null) {

            $this->checkEncryption(
                $checks,
                $configuration
            );


            $this->checkHostnameResolution(
                $checks,
                $configuration
            );


            $this->checkSymfonyTransport(
                $checks,
                $configuration
            );


            $this->checkSmtpConnection(
                $checks,
                $configuration
            );
        }


        $this->checkRecipients(
            $checks
        );


        $this->checkDeliveryHistory(
            $checks
        );


        $this->checkMailLog(
            $checks
        );


        $passCount =
            count(
                array_filter(
                    $checks,
                    static fn (
                        array $check
                    ): bool =>
                        $check['status']
                        ===
                        self::PASS
                )
            );


        $warningCount =
            count(
                array_filter(
                    $checks,
                    static fn (
                        array $check
                    ): bool =>
                        $check['status']
                        ===
                        self::WARN
                )
            );


        $failureCount =
            count(
                array_filter(
                    $checks,
                    static fn (
                        array $check
                    ): bool =>
                        $check['status']
                        ===
                        self::FAIL
                )
            );


        $overallStatus =
            $failureCount > 0
                ? self::FAIL
                : (
                    $warningCount > 0
                        ? self::WARN
                        : self::PASS
                );


        $result = [
            'overall_status' =>
                $overallStatus,

            'checks' =>
                $checks,

            'pass_count' =>
                $passCount,

            'warning_count' =>
                $warningCount,

            'failure_count' =>
                $failureCount,

            'total_count' =>
                count(
                    $checks
                ),

            'checked_at' =>
                date(
                    'Y-m-d H:i:s T'
                ),

            'duration_milliseconds' =>
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
                )
        ];


        $context = [
            'overall_status' =>
                $overallStatus,

            'pass_count' =>
                $passCount,

            'warning_count' =>
                $warningCount,

            'failure_count' =>
                $failureCount,

            'total_count' =>
                count(
                    $checks
                ),

            'duration_milliseconds' =>
                $result['duration_milliseconds']
        ];


        if ($failureCount > 0) {

            $this->logger->error(
                'Mail diagnostic detected one or more failures.',
                $context
            );

        } elseif ($warningCount > 0) {

            $this->logger->warning(
                'Mail diagnostic completed with warnings.',
                $context
            );

        } else {

            $this->logger->info(
                'Mail diagnostic completed successfully.',
                $context
            );
        }


        return $result;
    }


    /**
     * @param array<int,array<string,mixed>> $checks
     *
     * @return array<string,mixed>|null
     */
    private function checkConfiguration(
        array &$checks
    ): ?array
    {
        $path =
            $this->projectRoot
            .
            '/config/mail.php';


        if (
            !is_file(
                $path
            )
        ) {
            $this->addCheck(
                $checks,
                self::FAIL,
                'SMTP configuration',
                'config/mail.php does not exist.'
            );


            return null;
        }


        if (
            !is_readable(
                $path
            )
        ) {
            $this->addCheck(
                $checks,
                self::FAIL,
                'SMTP configuration',
                'config/mail.php is not readable.'
            );


            return null;
        }


        try {

            $configuration =
                require $path;


            if (!is_array($configuration)) {

                throw new RuntimeException(
                    'The mail configuration did not return an array.'
                );
            }


            $required = [
                'host',
                'port',
                'username',
                'password',
                'encryption',
                'from_email',
                'from_name',
            ];


            $problems = [];


            foreach ($required as $key) {

                if (
                    !array_key_exists(
                        $key,
                        $configuration
                    )
                    ||
                    trim(
                        (string)$configuration[$key]
                    )
                    ===
                    ''
                ) {
                    $problems[] =
                        'Missing or empty setting: '
                        .
                        $key;
                }
            }


            $port =
                (int)(
                    $configuration['port']
                    ??
                    0
                );


            if (
                $port < 1
                ||
                $port > 65535
            ) {
                $problems[] =
                    'SMTP port must be between 1 and 65535.';
            }


            $fromEmail =
                trim(
                    (string)(
                        $configuration['from_email']
                        ??
                        ''
                    )
                );


            if (
                $fromEmail !== ''
                &&
                filter_var(
                    $fromEmail,
                    FILTER_VALIDATE_EMAIL
                )
                ===
                false
            ) {
                $problems[] =
                    'The sender email address is invalid.';
            }


            $placeholders = [
                'smtp.example.com',
                'SMTP_USERNAME',
                'SMTP_PASSWORD',
                'timeclock@example.com',
            ];


            foreach ($configuration as $value) {

                if (
                    in_array(
                        trim(
                            (string)$value
                        ),
                        $placeholders,
                        true
                    )
                ) {
                    $problems[] =
                        'The active configuration contains example placeholder values.';


                    break;
                }
            }


            $details = [
                'Host: '
                .
                (
                    $configuration['host']
                    ??
                    '(empty)'
                ),

                'Port: '
                .
                $port,

                'Encryption: '
                .
                (
                    $configuration['encryption']
                    ??
                    '(empty)'
                ),

                'Sender: '
                .
                (
                    $configuration['from_email']
                    ??
                    '(empty)'
                ),

                'Credentials present: '
                .
                (
                    trim(
                        (string)(
                            $configuration['username']
                            ??
                            ''
                        )
                    )
                    !==
                    ''
                    &&
                    trim(
                        (string)(
                            $configuration['password']
                            ??
                            ''
                        )
                    )
                    !==
                    ''
                        ? 'yes'
                        : 'no'
                )
            ];


            if ($problems !== []) {

                $this->addCheck(
                    $checks,
                    self::FAIL,
                    'SMTP configuration',
                    'The SMTP configuration is incomplete or invalid.',
                    [
                        ...$problems,
                        ...$details
                    ]
                );

            } else {

                $this->addCheck(
                    $checks,
                    self::PASS,
                    'SMTP configuration',
                    'The SMTP configuration is structurally valid.',
                    $details
                );
            }


            return $configuration;

        } catch (Throwable $exception) {

            $this->addCheck(
                $checks,
                self::FAIL,
                'SMTP configuration',
                'config/mail.php could not be loaded.',
                [
                    $this->sanitize(
                        $exception->getMessage(),
                        []
                    )
                ]
            );


            return null;
        }
    }


    /**
     * @param array<int,array<string,mixed>> $checks
     */
    private function checkConfigurationPermissions(
        array &$checks
    ): void
    {
        $path =
            $this->projectRoot
            .
            '/config/mail.php';


        if (
            !is_file(
                $path
            )
        ) {
            return;
        }


        $permissions =
            fileperms(
                $path
            );


        if ($permissions === false) {

            $this->addCheck(
                $checks,
                self::WARN,
                'Credential permissions',
                'The permissions for config/mail.php could not be read.'
            );


            return;
        }


        $mode =
            sprintf(
                '%04o',
                $permissions
                &
                0777
            );


        $otherAccess =
            (
                $permissions
                &
                0007
            )
            !==
            0;


        $groupWrite =
            (
                $permissions
                &
                0020
            )
            !==
            0;


        if (
            $otherAccess
            ||
            $groupWrite
        ) {
            $this->addCheck(
                $checks,
                self::WARN,
                'Credential permissions',
                'SMTP credentials are accessible more broadly than recommended.',
                [
                    'Current mode: '
                    .
                    $mode,

                    'Recommended mode: 0600 or 0640'
                ]
            );


            return;
        }


        $this->addCheck(
            $checks,
            self::PASS,
            'Credential permissions',
            'SMTP credential-file permissions are appropriately restricted.',
            [
                'Current mode: '
                .
                $mode
            ]
        );
    }


    /**
     * @param array<int,array<string,mixed>> $checks
     * @param array<string,mixed> $configuration
     */
    private function checkEncryption(
        array &$checks,
        array $configuration
    ): void
    {
        $encryption =
            strtolower(
                trim(
                    (string)(
                        $configuration['encryption']
                        ??
                        ''
                    )
                )
            );


        $supported = [
            'tls',
            'starttls',
            'ssl',
            'smtps',
            'none',
        ];


        if (
            !in_array(
                $encryption,
                $supported,
                true
            )
        ) {
            $this->addCheck(
                $checks,
                self::FAIL,
                'Encryption setting',
                'The configured SMTP encryption mode is unsupported.',
                [
                    'Configured: '
                    .
                    (
                        $encryption === ''
                            ? '(empty)'
                            : $encryption
                    ),

                    'Supported: tls, starttls, ssl, smtps, none'
                ]
            );


            return;
        }


        if ($encryption === 'none') {

            $this->addCheck(
                $checks,
                self::WARN,
                'Encryption setting',
                'SMTP transport encryption is disabled.'
            );


            return;
        }


        $this->addCheck(
            $checks,
            self::PASS,
            'Encryption setting',
            'SMTP encryption is configured.',
            [
                'Mode: '
                .
                $encryption
            ]
        );
    }


    /**
     * @param array<int,array<string,mixed>> $checks
     * @param array<string,mixed> $configuration
     */
    private function checkHostnameResolution(
        array &$checks,
        array $configuration
    ): void
    {
        $host =
            trim(
                (string)(
                    $configuration['host']
                    ??
                    ''
                )
            );


        if ($host === '') {

            $this->addCheck(
                $checks,
                self::FAIL,
                'Hostname resolution',
                'No SMTP hostname is configured.'
            );


            return;
        }


        $addresses = [];


        if (
            function_exists(
                'dns_get_record'
            )
        ) {
            $aRecords =
                @dns_get_record(
                    $host,
                    DNS_A
                );


            $aaaaRecords =
                @dns_get_record(
                    $host,
                    DNS_AAAA
                );


            if (is_array($aRecords)) {

                foreach ($aRecords as $record) {

                    if (
                        isset(
                            $record['ip']
                        )
                    ) {
                        $addresses[] =
                            (string)$record['ip'];
                    }
                }
            }


            if (is_array($aaaaRecords)) {

                foreach ($aaaaRecords as $record) {

                    if (
                        isset(
                            $record['ipv6']
                        )
                    ) {
                        $addresses[] =
                            (string)$record['ipv6'];
                    }
                }
            }
        }


        if ($addresses === []) {

            $fallback =
                gethostbyname(
                    $host
                );


            if ($fallback !== $host) {

                $addresses[] =
                    $fallback;
            }
        }


        $addresses =
            array_values(
                array_unique(
                    $addresses
                )
            );


        if ($addresses === []) {

            $this->addCheck(
                $checks,
                self::FAIL,
                'Hostname resolution',
                'The SMTP hostname could not be resolved.',
                [
                    'Host: '
                    .
                    $host
                ]
            );


            return;
        }


        $this->addCheck(
            $checks,
            self::PASS,
            'Hostname resolution',
            'The SMTP hostname resolved successfully.',
            [
                'Host: '
                .
                $host,

                'Addresses: '
                .
                implode(
                    ', ',
                    $addresses
                )
            ]
        );
    }


    /**
     * @param array<int,array<string,mixed>> $checks
     * @param array<string,mixed> $configuration
     */
    private function checkSymfonyTransport(
        array &$checks,
        array $configuration
    ): void
    {
        try {

            $dsn =
                sprintf(
                    'smtp://%s:%s@%s:%d',
                    rawurlencode(
                        (string)(
                            $configuration['username']
                            ??
                            ''
                        )
                    ),
                    rawurlencode(
                        (string)(
                            $configuration['password']
                            ??
                            ''
                        )
                    ),
                    (string)(
                        $configuration['host']
                        ??
                        ''
                    ),
                    (int)(
                        $configuration['port']
                        ??
                        0
                    )
                );


            $transport =
                Transport::fromDsn(
                    $dsn
                );


            $this->addCheck(
                $checks,
                self::PASS,
                'Mailer transport',
                'Symfony Mailer initialized the configured SMTP transport.',
                [
                    'Transport class: '
                    .
                    $transport::class,

                    'No authentication or message transmission was attempted.'
                ]
            );

        } catch (Throwable $exception) {

            $this->addCheck(
                $checks,
                self::FAIL,
                'Mailer transport',
                'Symfony Mailer could not initialize the SMTP transport.',
                [
                    $this->sanitize(
                        $exception->getMessage(),
                        $configuration
                    )
                ]
            );
        }
    }


    /**
     * @param array<int,array<string,mixed>> $checks
     * @param array<string,mixed> $configuration
     */
    private function checkSmtpConnection(
        array &$checks,
        array $configuration
    ): void
    {
        $startedAt =
            microtime(
                true
            );


        $host =
            trim(
                (string)(
                    $configuration['host']
                    ??
                    ''
                )
            );


        $port =
            (int)(
                $configuration['port']
                ??
                0
            );


        $encryption =
            strtolower(
                trim(
                    (string)(
                        $configuration['encryption']
                        ??
                        ''
                    )
                )
            );


        if (
            $host === ''
            ||
            $port < 1
            ||
            $port > 65535
        ) {
            $this->addCheck(
                $checks,
                self::FAIL,
                'SMTP connectivity',
                'SMTP connectivity could not be tested because the endpoint is invalid.'
            );


            return;
        }


        $implicitTls =
            in_array(
                $encryption,
                [
                    'ssl',
                    'smtps',
                ],
                true
            );


        $startTls =
            in_array(
                $encryption,
                [
                    'tls',
                    'starttls',
                ],
                true
            );


        $scheme =
            $implicitTls
                ? 'tls'
                : 'tcp';


        $context =
            stream_context_create(
                [
                    'ssl' => [
                        'verify_peer' =>
                            true,

                        'verify_peer_name' =>
                            true,

                        'peer_name' =>
                            $host,

                        'SNI_enabled' =>
                            true,

                        'capture_peer_cert' =>
                            true,

                        'allow_self_signed' =>
                            false
                    ]
                ]
            );


        $errorNumber = 0;

        $errorMessage = '';


        $stream =
            @stream_socket_client(
                $scheme
                .
                '://'
                .
                $host
                .
                ':'
                .
                $port,
                $errorNumber,
                $errorMessage,
                10,
                STREAM_CLIENT_CONNECT,
                $context
            );


        if ($stream === false) {

            $this->addCheck(
                $checks,
                self::FAIL,
                'SMTP connectivity',
                'A TCP connection to the SMTP server could not be established.',
                [
                    'Endpoint: '
                    .
                    $host
                    .
                    ':'
                    .
                    $port,

                    'Error: '
                    .
                    $this->sanitize(
                        $errorMessage,
                        $configuration
                    ),

                    'Error number: '
                    .
                    $errorNumber
                ]
            );


            return;
        }


        try {

            stream_set_timeout(
                $stream,
                10
            );


            $banner =
                $this->readResponse(
                    $stream
                );


            if (
                $banner['code']
                !==
                220
            ) {
                throw new RuntimeException(
                    'The SMTP server returned unexpected banner code '
                    .
                    $banner['code']
                    .
                    '.'
                );
            }


            $this->writeCommand(
                $stream,
                'EHLO iqwurkspunch.local'
            );


            $ehlo =
                $this->readResponse(
                    $stream
                );


            if (
                $ehlo['code']
                !==
                250
            ) {
                throw new RuntimeException(
                    'The SMTP server rejected EHLO with code '
                    .
                    $ehlo['code']
                    .
                    '.'
                );
            }


            $ehloText =
                strtoupper(
                    implode(
                        "\n",
                        $ehlo['lines']
                    )
                );


            $tlsActive =
                $implicitTls;


            if ($startTls) {

                if (
                    !str_contains(
                        $ehloText,
                        'STARTTLS'
                    )
                ) {
                    throw new RuntimeException(
                        'The SMTP server did not advertise STARTTLS.'
                    );
                }


                $this->writeCommand(
                    $stream,
                    'STARTTLS'
                );


                $startTlsResponse =
                    $this->readResponse(
                        $stream
                    );


                if (
                    $startTlsResponse['code']
                    !==
                    220
                ) {
                    throw new RuntimeException(
                        'The SMTP server rejected STARTTLS with code '
                        .
                        $startTlsResponse['code']
                        .
                        '.'
                    );
                }


                $cryptoEnabled =
                    @stream_socket_enable_crypto(
                        $stream,
                        true,
                        STREAM_CRYPTO_METHOD_TLS_CLIENT
                    );


                if ($cryptoEnabled !== true) {

                    throw new RuntimeException(
                        'TLS negotiation with the SMTP server failed.'
                    );
                }


                $tlsActive =
                    true;


                $this->writeCommand(
                    $stream,
                    'EHLO iqwurkspunch.local'
                );


                $secureEhlo =
                    $this->readResponse(
                        $stream
                    );


                if (
                    $secureEhlo['code']
                    !==
                    250
                ) {
                    throw new RuntimeException(
                        'The SMTP server rejected EHLO after TLS negotiation.'
                    );
                }


                $ehloText =
                    strtoupper(
                        implode(
                            "\n",
                            $secureEhlo['lines']
                        )
                    );
            }


            $authenticationAdvertised =
                str_contains(
                    $ehloText,
                    'AUTH'
                );


            $certificateDetails =
                $this->certificateDetails(
                    $stream
                );


            $this->writeCommand(
                $stream,
                'QUIT'
            );


            try {

                $this->readResponse(
                    $stream
                );

            } catch (Throwable $ignored) {

                // The diagnostic is already complete.
            }


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


            $details = [
                'Endpoint: '
                .
                $host
                .
                ':'
                .
                $port,

                'Banner code: '
                .
                $banner['code'],

                'TLS active: '
                .
                (
                    $tlsActive
                        ? 'yes'
                        : 'no'
                ),

                'Authentication advertised: '
                .
                (
                    $authenticationAdvertised
                        ? 'yes'
                        : 'no'
                ),

                'Authentication attempted: no',

                'Message transmitted: no',

                'Duration: '
                .
                number_format(
                    $durationMilliseconds,
                    2
                )
                .
                ' ms'
            ];


            if ($certificateDetails !== []) {

                $details = [
                    ...$details,
                    ...$certificateDetails
                ];
            }


            if (!$tlsActive) {

                $this->addCheck(
                    $checks,
                    self::WARN,
                    'SMTP connectivity',
                    'The SMTP server responded, but the connection was not encrypted.',
                    $details
                );


                return;
            }


            if (!$authenticationAdvertised) {

                $this->addCheck(
                    $checks,
                    self::WARN,
                    'SMTP connectivity',
                    'TLS negotiation succeeded, but the server did not advertise authentication.',
                    $details
                );


                return;
            }


            $this->addCheck(
                $checks,
                self::PASS,
                'SMTP connectivity',
                'SMTP connectivity and TLS negotiation completed successfully.',
                $details
            );

        } catch (Throwable $exception) {

            $this->addCheck(
                $checks,
                self::FAIL,
                'SMTP connectivity',
                'The SMTP protocol diagnostic failed.',
                [
                    'Endpoint: '
                    .
                    $host
                    .
                    ':'
                    .
                    $port,

                    'Error: '
                    .
                    $this->sanitize(
                        $exception->getMessage(),
                        $configuration
                    ),

                    'Authentication attempted: no',

                    'Message transmitted: no'
                ]
            );

        } finally {

            fclose(
                $stream
            );
        }
    }


    /**
     * @param array<int,array<string,mixed>> $checks
     */
    private function checkRecipients(
        array &$checks
    ): void
    {
        if ($this->database === null) {

            $this->addCheck(
                $checks,
                self::WARN,
                'Notification recipients',
                'Recipient availability could not be checked because the database is unavailable.'
            );


            return;
        }


        try {

            $statement =
                $this->database->query(
                    "
                    SELECT
                        COUNT(*) AS total_active,

                        SUM(
                            CASE
                                WHEN daily_payroll = 1
                                THEN 1
                                ELSE 0
                            END
                        ) AS daily_payroll_count,

                        SUM(
                            CASE
                                WHEN weekly_payroll = 1
                                THEN 1
                                ELSE 0
                            END
                        ) AS weekly_payroll_count,

                        SUM(
                            CASE
                                WHEN exception_reports = 1
                                THEN 1
                                ELSE 0
                            END
                        ) AS exception_reports_count

                    FROM notification_recipients

                    WHERE active = 1
                    "
                );


            if ($statement === false) {

                throw new RuntimeException(
                    'The active-recipient query could not be executed.'
                );
            }


            $counts =
                $statement->fetch();


            if (!is_array($counts)) {

                throw new RuntimeException(
                    'The active-recipient query returned no result.'
                );
            }


            $totalActive =
                (int)(
                    $counts['total_active']
                    ??
                    0
                );


            $dailyPayroll =
                (int)(
                    $counts['daily_payroll_count']
                    ??
                    0
                );


            $weeklyPayroll =
                (int)(
                    $counts['weekly_payroll_count']
                    ??
                    0
                );


            $exceptionReports =
                (int)(
                    $counts['exception_reports_count']
                    ??
                    0
                );


            $totalSubscriptions =
                $dailyPayroll
                +
                $weeklyPayroll
                +
                $exceptionReports;


            $details = [
                'Total active recipients: '
                .
                $totalActive,

                'Daily payroll subscribers: '
                .
                $dailyPayroll,

                'Weekly payroll subscribers: '
                .
                $weeklyPayroll,

                'Exception-report subscribers: '
                .
                $exceptionReports
            ];


            if ($totalActive === 0) {

                $this->addCheck(
                    $checks,
                    self::WARN,
                    'Notification recipients',
                    'No active notification recipients are configured.',
                    $details
                );


                return;
            }


            if ($totalSubscriptions === 0) {

                $this->addCheck(
                    $checks,
                    self::WARN,
                    'Notification recipients',
                    'Active recipients exist, but none are subscribed to a notification type.',
                    $details
                );


                return;
            }


            $this->addCheck(
                $checks,
                self::PASS,
                'Notification recipients',
                'Active notification recipients are available.',
                $details
            );

        } catch (Throwable $exception) {

            $this->addCheck(
                $checks,
                self::WARN,
                'Notification recipients',
                'Recipient availability could not be checked.',
                [
                    $exception->getMessage()
                ]
            );
        }
    }


    /**
     * @param array<int,array<string,mixed>> $checks
     */
    private function checkDeliveryHistory(
        array &$checks
    ): void
    {
        if ($this->database === null) {

            $this->addCheck(
                $checks,
                self::WARN,
                'Delivery history',
                'Email delivery history could not be checked because the database is unavailable.'
            );


            return;
        }


        try {

            $latestStatement =
                $this->database->query(
                    "
                    SELECT
                        report_type,
                        status,
                        sent_at
                    FROM email_log
                    ORDER BY id DESC
                    LIMIT 1
                    "
                );


            if ($latestStatement === false) {

                throw new RuntimeException(
                    'The latest-delivery query could not be executed.'
                );
            }


            $latest =
                $latestStatement->fetch();


            if (!is_array($latest)) {

                $this->addCheck(
                    $checks,
                    self::WARN,
                    'Delivery history',
                    'No email delivery history is recorded.'
                );


                return;
            }


            $countStatement =
                $this->database->query(
                    "
                    SELECT
                        status,
                        COUNT(*) AS delivery_count
                    FROM email_log
                    GROUP BY status
                    ORDER BY status
                    "
                );


            if ($countStatement === false) {

                throw new RuntimeException(
                    'The delivery-count query could not be executed.'
                );
            }


            $counts =
                $countStatement->fetchAll();


            $details = [
                'Latest report: '
                .
                (
                    $latest['report_type']
                    ??
                    'unknown'
                ),

                'Latest status: '
                .
                (
                    $latest['status']
                    ??
                    'unknown'
                ),

                'Latest timestamp: '
                .
                (
                    $latest['sent_at']
                    ??
                    'unknown'
                )
            ];


            foreach ($counts as $count) {

                $details[] =
                    (
                        $count['status']
                        ??
                        'unknown'
                    )
                    .
                    ' records: '
                    .
                    (
                        $count['delivery_count']
                        ??
                        0
                    );
            }


            if (
                strtolower(
                    (string)(
                        $latest['status']
                        ??
                        ''
                    )
                )
                !==
                'sent'
            ) {
                $this->addCheck(
                    $checks,
                    self::WARN,
                    'Delivery history',
                    'The most recent recorded email delivery was not successful.',
                    $details
                );


                return;
            }


            $this->addCheck(
                $checks,
                self::PASS,
                'Delivery history',
                'The most recent recorded email delivery was successful.',
                $details
            );

        } catch (Throwable $exception) {

            $this->addCheck(
                $checks,
                self::WARN,
                'Delivery history',
                'Email delivery history could not be checked.',
                [
                    $exception->getMessage()
                ]
            );
        }
    }


    /**
     * @param array<int,array<string,mixed>> $checks
     */
    private function checkMailLog(
        array &$checks
    ): void
    {
        $path =
            $this->projectRoot
            .
            '/storage/logs/mail.log';


        if (
            !is_file(
                $path
            )
        ) {
            $this->addCheck(
                $checks,
                self::WARN,
                'Mail log',
                'storage/logs/mail.log does not exist.'
            );


            return;
        }


        $contents =
            file_get_contents(
                $path
            );


        $timestamp =
            filemtime(
                $path
            );


        if (
            $contents === false
            ||
            $timestamp === false
        ) {
            $this->addCheck(
                $checks,
                self::WARN,
                'Mail log',
                'The mail log could not be read completely.'
            );


            return;
        }


        $ageSeconds =
            max(
                0,
                time()
                -
                $timestamp
            );


        $successful =
            str_contains(
                $contents,
                'Email delivery completed successfully.'
            );


        $details = [
            'Last activity: '
            .
            date(
                'Y-m-d H:i:s T',
                $timestamp
            ),

            'Age: '
            .
            $this->formatAge(
                $ageSeconds
            ),

            'Size: '
            .
            $this->formatBytes(
                (int)(
                    filesize(
                        $path
                    )
                    ?:
                    0
                )
            )
        ];


        if (!$successful) {

            $this->addCheck(
                $checks,
                self::WARN,
                'Mail log',
                'No successful delivery entry was found in the current mail log.',
                $details
            );


            return;
        }


        if (
            $ageSeconds
            >
            7
            *
            24
            *
            60
            *
            60
        ) {
            $this->addCheck(
                $checks,
                self::WARN,
                'Mail log',
                'The most recent mail-log activity is more than seven days old.',
                $details
            );


            return;
        }


        $this->addCheck(
            $checks,
            self::PASS,
            'Mail log',
            'Recent successful email delivery activity was found.',
            $details
        );
    }


    /**
     * @param resource $stream
     *
     * @return array{
     *     code:int,
     *     lines:array<int,string>
     * }
     */
    private function readResponse(
        mixed $stream
    ): array
    {
        $lines = [];

        $code = 0;


        for ($lineNumber = 0; $lineNumber < 100; $lineNumber++) {

            $line =
                fgets(
                    $stream,
                    4096
                );


            if ($line === false) {

                $metadata =
                    stream_get_meta_data(
                        $stream
                    );


                if (
                    (
                        $metadata['timed_out']
                        ??
                        false
                    )
                    ===
                    true
                ) {
                    throw new RuntimeException(
                        'The SMTP server response timed out.'
                    );
                }


                throw new RuntimeException(
                    'The SMTP server closed the connection unexpectedly.'
                );
            }


            $line =
                rtrim(
                    $line,
                    "\r\n"
                );


            $lines[] =
                $line;


            if (
                preg_match(
                    '/^(\d{3})([ -])/',
                    $line,
                    $matches
                )
                ===
                1
            ) {
                $code =
                    (int)$matches[1];


                if ($matches[2] === ' ') {

                    return [
                        'code' =>
                            $code,

                        'lines' =>
                            $lines
                    ];
                }
            }
        }


        throw new RuntimeException(
            'The SMTP response exceeded the diagnostic line limit.'
        );
    }


    /**
     * @param resource $stream
     */
    private function writeCommand(
        mixed $stream,
        string $command
    ): void
    {
        $written =
            fwrite(
                $stream,
                $command
                .
                "\r\n"
            );


        if ($written === false) {

            throw new RuntimeException(
                'An SMTP command could not be written.'
            );
        }


        fflush(
            $stream
        );
    }


    /**
     * @param resource $stream
     *
     * @return array<int,string>
     */
    private function certificateDetails(
        mixed $stream
    ): array
    {
        if (
            !function_exists(
                'openssl_x509_parse'
            )
        ) {
            return [];
        }


        $parameters =
            stream_context_get_params(
                $stream
            );


        $certificate =
            $parameters['options']['ssl']['peer_certificate']
            ??
            null;


        if ($certificate === null) {

            return [];
        }


        $parsed =
            @openssl_x509_parse(
                $certificate
            );


        if (!is_array($parsed)) {

            return [];
        }


        $details = [];


        $commonName =
            $parsed['subject']['CN']
            ??
            null;


        if (
            is_string(
                $commonName
            )
            &&
            $commonName !== ''
        ) {
            $details[] =
                'Certificate subject: '
                .
                $commonName;
        }


        $validUntil =
            $parsed['validTo_time_t']
            ??
            null;


        if (is_int($validUntil)) {

            $details[] =
                'Certificate valid until: '
                .
                date(
                    'Y-m-d H:i:s T',
                    $validUntil
                );
        }


        return $details;
    }


    /**
     * @param array<string,mixed> $configuration
     */
    private function sanitize(
        string $message,
        array $configuration
    ): string
    {
        $sensitiveValues = [
            (string)(
                $configuration['username']
                ??
                ''
            ),

            (string)(
                $configuration['password']
                ??
                ''
            ),

            rawurlencode(
                (string)(
                    $configuration['username']
                    ??
                    ''
                )
            ),

            rawurlencode(
                (string)(
                    $configuration['password']
                    ??
                    ''
                )
            )
        ];


        foreach ($sensitiveValues as $value) {

            if ($value === '') {

                continue;
            }


            $message =
                str_replace(
                    $value,
                    '[REDACTED]',
                    $message
                );
        }


        return $message;
    }


    /**
     * @param array<int,array<string,mixed>> $checks
     * @param array<int,string> $details
     */
    private function addCheck(
        array &$checks,
        string $status,
        string $name,
        string $message,
        array $details = []
    ): void
    {
        $checks[] = [
            'status' =>
                $status,

            'name' =>
                $name,

            'message' =>
                $message,

            'details' =>
                $details
        ];
    }


    private function formatBytes(
        int $bytes
    ): string
    {
        if ($bytes < 1024) {

            return
                number_format(
                    $bytes
                )
                .
                ' B';
        }


        $kilobytes =
            $bytes
            /
            1024;


        if ($kilobytes < 1024) {

            return
                number_format(
                    $kilobytes,
                    2
                )
                .
                ' KB';
        }


        $megabytes =
            $kilobytes
            /
            1024;


        return
            number_format(
                $megabytes,
                2
            )
            .
            ' MB';
    }


    private function formatAge(
        int $seconds
    ): string
    {
        if ($seconds < 60) {

            return
                $seconds
                .
                ' second'
                .
                (
                    $seconds === 1
                        ? ''
                        : 's'
                );
        }


        $minutes =
            intdiv(
                $seconds,
                60
            );


        if ($minutes < 60) {

            return
                $minutes
                .
                ' minute'
                .
                (
                    $minutes === 1
                        ? ''
                        : 's'
                );
        }


        $hours =
            intdiv(
                $minutes,
                60
            );


        if ($hours < 48) {

            return
                $hours
                .
                ' hour'
                .
                (
                    $hours === 1
                        ? ''
                        : 's'
                );
        }


        $days =
            intdiv(
                $hours,
                24
            );


        return
            $days
            .
            ' day'
            .
            (
                $days === 1
                    ? ''
                    : 's'
            );
    }
}
