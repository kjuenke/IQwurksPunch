<?php
declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\CommandInterface;
use App\Core\Container;
use App\Services\DatabaseBackupCatalogService;
use App\Services\DatabaseBackupVerificationService;
use App\Services\OperationalFailureNotificationService;
use InvalidArgumentException;
use Throwable;

final class BackupVerifyCommand implements CommandInterface
{
    private DatabaseBackupCatalogService $catalog;

    private DatabaseBackupVerificationService $verification;


    public function __construct()
    {
        $projectRoot =
            dirname(
                __DIR__,
                3
            );


        $backupConfig =
            require $projectRoot
            .
            '/config/backup.php';


        $backupDirectory =
            (string)(
                $backupConfig['directory']
                ??
                $projectRoot
                .
                '/storage/backups'
            );


        $this->catalog =
            new DatabaseBackupCatalogService(
                $backupDirectory
            );


        $this->verification =
            new DatabaseBackupVerificationService(
                $backupDirectory,
                Container::logger(
                    'backup'
                )
            );
    }


    public function name(): string
    {
        return 'backup:verify';
    }


    public function description(): string
    {
        return 'Verify the newest, a named, or all SQLite backups.';
    }


    public function execute(
        array $arguments = []
    ): int
    {
        try {

            $filenames =
                $this->resolveFilenames(
                    $arguments
                );


            echo
                'Backups selected for verification: '
                .
                count(
                    $filenames
                )
                .
                PHP_EOL
                .
                PHP_EOL;


            $validCount = 0;

            $invalidCount = 0;

            $failures = [];


            foreach ($filenames as $filename) {

                try {

                    $result =
                        $this->verification->verify(
                            $filename
                        );


                    $status =
                        $result['valid']
                            ? 'PASS'
                            : 'FAIL';


                    echo
                        '['
                        .
                        $status
                        .
                        '] '
                        .
                        $result['filename']
                        .
                        PHP_EOL;


                    echo
                        '  Modified: '
                        .
                        $result['modified_at']
                        .
                        PHP_EOL;


                    echo
                        '  Size: '
                        .
                        $this->formatBytes(
                            (int)$result['size_bytes']
                        )
                        .
                        PHP_EOL;


                    echo
                        '  Integrity: '
                        .
                        (
                            $result['integrity_valid']
                                ? 'ok'
                                : 'failed'
                        )
                        .
                        PHP_EOL;


                    echo
                        '  Foreign-key violations: '
                        .
                        $result['foreign_key_violation_count']
                        .
                        PHP_EOL;


                    echo
                        '  Duration: '
                        .
                        number_format(
                            (float)$result['duration_milliseconds'],
                            2
                        )
                        .
                        ' ms'
                        .
                        PHP_EOL
                        .
                        PHP_EOL;


                    if ($result['valid']) {

                        $validCount++;

                    } else {

                        $invalidCount++;


                        $failures[] = [
                            'filename' =>
                                $result['filename']
                                ??
                                $filename,

                            'integrity_valid' =>
                                $result['integrity_valid']
                                ??
                                false,

                            'integrity_messages' =>
                                $result['integrity_messages']
                                ??
                                [],

                            'foreign_key_violation_count' =>
                                $result['foreign_key_violation_count']
                                ??
                                null,

                            'duration_milliseconds' =>
                                $result['duration_milliseconds']
                                ??
                                null
                        ];


                        foreach (
                            $result['integrity_messages']
                            as $message
                        ) {
                            fwrite(
                                STDERR,
                                '  Integrity message: '
                                .
                                $message
                                .
                                PHP_EOL
                            );
                        }
                    }

                } catch (Throwable $exception) {

                    $invalidCount++;


                    $failures[] = [
                        'filename' =>
                            $filename,

                        'exception_class' =>
                            $exception::class,

                        'exception_message' =>
                            $exception->getMessage(),

                        'exception_file' =>
                            $exception->getFile(),

                        'exception_line' =>
                            $exception->getLine()
                    ];


                    fwrite(
                        STDERR,
                        '[ERROR] '
                        .
                        $filename
                        .
                        PHP_EOL
                    );


                    fwrite(
                        STDERR,
                        '  '
                        .
                        $exception->getMessage()
                        .
                        PHP_EOL
                        .
                        PHP_EOL
                    );
                }
            }


            echo
                'Valid backups: '
                .
                $validCount
                .
                PHP_EOL;


            echo
                'Invalid or unreadable backups: '
                .
                $invalidCount
                .
                PHP_EOL;


            if ($invalidCount > 0) {

                $this->notifyOperationalFailure(
                    'Backup Verification',
                    'One or more database backups failed verification or could not be read.',
                    [
                        'command' =>
                            $this->name(),

                        'selected_count' =>
                            count(
                                $filenames
                            ),

                        'valid_count' =>
                            $validCount,

                        'invalid_count' =>
                            $invalidCount,

                        'selected_backups' =>
                            $filenames,

                        'failures' =>
                            $failures,

                        'exit_code' =>
                            1
                    ]
                );


                return 1;
            }


            echo
                'Backup verification completed successfully.'
                .
                PHP_EOL;


            return 0;

        } catch (InvalidArgumentException $exception) {

            /*
             * Invalid command usage and an empty backup catalog are reported
             * to the operator, but they do not represent an automatic system
             * failure that should generate an email notification.
             */
            fwrite(
                STDERR,
                'Backup verification failed: '
                .
                $exception->getMessage()
                .
                PHP_EOL
            );


            return 1;

        } catch (Throwable $exception) {

            fwrite(
                STDERR,
                'Backup verification failed: '
                .
                $exception->getMessage()
                .
                PHP_EOL
            );


            $this->notifyOperationalFailure(
                'Backup Verification',
                'The database backup verification command failed unexpectedly.',
                [
                    'command' =>
                        $this->name(),

                    'arguments' =>
                        $arguments,

                    'exception_class' =>
                        $exception::class,

                    'exception_message' =>
                        $exception->getMessage(),

                    'exception_file' =>
                        $exception->getFile(),

                    'exception_line' =>
                        $exception->getLine(),

                    'exit_code' =>
                        1
                ]
            );


            return 1;
        }
    }


