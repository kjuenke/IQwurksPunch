<?php
declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\CommandInterface;
use App\Core\Container;
use App\Services\DatabaseHealthService;
use Throwable;

final class DatabaseCheckCommand implements CommandInterface
{
    private DatabaseHealthService $health;


    public function __construct(
        ?DatabaseHealthService $health = null
    )
    {
        if ($health !== null) {

            $this->health =
                $health;


            return;
        }


        $projectRoot =
            dirname(
                __DIR__,
                3
            );


        $databaseConfig =
            require $projectRoot
            .
            '/config/database.php';


        $databasePath =
            (string)(
                $databaseConfig['database']
                ??
                ''
            );


        $this->health =
            new DatabaseHealthService(
                $databasePath,
                Container::logger(
                    'database'
                )
            );
    }


    public function name(): string
    {
        return 'database:check';
    }


    public function description(): string
    {
        return 'Check active SQLite database health and integrity.';
    }


    public function execute(
        array $arguments = []
    ): int
    {
        if ($arguments !== []) {

            fwrite(
                STDERR,
                'The database:check command does not accept arguments.'
                .
                PHP_EOL
            );


            return 1;
        }


        try {

            $result =
                $this->health->inspect();


            echo
                'IQwurksPunch Database Health Check'
                .
                PHP_EOL;


            echo
                str_repeat(
                    '=',
                    40
                )
                .
                PHP_EOL;


            echo
                'Status: '
                .
                (
                    $result['healthy']
                        ? 'HEALTHY'
                        : 'FAILED'
                )
                .
                PHP_EOL;


            echo
                'Database: '
                .
                $result['database_path']
                .
                PHP_EOL;


            echo
                'Size: '
                .
                $this->formatBytes(
                    (int)$result['size_bytes']
                )
                .
                PHP_EOL;


            echo
                'Readable: '
                .
                $this->yesNo(
                    (bool)$result['database_readable']
                )
                .
                PHP_EOL;


            echo
                'Writable: '
                .
                $this->yesNo(
                    (bool)$result['database_writable']
                )
                .
                PHP_EOL;


            echo
                'Directory writable: '
                .
                $this->yesNo(
                    (bool)$result['directory_writable']
                )
                .
                PHP_EOL;


            echo
                'SQLite version: '
                .
                (
                    $result['sqlite_version']
                    ??
                    'unavailable'
                )
                .
                PHP_EOL;


            echo
                'Journal mode: '
                .
                (
                    $result['journal_mode']
                    ??
                    'unavailable'
                )
                .
                PHP_EOL;


            echo
                'Integrity check: '
                .
                (
                    $result['integrity_valid']
                        ? 'ok'
                        : 'failed'
                )
                .
                PHP_EOL;


            echo
                'Foreign-key violations: '
                .
                $result['foreign_key_violation_count']
                .
                PHP_EOL;


            echo
                'Application tables: '
                .
                $result['table_count']
                .
                PHP_EOL;


            echo
                'Applied migrations: '
                .
                (
                    $result['migration_count']
                    ??
                    'unavailable'
                )
                .
                PHP_EOL;


            echo
                'Page size: '
                .
                (
                    $result['page_size']
                    ??
                    'unavailable'
                )
                .
                ' bytes'
                .
                PHP_EOL;


            echo
                'Page count: '
                .
                (
                    $result['page_count']
                    ??
                    'unavailable'
                )
                .
                PHP_EOL;


            echo
                'Unused pages: '
                .
                (
                    $result['freelist_count']
                    ??
                    'unavailable'
                )
                .
                PHP_EOL;


            echo
                'Duration: '
                .
                number_format(
                    (float)$result['duration_milliseconds'],
                    2
                )
                .
                ' ms'
                .
                PHP_EOL;


            if (
                $result['missing_core_tables']
                !==
                []
            ) {
                echo
                    PHP_EOL
                    .
                    'Missing core tables:'
                    .
                    PHP_EOL;


                foreach (
                    $result['missing_core_tables']
                    as $table
                ) {
                    echo
                        '  - '
                        .
                        $table
                        .
                        PHP_EOL;
                }
            }


            if (
                $result['warnings']
                !==
                []
            ) {
                echo
                    PHP_EOL
                    .
                    'Warnings:'
                    .
                    PHP_EOL;


                foreach (
                    $result['warnings']
                    as $warning
                ) {
                    echo
                        '  - '
                        .
                        $warning
                        .
                        PHP_EOL;
                }
            }


            if (
                $result['errors']
                !==
                []
            ) {
                fwrite(
                    STDERR,
                    PHP_EOL
                    .
                    'Errors:'
                    .
                    PHP_EOL
                );


                foreach (
                    $result['errors']
                    as $error
                ) {
                    fwrite(
                        STDERR,
                        '  - '
                        .
                        $error
                        .
                        PHP_EOL
                    );
                }
            }


            if (!$result['healthy']) {

                return 1;
            }


            echo
                PHP_EOL
                .
                'Database health check completed successfully.'
                .
                PHP_EOL;


            return 0;

        } catch (Throwable $exception) {

            fwrite(
                STDERR,
                'Database health check failed: '
                .
                $exception->getMessage()
                .
                PHP_EOL
            );


            return 1;
        }
    }


    private function yesNo(
        bool $value
    ): string
    {
        return
            $value
                ? 'yes'
                : 'no';
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
