<?php
declare(strict_types=1);

namespace App\Services;

use App\Logging\LoggerInterface;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class DatabaseBackupRetentionService
{
    private DatabaseBackupCatalogService $catalog;

    private LoggerInterface $logger;


    public function __construct(
        DatabaseBackupCatalogService $catalog,
        LoggerInterface $logger
    )
    {
        $this->catalog =
            $catalog;


        $this->logger =
            $logger;
    }


    /**
     * @return array<string,mixed>
     */
    public function plan(
        int $keep
    ): array
    {
        $this->validateKeepCount(
            $keep
        );


        $backups =
            $this->catalog->all();


        $retained =
            array_slice(
                $backups,
                0,
                $keep
            );


        $removable =
            array_slice(
                $backups,
                $keep
            );


        $removableBytes =
            array_sum(
                array_map(
                    static fn (
                        array $backup
                    ): int =>
                        (int)(
                            $backup['size_bytes']
                            ??
                            0
                        ),
                    $removable
                )
            );


        return [
            'directory' =>
                $this->catalog->directory(),

            'retention_count' =>
                $keep,

            'total_count' =>
                count(
                    $backups
                ),

            'retained_count' =>
                count(
                    $retained
                ),

            'removable_count' =>
                count(
                    $removable
                ),

            'removable_bytes' =>
                $removableBytes,

            'retained' =>
                $retained,

            'removable' =>
                $removable
        ];
    }


    /**
     * @return array<string,mixed>
     */
    public function prune(
        int $keep
    ): array
    {
        $plan =
            $this->plan(
                $keep
            );


        $deleted = [];

        $failed = [];


        $backupDirectory =
            realpath(
                (string)$plan['directory']
            );


        if (
            $plan['removable_count']
            >
            0
            &&
            $backupDirectory === false
        ) {
            throw new RuntimeException(
                'The backup directory could not be resolved safely.'
            );
        }


        $this->logger->info(
            'Database backup retention started.',
            [
                'backup_directory' =>
                    $plan['directory'],

                'retention_count' =>
                    $keep,

                'available_count' =>
                    $plan['total_count'],

                'removable_count' =>
                    $plan['removable_count']
            ]
        );


        foreach (
            $plan['removable']
            as $backup
        ) {
            $path =
                (string)(
                    $backup['path']
                    ??
                    ''
                );


            try {

                $realPath =
                    realpath(
                        $path
                    );


                if ($realPath === false) {

                    throw new RuntimeException(
                        'The backup file no longer exists.'
                    );
                }


                if (
                    $backupDirectory === false
                    ||
                    realpath(
                        dirname(
                            $realPath
                        )
                    )
                    !==
                    $backupDirectory
                ) {
                    throw new RuntimeException(
                        'The backup file is outside the configured backup directory.'
                    );
                }


                if (
                    strtolower(
                        pathinfo(
                            $realPath,
                            PATHINFO_EXTENSION
                        )
                    )
                    !==
                    'sqlite'
                ) {
                    throw new RuntimeException(
                        'The backup file does not have a .sqlite extension.'
                    );
                }


                if (
                    !is_file(
                        $realPath
                    )
                ) {
                    throw new RuntimeException(
                        'The backup path is not a regular file.'
                    );
                }


                if (
                    !unlink(
                        $realPath
                    )
                ) {
                    throw new RuntimeException(
                        'The backup file could not be deleted.'
                    );
                }


                $deleted[] = [
                    ...$backup,

                    'deleted_at' =>
                        date(
                            'Y-m-d H:i:s T'
                        )
                ];


                $this->logger->info(
                    'Expired database backup deleted.',
                    [
                        'filename' =>
                            $backup['filename']
                            ??
                            basename(
                                $realPath
                            ),

                        'path' =>
                            $realPath,

                        'size_bytes' =>
                            $backup['size_bytes']
                            ??
                            0
                    ]
                );

            } catch (Throwable $exception) {

                $failed[] = [
                    ...$backup,

                    'error' =>
                        $exception->getMessage()
                ];


                $this->logger->error(
                    'Expired database backup could not be deleted.',
                    [
                        'filename' =>
                            $backup['filename']
                            ??
                            basename(
                                $path
                            ),

                        'path' =>
                            $path,

                        'exception_class' =>
                            $exception::class,

                        'exception_message' =>
                            $exception->getMessage()
                    ]
                );
            }
        }


        $result = [
            ...$plan,

            'deleted_count' =>
                count(
                    $deleted
                ),

            'failed_count' =>
                count(
                    $failed
                ),

            'deleted' =>
                $deleted,

            'failed' =>
                $failed
        ];


        $this->logger->info(
            'Database backup retention completed.',
            [
                'retention_count' =>
                    $keep,

                'deleted_count' =>
                    $result['deleted_count'],

                'failed_count' =>
                    $result['failed_count']
            ]
        );


        return $result;
    }


    private function validateKeepCount(
        int $keep
    ): void
    {
        if ($keep < 1) {

            throw new InvalidArgumentException(
                'Backup retention must keep at least one backup.'
            );
        }


        if ($keep > 10000) {

            throw new InvalidArgumentException(
                'Backup retention cannot exceed 10,000 backups.'
            );
        }
    }
}
