<?php
declare(strict_types=1);

namespace App\Services;

use App\Logging\LoggerInterface;
use PDO;
use RuntimeException;
use Throwable;

final class DatabaseBackupService
{
    private PDO $db;

    private string $sourceDatabasePath;

    private string $backupDirectory;

    private LoggerInterface $logger;


    public function __construct(
        PDO $db,
        string $sourceDatabasePath,
        string $backupDirectory,
        LoggerInterface $logger
    )
    {
        $this->db =
            $db;


        $this->sourceDatabasePath =
            $sourceDatabasePath;


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
    public function create(): array
    {
        $startedAt =
            microtime(
                true
            );


        $sourcePath =
            $this->validatedSourcePath();


        $backupDirectory =
            $this->preparedBackupDirectory();


        if (
            $this->db->inTransaction()
        ) {
            throw new RuntimeException(
                'A database backup cannot be created while a transaction is active.'
            );
        }


        $backupFilename =
            $this->backupFilename(
                $sourcePath
            );


        $backupPath =
            $backupDirectory
            .
            DIRECTORY_SEPARATOR
            .
            $backupFilename;


        $this->logger->info(
            'Database backup creation started.',
            [
                'source_database' =>
                    $sourcePath,

                'backup_path' =>
                    $backupPath,

                'process_id' =>
                    getmypid(),

                'timezone' =>
                    date_default_timezone_get()
            ]
        );


        try {

            $this->db->exec(
                'PRAGMA busy_timeout = 5000'
            );


            $quotedBackupPath =
                $this->db->quote(
                    $backupPath
                );


            if ($quotedBackupPath === false) {

                throw new RuntimeException(
                    'The backup destination path could not be quoted safely.'
                );
            }


            $this->db->exec(
                'VACUUM INTO '
                .
                $quotedBackupPath
            );


            clearstatcache(
                true,
                $backupPath
            );


            if (
                !is_file(
                    $backupPath
                )
            ) {
                throw new RuntimeException(
                    'SQLite did not create the expected backup file.'
                );
            }


            $sizeBytes =
                filesize(
                    $backupPath
                );


            if (
                $sizeBytes === false
                ||
                $sizeBytes <= 0
            ) {
                throw new RuntimeException(
                    'The generated backup file is empty.'
                );
            }


            $integrityResult =
                $this->verifyIntegrity(
                    $backupPath
                );


            if (!$integrityResult['valid']) {

                throw new RuntimeException(
                    'The generated backup failed its SQLite integrity check: '
                    .
                    implode(
                        ' | ',
                        $integrityResult['messages']
                    )
                );
            }


            @chmod(
                $backupPath,
                0640
            );


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
                    $backupFilename,

                'path' =>
                    $backupPath,

                'source_path' =>
                    $sourcePath,

                'size_bytes' =>
                    $sizeBytes,

                'integrity' =>
                    'ok',

                'created_at' =>
                    date(
                        'Y-m-d H:i:s T'
                    ),

                'duration_milliseconds' =>
                    $durationMilliseconds
            ];


            $this->logger->info(
                'Database backup created successfully.',
                $result
            );


            return $result;

        } catch (Throwable $exception) {

            if (
                is_file(
                    $backupPath
                )
            ) {
                @unlink(
                    $backupPath
                );
            }


            $this->logger->error(
                'Database backup creation failed.',
                [
                    'source_database' =>
                        $sourcePath,

                    'backup_path' =>
                        $backupPath,

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
                'Database backup failed: '
                .
                $exception->getMessage(),
                0,
                $exception
            );
        }
    }


    private function validatedSourcePath(): string
    {
        $sourcePath =
            trim(
                $this->sourceDatabasePath
            );


        if ($sourcePath === '') {

            throw new RuntimeException(
                'The SQLite database path is not configured.'
            );
        }


        if (
            !is_file(
                $sourcePath
            )
        ) {
            throw new RuntimeException(
                'The configured SQLite database file does not exist: '
                .
                $sourcePath
            );
        }


        if (
            !is_readable(
                $sourcePath
            )
        ) {
            throw new RuntimeException(
                'The configured SQLite database file is not readable: '
                .
                $sourcePath
            );
        }


        $realPath =
            realpath(
                $sourcePath
            );


        if ($realPath === false) {

            throw new RuntimeException(
                'The configured SQLite database path could not be resolved.'
            );
        }


        return $realPath;
    }


    private function preparedBackupDirectory(): string
    {
        if (
            !is_dir(
                $this->backupDirectory
            )
        ) {
            $created =
                mkdir(
                    $this->backupDirectory,
                    0770,
                    true
                );


            if (
                !$created
                &&
                !is_dir(
                    $this->backupDirectory
                )
            ) {
                throw new RuntimeException(
                    'The backup directory could not be created: '
                    .
                    $this->backupDirectory
                );
            }
        }


        if (
            !is_writable(
                $this->backupDirectory
            )
        ) {
            throw new RuntimeException(
                'The backup directory is not writable: '
                .
                $this->backupDirectory
            );
        }


        $realPath =
            realpath(
                $this->backupDirectory
            );


        if ($realPath === false) {

            throw new RuntimeException(
                'The backup directory path could not be resolved.'
            );
        }


        return $realPath;
    }


    private function backupFilename(
        string $sourcePath
    ): string
    {
        $databaseName =
            pathinfo(
                $sourcePath,
                PATHINFO_FILENAME
            );


        $databaseName =
            preg_replace(
                '/[^A-Za-z0-9_-]+/',
                '-',
                $databaseName
            )
            ??
            'database';


        $randomSuffix =
            bin2hex(
                random_bytes(
                    3
                )
            );


        return
            $databaseName
            .
            '_'
            .
            date(
                'Ymd_His'
            )
            .
            '_'
            .
            $randomSuffix
            .
            '.sqlite';
    }


    /**
     * @return array{
     *     valid:bool,
     *     messages:array<int,string>
     * }
     */
    private function verifyIntegrity(
        string $backupPath
    ): array
    {
        $backupDatabase =
            new PDO(
                'sqlite:'
                .
                $backupPath
            );


        $backupDatabase->setAttribute(
            PDO::ATTR_ERRMODE,
            PDO::ERRMODE_EXCEPTION
        );


        $statement =
            $backupDatabase->query(
                'PRAGMA integrity_check'
            );


        if ($statement === false) {

            throw new RuntimeException(
                'The backup integrity check could not be executed.'
            );
        }


        $messages =
            $statement->fetchAll(
                PDO::FETCH_COLUMN
            );


        $messages =
            array_values(
                array_map(
                    static fn (
                        mixed $message
                    ): string =>
                        trim(
                            (string)$message
                        ),
                    $messages
                )
            );


        return [
            'valid' =>
                count(
                    $messages
                )
                ===
                1
                &&
                strtolower(
                    $messages[0]
                )
                ===
                'ok',

            'messages' =>
                $messages
        ];
    }
}
