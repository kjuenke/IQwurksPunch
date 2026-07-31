<?php
declare(strict_types=1);

namespace App\Services;

use JsonException;
use RuntimeException;
use Throwable;

final class UpgradeExecutionJournalService implements UpgradeExecutionJournalInterface
{
    private string $directory;


    public function __construct(
        ?string $directory = null
    )
    {
        $directory =
            $directory
            ??
            dirname(
                __DIR__,
                2
            )
            .
            '/storage/logs/upgrade-executions';


        $directory =
            rtrim(
                trim(
                    $directory
                ),
                DIRECTORY_SEPARATOR
            );


        if ($directory === '') {
            throw new RuntimeException(
                'The upgrade execution journal directory cannot be empty.'
            );
        }


        if (
            str_contains(
                $directory,
                "\0"
            )
        ) {
            throw new RuntimeException(
                'The upgrade execution journal directory cannot contain null bytes.'
            );
        }


        $this->directory =
            $directory;
    }


    /**
     * @param array<string,mixed> $context
     *
     * @return array<string,mixed>
     */
    public function start(
        string $version,
        string $confirmation,
        array $context = []
    ): array
    {
        $version =
            trim(
                $version
            );


        if ($version === '') {
            throw new RuntimeException(
                'The journaled upgrade version cannot be empty.'
            );
        }


        $confirmation =
            trim(
                $confirmation
            );


        if ($confirmation === '') {
            throw new RuntimeException(
                'The journaled upgrade confirmation cannot be empty.'
            );
        }


        $this->ensureDirectory();


        $executionId =
            $this->generateExecutionId();


        $record = [
            'execution_id' =>
                $executionId,

            'status' =>
                'running',

            'application_version' =>
                $version,

            'confirmation' =>
                $confirmation,

            'started_at' =>
                date(
                    DATE_ATOM
                ),

            'completed_at' =>
                null,

            'successful' =>
                null,

            'context' =>
                $context,

            'result' =>
                null
        ];


        $this->writeRecord(
            $executionId,
            $record
        );


        $this->writeLatest(
            $record
        );


        return $record;
    }


    /**
     * @param array<string,mixed> $result
     *
     * @return array<string,mixed>
     */
    public function complete(
        string $executionId,
        array $result
    ): array
    {
        $executionId =
            $this->validateExecutionId(
                $executionId
            );


        $record =
            $this->read(
                $executionId
            );


        $successful =
            (
                $result['successful']
                ??
                false
            )
            ===
            true;


        $record['status'] =
            $successful
                ? 'completed'
                : 'failed';


        $record['completed_at'] =
            date(
                DATE_ATOM
            );


        $record['successful'] =
            $successful;


        $record['result'] =
            $result;


        $this->writeRecord(
            $executionId,
            $record
        );


        $this->writeLatest(
            $record
        );


        return $record;
    }


    /**
     * @return array<string,mixed>
     */
    public function read(
        string $executionId
    ): array
    {
        $executionId =
            $this->validateExecutionId(
                $executionId
            );


        $path =
            $this->recordPath(
                $executionId
            );


        return
            $this->readFile(
                $path,
                'Upgrade execution record does not exist: '
                .
                $executionId
            );
    }


    /**
     * @return array<string,mixed>|null
     */
    public function latest(): ?array
    {
        $path =
            $this->latestPath();


        if (!is_file($path)) {
            return null;
        }


        return
            $this->readFile(
                $path,
                'The latest upgrade execution record could not be read.'
            );
    }


    private function ensureDirectory(): void
    {
        if (!is_dir($this->directory)) {

            try {

                $created =
                    mkdir(
                        $this->directory,
                        02770,
                        true
                    );

            } catch (Throwable $exception) {

                throw new RuntimeException(
                    'The upgrade execution journal directory could not be created: '
                    .
                    $exception->getMessage(),
                    0,
                    $exception
                );
            }


            if (!$created) {
                throw new RuntimeException(
                    'The upgrade execution journal directory could not be created.'
                );
            }
        }


        if (!is_writable($this->directory)) {
            throw new RuntimeException(
                'The upgrade execution journal directory is not writable: '
                .
                $this->directory
            );
        }


        if (
            !chmod(
                $this->directory,
                02770
            )
        ) {
            throw new RuntimeException(
                'The upgrade execution journal directory permissions could not be applied.'
            );
        }
    }


