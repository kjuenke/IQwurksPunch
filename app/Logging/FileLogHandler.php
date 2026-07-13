<?php
declare(strict_types=1);

namespace App\Logging;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

class FileLogHandler implements LogHandlerInterface
{
    private string $logDirectory;


    public function __construct(
        string $logDirectory
    )
    {
        $this->logDirectory =
            rtrim(
                $logDirectory,
                DIRECTORY_SEPARATOR
            );


        if (
            !is_dir(
                $this->logDirectory
            )
            &&
            !mkdir(
                $this->logDirectory,
                0775,
                true
            )
            &&
            !is_dir(
                $this->logDirectory
            )
        ) {
            throw new RuntimeException(
                'Unable to create log directory: '
                .
                $this->logDirectory
            );
        }


        if (
            !is_writable(
                $this->logDirectory
            )
        ) {
            throw new RuntimeException(
                'Log directory is not writable: '
                .
                $this->logDirectory
            );
        }
    }


    public function write(
        string $level,
        string $channel,
        string $message,
        array $context = []
    ): void
    {
        $safeChannel =
            preg_replace(
                '/[^a-zA-Z0-9_-]/',
                '_',
                $channel
            );


        if (
            empty(
                $safeChannel
            )
        ) {
            $safeChannel =
                'application';
        }


        $path =
            $this->logDirectory
            .
            DIRECTORY_SEPARATOR
            .
            $safeChannel
            .
            '.log';


        $timestamp =
            new DateTimeImmutable(
                'now',
                new DateTimeZone('UTC')
            );


        $line =
            sprintf(
                '[%s] %s %s: %s',
                $timestamp->format(
                    'Y-m-d H:i:s'
                ),
                strtoupper(
                    $level
                ),
                $safeChannel,
                $message
            );


        if (!empty($context)) {

            $encodedContext =
                json_encode(
                    $context,
                    JSON_UNESCAPED_SLASHES
                    |
                    JSON_UNESCAPED_UNICODE
                    |
                    JSON_PARTIAL_OUTPUT_ON_ERROR
                );


            if ($encodedContext !== false) {

                $line .=
                    ' '
                    .
                    $encodedContext;
            }
        }


        $line .=
            PHP_EOL;


        $written =
            file_put_contents(
                $path,
                $line,
                FILE_APPEND
                |
                LOCK_EX
            );


        if ($written === false) {

            throw new RuntimeException(
                'Unable to write log file: '
                .
                $path
            );
        }
    }
}
