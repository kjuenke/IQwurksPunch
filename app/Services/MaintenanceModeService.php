<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\AppInfo;
use RuntimeException;
use Throwable;

final class MaintenanceModeService
{
    private string $maintenanceFile;


    public function __construct(
        string $maintenanceFile
    )
    {
        $this->maintenanceFile =
            $maintenanceFile;
    }


    public function isActive(): bool
    {
        return
            is_file(
                $this->maintenanceFile
            );
    }


    /**
     * @return array<string,mixed>
     */
    public function activate(
        string $reason = 'Scheduled maintenance'
    ): array
    {
        $reason =
            trim(
                $reason
            );


        if ($reason === '') {

            $reason =
                'Scheduled maintenance';
        }


        if (
            mb_strlen(
                $reason
            )
            >
            500
        ) {
            throw new RuntimeException(
                'The maintenance reason cannot exceed 500 characters.'
            );
        }


        $directory =
            dirname(
                $this->maintenanceFile
            );


        if (
            !is_dir(
                $directory
            )
        ) {
            $created =
                mkdir(
                    $directory,
                    0770,
                    true
                );


            if (
                !$created
                &&
                !is_dir(
                    $directory
                )
            ) {
                throw new RuntimeException(
                    'The maintenance directory could not be created: '
                    .
                    $directory
                );
            }
        }


        if (
            !is_writable(
                $directory
            )
        ) {
            throw new RuntimeException(
                'The maintenance directory is not writable: '
                .
                $directory
            );
        }


        $metadata = [
            'active' =>
                true,

            'reason' =>
                $reason,

            'started_at' =>
                date(
                    DATE_ATOM
                ),

            'started_at_display' =>
                date(
                    'Y-m-d H:i:s T'
                ),

            'process_id' =>
                getmypid(),

            'hostname' =>
                gethostname()
                ?:
                'unknown',

            'application' =>
                AppInfo::name(),

            'version' =>
                AppInfo::version()
        ];


        $json =
            json_encode(
                $metadata,
                JSON_PRETTY_PRINT
                |
                JSON_UNESCAPED_SLASHES
                |
                JSON_THROW_ON_ERROR
            )
            .
            PHP_EOL;


        $temporaryFile =
            tempnam(
                $directory,
                'maintenance-'
            );


        if ($temporaryFile === false) {

            throw new RuntimeException(
                'A temporary maintenance file could not be created.'
            );
        }


        try {

            $bytesWritten =
                file_put_contents(
                    $temporaryFile,
                    $json,
                    LOCK_EX
                );


            if (
                $bytesWritten === false
                ||
                $bytesWritten !== strlen(
                    $json
                )
            ) {
                throw new RuntimeException(
                    'The maintenance metadata could not be written completely.'
                );
            }


            @chmod(
                $temporaryFile,
                0640
            );


            if (
                !rename(
                    $temporaryFile,
                    $this->maintenanceFile
                )
            ) {
                throw new RuntimeException(
                    'Maintenance mode could not be activated atomically.'
                );
            }


            clearstatcache(
                true,
                $this->maintenanceFile
            );


            if (
                !is_file(
                    $this->maintenanceFile
                )
            ) {
                throw new RuntimeException(
                    'The maintenance file was not created.'
                );
            }


            return $metadata;

        } catch (Throwable $exception) {

            if (
                is_file(
                    $temporaryFile
                )
            ) {
                @unlink(
                    $temporaryFile
                );
            }


            throw $exception;
        }
    }


    /**
     * @return array<string,mixed>
     */
    public function deactivate(): array
    {
        $status =
            $this->status();


        if (!$status['active']) {

            return [
                ...$status,

                'removed' =>
                    false
            ];
        }


        if (
            !unlink(
                $this->maintenanceFile
            )
        ) {
            throw new RuntimeException(
                'The maintenance file could not be removed: '
                .
                $this->maintenanceFile
            );
        }


        clearstatcache(
            true,
            $this->maintenanceFile
        );


        if (
            is_file(
                $this->maintenanceFile
            )
        ) {
            throw new RuntimeException(
                'Maintenance mode remains active after removal was attempted.'
            );
        }


        return [
            ...$status,

            'active' =>
                false,

            'removed' =>
                true,

            'removed_at' =>
                date(
                    'Y-m-d H:i:s T'
                )
        ];
    }


    /**
     * @return array<string,mixed>
     */
    public function status(): array
    {
        if (!$this->isActive()) {

            return [
                'active' =>
                    false,

                'file' =>
                    $this->maintenanceFile
            ];
        }


        $contents =
            file_get_contents(
                $this->maintenanceFile
            );


        if ($contents === false) {

            return [
                'active' =>
                    true,

                'file' =>
                    $this->maintenanceFile,

                'reason' =>
                    'Maintenance metadata could not be read.',

                'metadata_valid' =>
                    false
            ];
        }


        try {

            $metadata =
                json_decode(
                    $contents,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );


            if (!is_array($metadata)) {

                throw new RuntimeException(
                    'Maintenance metadata is not an object.'
                );
            }


            return [
                ...$metadata,

                'active' =>
                    true,

                'file' =>
                    $this->maintenanceFile,

                'metadata_valid' =>
                    true
            ];

        } catch (Throwable $exception) {

            return [
                'active' =>
                    true,

                'file' =>
                    $this->maintenanceFile,

                'reason' =>
                    'Maintenance metadata is invalid.',

                'metadata_valid' =>
                    false,

                'metadata_error' =>
                    $exception->getMessage()
            ];
        }
    }


    public function file(): string
    {
        return $this->maintenanceFile;
    }
}