    private function generateExecutionId(): string
    {
        return
            date(
                'Ymd_His'
            )
            .
            '_'
            .
            bin2hex(
                random_bytes(
                    8
                )
            );
    }


    private function validateExecutionId(
        string $executionId
    ): string
    {
        $executionId =
            trim(
                $executionId
            );


        if (
            $executionId === ''
            ||
            preg_match(
                '/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/',
                $executionId
            )
            !==
            1
        ) {
            throw new RuntimeException(
                'The upgrade execution ID is invalid.'
            );
        }


        return $executionId;
    }


    /**
     * @param array<string,mixed> $record
     */
    private function writeRecord(
        string $executionId,
        array $record
    ): void
    {
        $executionId =
            $this->validateExecutionId(
                $executionId
            );


        $this->ensureDirectory();


        $this->atomicWrite(
            $this->recordPath(
                $executionId
            ),
            $record
        );
    }


    /**
     * @param array<string,mixed> $record
     */
    private function writeLatest(
        array $record
    ): void
    {
        $this->ensureDirectory();


        $this->atomicWrite(
            $this->latestPath(),
            $record
        );
    }


    /**
     * @param array<string,mixed> $record
     */
    private function atomicWrite(
        string $path,
        array $record
    ): void
    {
        try {

            $json =
                json_encode(
                    $record,
                    JSON_THROW_ON_ERROR
                    |
                    JSON_PRETTY_PRINT
                    |
                    JSON_UNESCAPED_SLASHES
                );

        } catch (JsonException $exception) {

            throw new RuntimeException(
                'The upgrade execution record could not be encoded: '
                .
                $exception->getMessage(),
                0,
                $exception
            );
        }


        $temporaryPath =
            $path
            .
            '.tmp.'
            .
            bin2hex(
                random_bytes(
                    6
                )
            );


        try {

            $bytes =
                file_put_contents(
                    $temporaryPath,
                    $json
                    .
                    PHP_EOL,
                    LOCK_EX
                );


            if ($bytes === false) {
                throw new RuntimeException(
                    'The temporary upgrade execution record could not be written.'
                );
            }


            if (
                !chmod(
                    $temporaryPath,
                    0640
                )
            ) {
                throw new RuntimeException(
                    'The temporary upgrade execution record permissions could not be applied.'
                );
            }


            if (
                !rename(
                    $temporaryPath,
                    $path
                )
            ) {
                throw new RuntimeException(
                    'The upgrade execution record could not be installed atomically.'
                );
            }


            clearstatcache(
                true,
                $path
            );

        } catch (Throwable $exception) {

            if (is_file($temporaryPath)) {
                @unlink(
                    $temporaryPath
                );
            }


            if ($exception instanceof RuntimeException) {
                throw $exception;
            }


            throw new RuntimeException(
                'The upgrade execution record could not be written: '
                .
                $exception->getMessage(),
                0,
                $exception
            );
        }
    }


    /**
     * @return array<string,mixed>
     */
    private function readFile(
        string $path,
        string $missingMessage
    ): array
    {
        if (
            !is_file(
                $path
            )
            ||
            !is_readable(
                $path
            )
        ) {
            throw new RuntimeException(
                $missingMessage
            );
        }


        $contents =
            file_get_contents(
                $path
            );


        if ($contents === false) {
            throw new RuntimeException(
                'The upgrade execution record contents could not be read.'
            );
        }


        try {

            $record =
                json_decode(
                    $contents,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );

        } catch (JsonException $exception) {

            throw new RuntimeException(
                'The upgrade execution record contains invalid JSON: '
                .
                $exception->getMessage(),
                0,
                $exception
            );
        }


        if (!is_array($record)) {
            throw new RuntimeException(
                'The upgrade execution record did not contain an object.'
            );
        }


        return $record;
    }


    private function recordPath(
        string $executionId
    ): string
    {
        return
            $this->directory
            .
            DIRECTORY_SEPARATOR
            .
            $executionId
            .
            '.json';
    }


    private function latestPath(): string
    {
        return
            $this->directory
            .
            DIRECTORY_SEPARATOR
            .
            'latest.json';
    }
}
