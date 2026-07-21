<?php
declare(strict_types=1);

namespace App\Services;

use App\Logging\LoggerInterface;
use PDO;
use RuntimeException;
use Throwable;

final class DatabaseHealthService
{
    private const REQUIRED_CORE_TABLES = [
        'employees',
        'migrations',
        'punches',
        'settings',
        'users',
    ];


    private string $databasePath;

    private LoggerInterface $logger;


    public function __construct(
        string $databasePath,
        LoggerInterface $logger
    )
    {
        $this->databasePath =
            $databasePath;


        $this->logger =
            $logger;
    }


    /**
     * @return array<string,mixed>
     */
    public function inspect(): array
    {
        $startedAt =
            microtime(
                true
            );


        $result = [
            'healthy' =>
                false,

            'database_path' =>
                $this->databasePath,

            'database_exists' =>
                false,

            'database_readable' =>
                false,

            'database_writable' =>
                false,

            'directory_writable' =>
                false,

            'size_bytes' =>
                0,

            'sqlite_version' =>
                null,

            'journal_mode' =>
                null,

            'integrity_valid' =>
                false,

            'integrity_messages' =>
                [],

            'foreign_key_violation_count' =>
                0,

            'foreign_key_violations' =>
                [],

            'table_count' =>
                0,

            'tables' =>
                [],

            'missing_core_tables' =>
                [],

            'migration_count' =>
                null,

            'page_size' =>
                null,

            'page_count' =>
                null,

            'freelist_count' =>
                null,

            'warnings' =>
                [],

            'errors' =>
                [],

            'checked_at' =>
                date(
                    'Y-m-d H:i:s T'
                ),

            'duration_milliseconds' =>
                0.0
        ];


        $this->logger->info(
            'Active database health check started.',
            [
                'database_path' =>
                    $this->databasePath,

                'process_id' =>
                    getmypid()
            ]
        );


        try {

            $databasePath =
                $this->resolveDatabasePath();


            $result['database_path'] =
                $databasePath;


            $result['database_exists'] =
                true;


            $result['database_readable'] =
                is_readable(
                    $databasePath
                );


            $result['database_writable'] =
                is_writable(
                    $databasePath
                );


            $result['directory_writable'] =
                is_writable(
                    dirname(
                        $databasePath
                    )
                );


            $sizeBytes =
                filesize(
                    $databasePath
                );


            if ($sizeBytes === false) {

                throw new RuntimeException(
                    'The database file size could not be read.'
                );
            }


            $result['size_bytes'] =
                $sizeBytes;


            if (!$result['database_writable']) {

                $result['errors'][] =
                    'The database file is not writable.';
            }


            if (!$result['directory_writable']) {

                $result['errors'][] =
                    'The database directory is not writable.';
            }


            $database =
                new PDO(
                    'sqlite:'
                    .
                    $databasePath
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


            $result['sqlite_version'] =
                $this->scalar(
                    $database,
                    'SELECT sqlite_version()'
                );


            $result['journal_mode'] =
                $this->scalar(
                    $database,
                    'PRAGMA journal_mode'
                );


            $result['page_size'] =
                (int)$this->scalar(
                    $database,
                    'PRAGMA page_size'
                );


            $result['page_count'] =
                (int)$this->scalar(
                    $database,
                    'PRAGMA page_count'
                );


            $result['freelist_count'] =
                (int)$this->scalar(
                    $database,
                    'PRAGMA freelist_count'
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


            $result['integrity_messages'] =
                $integrityMessages;


            $result['integrity_valid'] =
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


            if (!$result['integrity_valid']) {

                $result['errors'][] =
                    'SQLite integrity_check did not return ok.';
            }


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


            $result['foreign_key_violations'] =
                $foreignKeyViolations;


            $result['foreign_key_violation_count'] =
                count(
                    $foreignKeyViolations
                );


            if (
                $result['foreign_key_violation_count']
                >
                0
            ) {
                $result['errors'][] =
                    'Foreign-key violations were detected.';
            }


            $tableStatement =
                $database->query(
                    "
                    SELECT name
                    FROM sqlite_master
                    WHERE type = 'table'
                      AND name NOT LIKE 'sqlite_%'
                    ORDER BY name
                    "
                );


            if ($tableStatement === false) {

                throw new RuntimeException(
                    'The database table list could not be read.'
                );
            }


            $tables =
                array_values(
                    array_map(
                        static fn (
                            mixed $table
                        ): string =>
                            (string)$table,
                        $tableStatement->fetchAll(
                            PDO::FETCH_COLUMN
                        )
                    )
                );


            $result['tables'] =
                $tables;


            $result['table_count'] =
                count(
                    $tables
                );


            $result['missing_core_tables'] =
                array_values(
                    array_diff(
                        self::REQUIRED_CORE_TABLES,
                        $tables
                    )
                );


            if (
                $result['missing_core_tables']
                !==
                []
            ) {
                $result['errors'][] =
                    'One or more required core tables are missing.';
            }


            if (
                in_array(
                    'migrations',
                    $tables,
                    true
                )
            ) {
                $result['migration_count'] =
                    (int)$this->scalar(
                        $database,
                        'SELECT COUNT(*) FROM migrations'
                    );

            } else {

                $result['errors'][] =
                    'The migrations table is not available.';
            }


            if (
                strtolower(
                    (string)$result['journal_mode']
                )
                !==
                'wal'
            ) {
                $result['warnings'][] =
                    'SQLite journal mode is not WAL.';
            }


            if (
                (int)$result['freelist_count']
                >
                0
            ) {
                $result['warnings'][] =
                    'The database contains unused pages that may be reclaimed during a future VACUUM operation.';
            }


            $result['healthy'] =
                $result['errors']
                ===
                [];


            $result['duration_milliseconds'] =
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


            if ($result['healthy']) {

                $this->logger->info(
                    'Active database health check completed successfully.',
                    [
                        'database_path' =>
                            $databasePath,

                        'size_bytes' =>
                            $result['size_bytes'],

                        'table_count' =>
                            $result['table_count'],

                        'migration_count' =>
                            $result['migration_count'],

                        'journal_mode' =>
                            $result['journal_mode'],

                        'warning_count' =>
                            count(
                                $result['warnings']
                            ),

                        'duration_milliseconds' =>
                            $result['duration_milliseconds']
                    ]
                );

            } else {

                $this->logger->error(
                    'Active database health check detected failures.',
                    [
                        'database_path' =>
                            $databasePath,

                        'errors' =>
                            $result['errors'],

                        'warnings' =>
                            $result['warnings'],

                        'duration_milliseconds' =>
                            $result['duration_milliseconds']
                    ]
                );
            }


            return $result;

        } catch (Throwable $exception) {

            $result['errors'][] =
                $exception->getMessage();


            $result['duration_milliseconds'] =
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


            $this->logger->critical(
                'Active database health check failed unexpectedly.',
                [
                    'database_path' =>
                        $this->databasePath,

                    'exception_class' =>
                        $exception::class,

                    'exception_message' =>
                        $exception->getMessage(),

                    'exception_file' =>
                        $exception->getFile(),

                    'exception_line' =>
                        $exception->getLine(),

                    'duration_milliseconds' =>
                        $result['duration_milliseconds']
                ]
            );


            return $result;
        }
    }


    private function resolveDatabasePath(): string
    {
        $databasePath =
            trim(
                $this->databasePath
            );


        if ($databasePath === '') {

            throw new RuntimeException(
                'The SQLite database path is not configured.'
            );
        }


        if (
            !is_file(
                $databasePath
            )
        ) {
            throw new RuntimeException(
                'The configured SQLite database does not exist: '
                .
                $databasePath
            );
        }


        if (
            !is_readable(
                $databasePath
            )
        ) {
            throw new RuntimeException(
                'The configured SQLite database is not readable: '
                .
                $databasePath
            );
        }


        $realPath =
            realpath(
                $databasePath
            );


        if ($realPath === false) {

            throw new RuntimeException(
                'The configured SQLite database path could not be resolved.'
            );
        }


        return $realPath;
    }


    private function scalar(
        PDO $database,
        string $sql
    ): mixed
    {
        $statement =
            $database->query(
                $sql
            );


        if ($statement === false) {

            throw new RuntimeException(
                'A database health query could not be executed.'
            );
        }


        return
            $statement->fetchColumn();
    }
}
