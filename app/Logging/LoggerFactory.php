<?php
declare(strict_types=1);

namespace App\Logging;

use RuntimeException;

final class LoggerFactory
{
    public static function create(
        string $channel = 'application'
    ): LoggerInterface
    {
        $configurationPath =
            dirname(__DIR__, 2)
            .
            '/config/logging.php';


        if (
            !is_file(
                $configurationPath
            )
        ) {
            throw new RuntimeException(
                'Logging configuration file was not found: '
                .
                $configurationPath
            );
        }


        $configuration =
            require $configurationPath;


        if (
            !is_array(
                $configuration
            )
        ) {
            throw new RuntimeException(
                'Logging configuration must return an array.'
            );
        }


        $logDirectory =
            $configuration['directory']
            ??
            null;


        if (
            !is_string(
                $logDirectory
            )
            ||
            trim(
                $logDirectory
            ) === ''
        ) {
            throw new RuntimeException(
                'Logging directory is not configured.'
            );
        }


        $handler =
            new FileLogHandler(
                $logDirectory
            );


        return new Logger(
            $handler,
            $channel
        );
    }
}
