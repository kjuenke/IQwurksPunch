<?php
declare(strict_types=1);

namespace App\Services;

use App\Logging\LoggerInterface;
use PDO;
use RuntimeException;
use Throwable;

final class DatabaseBackupVerificationService
{
    private string $backupDirectory;

    private LoggerInterface $logger;


    public function __construct(
        string $backupDirectory,
        LoggerInterface $logger
    )
    {
        $this->backupDirectory =
            rtrim(
                $backupDirectory,
                DIRECTORY_SEPARATOR
            );


        $this->logger =
            $logger;
    }


    /**
     * @return array<string,mixed>
     */
    public function verify(
        string $filename
    ): array
    {
        $startedAt =
            microtime(
                true
            );


        $path =
            $this->resolveBackupPath(
                $filename
            );


        $this->logger->info(
            'Database backup verification started.',
            [
                'filename' =>
                    basename(
                        $path
                    ),

                'path' =>
                    $path,

                'process_id' =>
                    getmypid()
            ]
        );


        try {

            $database =
                new PDO(
                    'sqlite:'
                    .
                    $path
                );


            $database->setAttribute(
                PDO::ATTR_ERRMODE,
                PDO::ERRMODE_EXCEPTION
            );


            $database->setAttribute(
                PDO::ATTR_DEFAULT_FETCH_MODE,
                PDO::FETCH_ASSOC
            );


            $database->exec(
                'PRAGMA query_only = ON'
            );


            $integrityStatement =
                $database->query(
                    'PRAGMA integrity_check'
                );


            if ($integrityStatement === false) {

                throw new RuntimeException(
                    'SQLite integrity_check could not be executed.'
                );
            }


            $integrityMessages =
                $integrityStatement->fetchAll(
                    PDO::FETCH_COLUMN
                );


            $integrityMessages =
                array_values(
                    array_map(
                        static fn (
                            mixed $message
                        ): string =>
                            trim(
                                (string)$message
                            ),
                        $integrityMessages
                    )
                );


            $integrityValid =
                count(
                    $integrityMessages
                )
                ===
                1
                &&
                strtolower(
                    $integrityMessages[0]
                )
                ===
                'ok';


            $foreignKeyStatement =
                $database->query(
                    'PRAGMA foreign_key_check'
                );


            if ($foreignKeyStatement === false) {

                throw new RuntimeException(
                    'SQLite foreign_key_check could not be executed.'
                );
            }


            $foreignKeyViolations =
                $foreignKeyStatement->fetchAll();


            $sizeBytes =
                filesize(
                    $path
                );


            $modifiedTimestamp =
                filemtime(
                    $path
                );


            if (
                $sizeBytes === false
                ||
                $modifiedTimestamp === false
            ) {
                throw new RuntimeException(
                    'Backup file metadata could not be read.'
                );
            }


            $valid =
                $integrityValid
                &&
                count(
                    $foreignKeyViolations
                )
                ===
                0;


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


            $result = [
                'filename' =>
                    basename(
                        $path
                    ),

                'path' =>
                    $path,

                'valid' =>
                    $valid,

                'integrity_valid' =>
                    $integrityValid,

                'integrity_messages' =>
                    $integrityMessages,

                'foreign_key_violation_count' =>
                    count(
                        $foreignKeyViolations
                    ),

                'foreign_key_violations' =>
                    $foreignKeyViolations,

                'size_bytes' =>
                    $sizeBytes,

                'modified_timestamp' =>
                    $modifiedTimestamp,

                'modified_at' =>
                    date(
                        'Y-m-d H:i:s T',
                        $modifiedTimestamp
                    ),

                'verified_at' =>
                    date(
                        'Y-m-d H:i:s T'
                    ),

                'duration_milliseconds' =>
                    $durationMilliseconds
            ];


            if ($valid) {

                $this->logger->info(
                    'Database backup verification completed successfully.',
                    [
                        'filename' =>
                            $result['filename'],

                        'size_bytes' =>
                            $sizeBytes,

                        'integrity' =>
                            'ok',

                        'foreign_key_violation_count' =>
                            0,

                        'duration_milliseconds' =>
                            $durationMilliseconds
                    ]
                );

            } else {

                $this->logger->error(
                    'Database backup verification failed.',
                    [
                        'filename' =>
                            $result['filename'],

                        'integrity_messages' =>
                            $integrityMessages,

                        'foreign_key_violation_count' =>
                            count(
                                $foreignKeyViolations
                            ),

                        'duration_milliseconds' =>
                            $durationMilliseconds
                    ]
                );
            }


            return $result;

        } catch (Throwable $exception) {

            $this->logger->error(
                'Database backup verification encountered an error.',
                [
                    'filename' =>
                        basename(
                            $path
                        ),

                    'path' =>
                        $path,

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


            throw new RuntimeException(
                'Backup verification failed for '
                .
                basename(
                    $path
                )
                .
                ': '
                .
                $exception->getMessage(),
                0,
                $exception
            );
        }
    }


    private function resolveBackupPath(
        string $filename
    ): string
    {
        $filename =
            trim(
                $filename
            );


        if ($filename === '') {

            throw new RuntimeException(
                'A backup filename is required.'
            );
        }


        if (
            basename(
                $filename
            )
            !==
            $filename
        ) {
            throw new RuntimeException(
                'The backup filename must not contain a directory path.'
            );
        }


        if (
            strtolower(
                pathinfo(
                    $filename,
                    PATHINFO_EXTENSION
                )
            )
            !==
            'sqlite'
        ) {
            throw new RuntimeException(
                'The backup filename must have a .sqlite extension.'
            );
        }


        $backupDirectory =
            realpath(
                $this->backupDirectory
            );


        if ($backupDirectory === false) {

            throw new RuntimeException(
                'The backup directory does not exist: '
                .
                $this->backupDirectory
            );
        }


        if (
            !is_readable(
                $backupDirectory
            )
        ) {
            throw new RuntimeException(
                'The backup directory is not readable: '
                .
                $backupDirectory
            );
        }


        $candidatePath =
            $backupDirectory
            .
            DIRECTORY_SEPARATOR
            .
            $filename;


        if (
            !is_file(
                $candidatePath
            )
        ) {
            throw new RuntimeException(
                'The requested backup file does not exist: '
                .
                $filename
            );
        }


        if (
            !is_readable(
                $candidatePath
            )
        ) {
            throw new RuntimeException(
                'The requested backup file is not readable: '
                .
                $filename
            );
        }


        $realPath =
            realpath(
                $candidatePath
            );


        if ($realPath === false) {

            throw new RuntimeException(
                'The requested backup path could not be resolved.'
            );
        }


        if (
            realpath(
                dirname(
                    $realPath
                )
            )
            !==
            $backupDirectory
        ) {
            throw new RuntimeException(
                'The requested backup is outside the configured backup directory.'
            );
        }


        return $realPath;
    }
}
