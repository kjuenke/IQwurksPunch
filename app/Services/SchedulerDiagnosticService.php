<?php
declare(strict_types=1);

namespace App\Services;

use App\Logging\LoggerInterface;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use Throwable;

final class SchedulerDiagnosticService
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


        $cron =
            $this->checkCronEntry(
                $checks
            );


        $lockFile =
            $cron['lock_file']
            ??
            '/tmp/iqwurks-scheduler.lock';


        $this->checkSchedulerLock(
            $checks,
            $lockFile
        );


        $this->checkApplicationLog(
            $checks
        );


        $this->checkCronOutputLog(
            $checks
        );


        $this->checkRecentFailures(
            $checks
        );


        $timezone =
            $this->checkCompanyTimezone(
                $checks
            );


        $schedule =
            $this->checkScheduleConfiguration(
                $checks
            );


        $this->checkRecipients(
            $checks,
            $schedule
        );


        $this->checkLastScheduledDelivery(
            $checks,
            $schedule,
            $timezone
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
                'Scheduler diagnostic detected one or more failures.',
                $context
            );

        } elseif ($warningCount > 0) {

            $this->logger->warning(
                'Scheduler diagnostic completed with warnings.',
                $context
            );

        } else {

            $this->logger->info(
                'Scheduler diagnostic completed successfully.',
                $context
            );
        }


        return $result;
    }


    /**
     * @param array<int,array<string,mixed>> $checks
     *
     * @return array<string,mixed>
     */
    private function checkCronEntry(
        array &$checks
    ): array
    {
        $crontab =
            $this->readCrontab();


        if (!$crontab['available']) {

            $this->addCheck(
                $checks,
                self::FAIL,
                'Scheduler cron',
                'The current crontab could not be inspected.',
                [
                    $crontab['message']
                ]
            );


            return [
                'installed' =>
                    false,

                'lock_file' =>
                    '/tmp/iqwurks-scheduler.lock'
            ];
        }


        $matchingLines = [];


        foreach (
            preg_split(
                '/\R/',
                $crontab['contents']
            )
            ?:
            []
            as $line
        ) {
            $line =
                trim(
                    $line
                );


            if (
                $line === ''
                ||
                str_starts_with(
                    $line,
                    '#'
                )
            ) {
                continue;
            }


            if (
                str_contains(
                    $line,
                    'schedule:run'
                )
            ) {
                $matchingLines[] =
                    $line;
            }
        }


        if ($matchingLines === []) {

            $this->addCheck(
                $checks,
                self::FAIL,
                'Scheduler cron',
                'No active schedule:run cron entry was found.'
            );


            return [
                'installed' =>
                    false,

                'lock_file' =>
                    '/tmp/iqwurks-scheduler.lock'
            ];
        }


        $lockFile =
            '/tmp/iqwurks-scheduler.lock';


        if (
            preg_match(
                '/\bflock\s+-n\s+([^\s]+)/',
                $matchingLines[0],
                $matches
            )
            ===
            1
        ) {
            $lockFile =
                trim(
                    $matches[1],
                    "\"'"
                );
        }


        $details = [
            'Entries found: '
            .
            count(
                $matchingLines
            ),

            'Lock file: '
            .
            $lockFile,

            'Command: '
            .
            $matchingLines[0]
        ];


        if (
            count(
                $matchingLines
            )
            >
            1
        ) {
            $this->addCheck(
                $checks,
                self::WARN,
                'Scheduler cron',
                'Multiple active schedule:run cron entries were found.',
                $details
            );

        } else {

            $this->addCheck(
                $checks,
                self::PASS,
                'Scheduler cron',
                'The schedule:run cron entry is installed.',
                $details
            );
        }


        return [
            'installed' =>
                true,

            'lock_file' =>
                $lockFile,

            'entries' =>
                $matchingLines
        ];
    }


    /**
     * @param array<int,array<string,mixed>> $checks
     */
    private function checkSchedulerLock(
        array &$checks,
        string $lockFile
    ): void
    {
        $directory =
            dirname(
                $lockFile
            );


        if (
            !is_dir(
                $directory
            )
            ||
            !is_writable(
                $directory
            )
        ) {
            $this->addCheck(
                $checks,
                self::FAIL,
                'Scheduler lock',
                'The scheduler lock directory is unavailable or not writable.',
                [
                    'Directory: '
                    .
                    $directory
                ]
            );


            return;
        }


        $handle =
            @fopen(
                $lockFile,
                'c+'
            );


        if ($handle === false) {

            $this->addCheck(
                $checks,
                self::FAIL,
                'Scheduler lock',
                'The scheduler lock file could not be opened.',
                [
                    'File: '
                    .
                    $lockFile
                ]
            );


            return;
        }


        $lockAcquired =
            flock(
                $handle,
                LOCK_EX
                |
                LOCK_NB
            );


        if ($lockAcquired) {

            flock(
                $handle,
                LOCK_UN
            );


            fclose(
                $handle
            );


            $details = [
                'File: '
                .
                $lockFile,

                'Currently held: no'
            ];


            if (
                is_file(
                    $lockFile
                )
            ) {
                $timestamp =
                    filemtime(
                        $lockFile
                    );


                if ($timestamp !== false) {

                    $details[] =
                        'File modified: '
                        .
                        date(
                            'Y-m-d H:i:s T',
                            $timestamp
                        );
                }
            }


            $this->addCheck(
                $checks,
                self::PASS,
                'Scheduler lock',
                'The scheduler lock is available.',
                $details
            );


            return;
        }


        fclose(
            $handle
        );


        $this->addCheck(
            $checks,
            self::WARN,
            'Scheduler lock',
            'The scheduler lock is currently held by another process.',
            [
                'File: '
                .
                $lockFile,

                'This may be normal during an active scheduler run.'
            ]
        );
    }


    /**
     * @param array<int,array<string,mixed>> $checks
     */
    private function checkApplicationLog(
        array &$checks
    ): void
    {
        $path =
            $this->projectRoot
            .
            '/storage/logs/scheduler.log';


        if (
            !is_file(
                $path
            )
        ) {
            $this->addCheck(
                $checks,
                self::FAIL,
                'Scheduler application log',
                'storage/logs/scheduler.log does not exist.'
            );


            return;
        }


        if (
            !is_readable(
                $path
            )
        ) {
            $this->addCheck(
                $checks,
                self::FAIL,
                'Scheduler application log',
                'The scheduler application log is not readable.'
            );


            return;
        }


        $timestamp =
            filemtime(
                $path
            );


        $sizeBytes =
            filesize(
                $path
            );


        if ($timestamp === false) {

            $this->addCheck(
                $checks,
                self::FAIL,
                'Scheduler application log',
                'The scheduler log modification time could not be read.'
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
                $sizeBytes === false
                    ? 0
                    : (int)$sizeBytes
            )
        ];


        if ($ageSeconds > 10 * 60) {

            $this->addCheck(
                $checks,
                self::FAIL,
                'Scheduler application log',
                'No scheduler application activity has been recorded in the last ten minutes.',
                $details
            );


            return;
        }


        $this->addCheck(
            $checks,
            self::PASS,
            'Scheduler application log',
            'Recent scheduler application activity was detected.',
            $details
        );
    }


    /**
     * @param array<int,array<string,mixed>> $checks
     */
    private function checkCronOutputLog(
        array &$checks
    ): void
    {
        $path =
            $this->projectRoot
            .
            '/storage/logs/cron-scheduler.log';


        if (
            !is_file(
                $path
            )
        ) {
            $this->addCheck(
                $checks,
                self::WARN,
                'Scheduler cron log',
                'storage/logs/cron-scheduler.log does not exist.'
            );


            return;
        }


        if (
            !is_readable(
                $path
            )
        ) {
            $this->addCheck(
                $checks,
                self::WARN,
                'Scheduler cron log',
                'The scheduler cron-output log is not readable.'
            );


            return;
        }


        $timestamp =
            filemtime(
                $path
            );


        $sizeBytes =
            filesize(
                $path
            );


        if ($timestamp === false) {

            $this->addCheck(
                $checks,
                self::WARN,
                'Scheduler cron log',
                'The scheduler cron-output log modification time could not be read.'
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
                $sizeBytes === false
                    ? 0
                    : (int)$sizeBytes
            )
        ];


        if ($ageSeconds > 10 * 60) {

            $this->addCheck(
                $checks,
                self::WARN,
                'Scheduler cron log',
                'No scheduler cron-output activity has been recorded in the last ten minutes.',
                $details
            );


            return;
        }


        $this->addCheck(
            $checks,
            self::PASS,
            'Scheduler cron log',
            'Recent scheduler cron-output activity was detected.',
            $details
        );
    }


    /**
     * @param array<int,array<string,mixed>> $checks
     */
    private function checkRecentFailures(
        array &$checks
    ): void
    {
        $path =
            $this->projectRoot
            .
            '/storage/logs/scheduler.log';


        if (
            !is_file(
                $path
            )
            ||
            !is_readable(
                $path
            )
        ) {
            $this->addCheck(
                $checks,
                self::WARN,
                'Recent scheduler failures',
                'Recent scheduler failures could not be inspected because the log is unavailable.'
            );


            return;
        }


        $lines =
            file(
                $path,
                FILE_IGNORE_NEW_LINES
            );


        if ($lines === false) {

            $this->addCheck(
                $checks,
                self::WARN,
                'Recent scheduler failures',
                'The scheduler log could not be read.'
            );


            return;
        }


        $lines =
            array_slice(
                $lines,
                -2000
            );


        $utc =
            new DateTimeZone(
                'UTC'
            );


        $now =
            new DateTimeImmutable(
                'now',
                $utc
            );


        $recentFailures = [];


        foreach ($lines as $line) {

            if (
                preg_match(
                    '/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/',
                    $line,
                    $matches
                )
                !==
                1
            ) {
                continue;
            }


            $timestamp =
                DateTimeImmutable::createFromFormat(
                    'Y-m-d H:i:s',
                    $matches[1],
                    $utc
                );


            if ($timestamp === false) {

                continue;
            }


            $ageSeconds =
                $now->getTimestamp()
                -
                $timestamp->getTimestamp();


            if (
                $ageSeconds < 0
                ||
                $ageSeconds > 24 * 60 * 60
            ) {
                continue;
            }


            if (
                str_contains(
                    $line,
                    ' ERROR scheduler:'
                )
                ||
                str_contains(
                    $line,
                    ' CRITICAL scheduler:'
                )
                ||
                str_contains(
                    $line,
                    '"exit_code":1'
                )
            ) {
                $recentFailures[] =
                    $line;
            }
        }


        $recentFailures =
            array_values(
                array_unique(
                    $recentFailures
                )
            );


        if ($recentFailures !== []) {

            $this->addCheck(
                $checks,
                self::WARN,
                'Recent scheduler failures',
                'One or more scheduler failures were recorded in the last 24 hours.',
                array_slice(
                    $recentFailures,
                    -5
                )
            );


            return;
        }


        $this->addCheck(
            $checks,
            self::PASS,
            'Recent scheduler failures',
            'No scheduler failures were found in the last 24 hours.'
        );
    }


    /**
     * @param array<int,array<string,mixed>> $checks
     */
    private function checkCompanyTimezone(
        array &$checks
    ): ?string
    {
        if ($this->database === null) {

            $this->addCheck(
                $checks,
                self::FAIL,
                'Company timezone',
                'The company timezone could not be checked because the database is unavailable.'
            );


            return null;
        }


        try {

            $statement =
                $this->database->query(
                    '
                    SELECT timezone
                    FROM company_settings
                    ORDER BY id ASC
                    LIMIT 1
                    '
                );


            if ($statement === false) {

                throw new RuntimeException(
                    'The company timezone query could not be executed.'
                );
            }


            $timezone =
                trim(
                    (string)$statement->fetchColumn()
                );


            if (
                $timezone === ''
                ||
                !in_array(
                    $timezone,
                    timezone_identifiers_list(),
                    true
                )
            ) {
                $this->addCheck(
                    $checks,
                    self::FAIL,
                    'Company timezone',
                    'The company timezone is missing or invalid.',
                    [
                        'Configured value: '
                        .
                        (
                            $timezone === ''
                                ? '(empty)'
                                : $timezone
                        )
                    ]
                );


                return null;
            }


            $this->addCheck(
                $checks,
                self::PASS,
                'Company timezone',
                'The company timezone is valid.',
                [
                    'Timezone: '
                    .
                    $timezone
                ]
            );


            return $timezone;

        } catch (Throwable $exception) {

            $this->addCheck(
                $checks,
                self::FAIL,
                'Company timezone',
                'The company timezone could not be checked.',
                [
                    $exception->getMessage()
                ]
            );


            return null;
        }
    }


    /**
     * @param array<int,array<string,mixed>> $checks
     *
     * @return array<string,mixed>|null
     */
    private function checkScheduleConfiguration(
        array &$checks
    ): ?array
    {
        if ($this->database === null) {

            $this->addCheck(
                $checks,
                self::FAIL,
                'Report schedule',
                'The report schedule could not be checked because the database is unavailable.'
            );


            return null;
        }


        try {

            $statement =
                $this->database->query(
                    '
                    SELECT *
                    FROM report_schedule_settings
                    ORDER BY id ASC
                    LIMIT 1
                    '
                );


            if ($statement === false) {

                throw new RuntimeException(
                    'The report schedule query could not be executed.'
                );
            }


            $schedule =
                $statement->fetch();


            if (!is_array($schedule)) {

                $this->addCheck(
                    $checks,
                    self::FAIL,
                    'Report schedule',
                    'No report schedule record exists.'
                );


                return null;
            }


            $enabled =
                (bool)(
                    $schedule['enabled']
                    ??
                    false
                );


            $sendTime =
                trim(
                    (string)(
                        $schedule['send_time']
                        ??
                        ''
                    )
                );


            $weekdaysOnly =
                (bool)(
                    $schedule['weekdays_only']
                    ??
                    false
                );


            if (
                preg_match(
                    '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
                    $sendTime
                )
                !==
                1
            ) {
                $this->addCheck(
                    $checks,
                    self::FAIL,
                    'Report schedule',
                    'The configured report delivery time is invalid.',
                    [
                        'Configured value: '
                        .
                        (
                            $sendTime === ''
                                ? '(empty)'
                                : $sendTime
                        )
                    ]
                );


                return $schedule;
            }


            $details = [
                'Enabled: '
                .
                (
                    $enabled
                        ? 'yes'
                        : 'no'
                ),

                'Delivery time: '
                .
                $sendTime,

                'Delivery days: '
                .
                (
                    $weekdaysOnly
                        ? 'Monday through Friday'
                        : 'Every day'
                ),

                'Last sent UTC: '
                .
                (
                    $schedule['last_sent_at']
                    ??
                    'never'
                ),

                'Updated: '
                .
                (
                    $schedule['updated_at']
                    ??
                    'unknown'
                )
            ];


            if (!$enabled) {

                $this->addCheck(
                    $checks,
                    self::WARN,
                    'Report schedule',
                    'Automatic payroll-report delivery is disabled.',
                    $details
                );

            } else {

                $this->addCheck(
                    $checks,
                    self::PASS,
                    'Report schedule',
                    'Automatic payroll-report delivery is enabled and structurally valid.',
                    $details
                );
            }


            return $schedule;

        } catch (Throwable $exception) {

            $this->addCheck(
                $checks,
                self::FAIL,
                'Report schedule',
                'The report schedule could not be checked.',
                [
                    $exception->getMessage()
                ]
            );


            return null;
        }
    }


    /**
     * @param array<int,array<string,mixed>> $checks
     * @param array<string,mixed>|null $schedule
     */
    private function checkRecipients(
        array &$checks,
        ?array $schedule
    ): void
    {
        if ($this->database === null) {

            $this->addCheck(
                $checks,
                self::FAIL,
                'Scheduled recipients',
                'Scheduled recipients could not be checked because the database is unavailable.'
            );


            return;
        }


        try {

            $statement =
                $this->database->query(
                    "
                    SELECT
                        SUM(
                            CASE
                                WHEN active = 1
                                THEN 1
                                ELSE 0
                            END
                        ) AS total_active,

                        SUM(
                            CASE
                                WHEN active = 1
                                 AND daily_payroll = 1
                                THEN 1
                                ELSE 0
                            END
                        ) AS daily_payroll_count

                    FROM notification_recipients
                    "
                );


            if ($statement === false) {

                throw new RuntimeException(
                    'The scheduled-recipient query could not be executed.'
                );
            }


            $counts =
                $statement->fetch(
                    PDO::FETCH_ASSOC
                );


            if (!is_array($counts)) {

                throw new RuntimeException(
                    'Scheduled-recipient totals could not be read.'
                );
            }


            $totalActive =
                (int)(
                    $counts['total_active']
                    ??
                    0
                );


            $dailyPayrollCount =
                (int)(
                    $counts['daily_payroll_count']
                    ??
                    0
                );


            $scheduleEnabled =
                (bool)(
                    $schedule['enabled']
                    ??
                    false
                );


            $details = [
                'Schedule enabled: '
                .
                (
                    $scheduleEnabled
                        ? 'yes'
                        : 'no'
                ),

                'Total active recipients: '
                .
                $totalActive,

                'Daily payroll subscribers: '
                .
                $dailyPayrollCount
            ];


            if ($dailyPayrollCount === 0) {

                $this->addCheck(
                    $checks,
                    $scheduleEnabled
                        ? self::FAIL
                        : self::WARN,
                    'Scheduled recipients',
                    'No active recipients are subscribed to daily payroll reports.',
                    $details
                );


                return;
            }


            $this->addCheck(
                $checks,
                self::PASS,
                'Scheduled recipients',
                'Active daily-payroll recipients are available.',
                $details
            );

        } catch (Throwable $exception) {

            $this->addCheck(
                $checks,
                self::FAIL,
                'Scheduled recipients',
                'Scheduled recipients could not be checked.',
                [
                    $exception->getMessage()
                ]
            );
        }
    }


    /**
     * @param array<int,array<string,mixed>> $checks
     * @param array<string,mixed>|null $schedule
     */
    private function checkLastScheduledDelivery(
        array &$checks,
        ?array $schedule,
        ?string $timezone
    ): void
    {
        if (
            $schedule === null
            ||
            $timezone === null
        ) {
            $this->addCheck(
                $checks,
                self::WARN,
                'Last scheduled delivery',
                'Scheduled-delivery recency could not be evaluated.'
            );


            return;
        }


        $enabled =
            (bool)(
                $schedule['enabled']
                ??
                false
            );


        if (!$enabled) {

            $this->addCheck(
                $checks,
                self::WARN,
                'Last scheduled delivery',
                'Delivery recency was not enforced because automatic delivery is disabled.'
            );


            return;
        }


        $sendTime =
            trim(
                (string)(
                    $schedule['send_time']
                    ??
                    ''
                )
            );


        if (
            preg_match(
                '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
                $sendTime
            )
            !==
            1
        ) {
            $this->addCheck(
                $checks,
                self::FAIL,
                'Last scheduled delivery',
                'Delivery recency could not be evaluated because the configured time is invalid.'
            );


            return;
        }


        $timezoneObject =
            new DateTimeZone(
                $timezone
            );


        $now =
            new DateTimeImmutable(
                'now',
                $timezoneObject
            );


        $expectedDelivery =
            $this->mostRecentExpectedDelivery(
                $now,
                $sendTime,
                (bool)(
                    $schedule['weekdays_only']
                    ??
                    false
                )
            );


        $lastSentValue =
            trim(
                (string)(
                    $schedule['last_sent_at']
                    ??
                    ''
                )
            );


        if ($lastSentValue === '') {

            $this->addCheck(
                $checks,
                self::WARN,
                'Last scheduled delivery',
                'No successful scheduled-delivery timestamp is recorded.',
                [
                    'Most recent expected delivery: '
                    .
                    $expectedDelivery->format(
                        'Y-m-d H:i:s T'
                    )
                ]
            );


            return;
        }


        try {

            $lastSent =
                new DateTimeImmutable(
                    $lastSentValue,
                    new DateTimeZone(
                        'UTC'
                    )
                );


            $lastSentLocal =
                $lastSent->setTimezone(
                    $timezoneObject
                );


            $details = [
                'Last successful scheduled delivery: '
                .
                $lastSentLocal->format(
                    'Y-m-d H:i:s T'
                ),

                'Stored UTC value: '
                .
                $lastSentValue,

                'Most recent expected delivery: '
                .
                $expectedDelivery->format(
                    'Y-m-d H:i:s T'
                )
            ];


            if (
                $lastSentLocal
                <
                $expectedDelivery
            ) {
                $this->addCheck(
                    $checks,
                    self::WARN,
                    'Last scheduled delivery',
                    'The recorded scheduled delivery is older than the most recent expected delivery.',
                    $details
                );


                return;
            }


            $this->addCheck(
                $checks,
                self::PASS,
                'Last scheduled delivery',
                'The recorded scheduled delivery is current.',
                $details
            );

        } catch (Throwable $exception) {

            $this->addCheck(
                $checks,
                self::FAIL,
                'Last scheduled delivery',
                'The recorded last_sent_at value is invalid.',
                [
                    'Value: '
                    .
                    $lastSentValue,

                    $exception->getMessage()
                ]
            );
        }
    }


    private function mostRecentExpectedDelivery(
        DateTimeImmutable $now,
        string $sendTime,
        bool $weekdaysOnly
    ): DateTimeImmutable
    {
        $timezone =
            $now->getTimezone();


        for (
            $daysBack = 0;
            $daysBack <= 14;
            $daysBack++
        ) {
            $date =
                $now->modify(
                    '-'
                    .
                    $daysBack
                    .
                    ' days'
                );


            if (
                $weekdaysOnly
                &&
                (int)$date->format(
                    'N'
                )
                >
                5
            ) {
                continue;
            }


            $candidate =
                DateTimeImmutable::createFromFormat(
                    'Y-m-d H:i',
                    $date->format(
                        'Y-m-d'
                    )
                    .
                    ' '
                    .
                    $sendTime,
                    $timezone
                );


            if (
                $candidate !== false
                &&
                $candidate <= $now
            ) {
                return $candidate;
            }
        }


        throw new RuntimeException(
            'The most recent expected scheduler delivery could not be calculated.'
        );
    }


    /**
     * @return array{
     *     available:bool,
     *     contents:string,
     *     message:string
     * }
     */
    private function readCrontab(): array
    {
        if (
            !function_exists(
                'exec'
            )
        ) {
            return [
                'available' =>
                    false,

                'contents' =>
                    '',

                'message' =>
                    'The PHP exec function is unavailable.'
            ];
        }


        $command =
            is_executable(
                '/usr/bin/crontab'
            )
                ? '/usr/bin/crontab'
                : 'crontab';


        $output = [];

        $exitCode = 1;


        exec(
            $command
            .
            ' -l 2>&1',
            $output,
            $exitCode
        );


        $contents =
            implode(
                PHP_EOL,
                $output
            );


        if ($exitCode !== 0) {

            return [
                'available' =>
                    false,

                'contents' =>
                    $contents,

                'message' =>
                    $contents === ''
                        ? 'crontab -l returned exit code '
                            .
                            $exitCode
                            .
                            '.'
                        : $contents
            ];
        }


        return [
            'available' =>
                true,

            'contents' =>
                $contents,

            'message' =>
                'Crontab inspected successfully.'
        ];
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
}
