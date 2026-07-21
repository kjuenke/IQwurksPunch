<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class DatabaseBackupCatalogService
{
    private string $backupDirectory;


    public function __construct(
        string $backupDirectory
    )
    {
        $this->backupDirectory =
            rtrim(
                $backupDirectory,
                DIRECTORY_SEPARATOR
            );
    }


    /**
     * @return array<int,array<string,mixed>>
     */
    public function all(): array
    {
        if (
            !is_dir(
                $this->backupDirectory
            )
        ) {
            return [];
        }


        if (
            !is_readable(
                $this->backupDirectory
            )
        ) {
            throw new RuntimeException(
                'The backup directory is not readable: '
                .
                $this->backupDirectory
            );
        }


        $paths =
            glob(
                $this->backupDirectory
                .
                DIRECTORY_SEPARATOR
                .
                '*.sqlite'
            );


        if ($paths === false) {

            throw new RuntimeException(
                'The backup directory could not be scanned.'
            );
        }


        $backups = [];


        foreach ($paths as $path) {

            if (
                !is_file(
                    $path
                )
            ) {
                continue;
            }


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
                continue;
            }


            $realPath =
                realpath(
                    $path
                );


            if ($realPath === false) {

                continue;
            }


            $backups[] = [
                'filename' =>
                    basename(
                        $realPath
                    ),

                'path' =>
                    $realPath,

                'size_bytes' =>
                    $sizeBytes,

                'modified_timestamp' =>
                    $modifiedTimestamp,

                'modified_at' =>
                    date(
                        'Y-m-d H:i:s T',
                        $modifiedTimestamp
                    )
            ];
        }


        usort(
            $backups,
            static function (
                array $left,
                array $right
            ): int {
                $timestampComparison =
                    (
                        (int)$right['modified_timestamp']
                    )
                    <=>
                    (
                        (int)$left['modified_timestamp']
                    );


                if ($timestampComparison !== 0) {

                    return $timestampComparison;
                }


                return strcmp(
                    (string)$right['filename'],
                    (string)$left['filename']
                );
            }
        );


        return $backups;
    }


    public function directory(): string
    {
        $realPath =
            realpath(
                $this->backupDirectory
            );


        return
            $realPath !== false
                ? $realPath
                : $this->backupDirectory;
    }
}