    /**
     * Operational notification failures must never replace the original
     * backup-verification failure or alter its exit code.
     *
     * @param array<string,mixed> $details
     */
    private function notifyOperationalFailure(
        string $source,
        string $summary,
        array $details
    ): void
    {
        try {

            $notifications =
                new OperationalFailureNotificationService();


            $sent =
                $notifications->sendFailure(
                    $source,
                    $summary,
                    $details
                );


            if (!$sent) {

                fwrite(
                    STDERR,
                    'Warning: the operational failure notification could not be delivered.'
                    .
                    PHP_EOL
                );
            }

        } catch (Throwable $notificationException) {

            fwrite(
                STDERR,
                'Warning: the operational failure notification could not be delivered: '
                .
                $notificationException->getMessage()
                .
                PHP_EOL
            );
        }
    }


    /**
     * @param array<int,mixed> $arguments
     *
     * @return array<int,string>
     */
    private function resolveFilenames(
        array $arguments
    ): array
    {
        if (count($arguments) > 1) {

            throw new InvalidArgumentException(
                'Use no argument, --all, or one backup filename.'
            );
        }


        $backups =
            $this->catalog->all();


        if ($backups === []) {

            throw new InvalidArgumentException(
                'No database backups are available.'
            );
        }


        if ($arguments === []) {

            return [
                (string)$backups[0]['filename']
            ];
        }


        $argument =
            trim(
                (string)$arguments[0]
            );


        if ($argument === '--all') {

            return
                array_values(
                    array_map(
                        static fn (
                            array $backup
                        ): string =>
                            (string)$backup['filename'],
                        $backups
                    )
                );
        }


        if (
            str_starts_with(
                $argument,
                '--'
            )
        ) {
            throw new InvalidArgumentException(
                'Unknown argument: '
                .
                $argument
                .
                '. Use --all or a backup filename.'
            );
        }


        return [
            $argument
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


        if ($megabytes < 1024) {

            return
                number_format(
                    $megabytes,
                    2
                )
                .
                ' MB';
        }


        return
            number_format(
                $megabytes
                /
                1024,
                2
            )
            .
            ' GB';
    }
}
