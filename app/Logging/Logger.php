<?php
declare(strict_types=1);

namespace App\Logging;

class Logger implements LoggerInterface
{
    private LogHandlerInterface $handler;

    private string $channel;


    public function __construct(
        LogHandlerInterface $handler,
        string $channel = 'application'
    )
    {
        $this->handler =
            $handler;


        $this->channel =
            $channel;
    }


    public function emergency(
        string $message,
        array $context = []
    ): void
    {
        $this->log(
            LogLevel::EMERGENCY,
            $message,
            $context
        );
    }


    public function alert(
        string $message,
        array $context = []
    ): void
    {
        $this->log(
            LogLevel::ALERT,
            $message,
            $context
        );
    }


    public function critical(
        string $message,
        array $context = []
    ): void
    {
        $this->log(
            LogLevel::CRITICAL,
            $message,
            $context
        );
    }


    public function error(
        string $message,
        array $context = []
    ): void
    {
        $this->log(
            LogLevel::ERROR,
            $message,
            $context
        );
    }


    public function warning(
        string $message,
        array $context = []
    ): void
    {
        $this->log(
            LogLevel::WARNING,
            $message,
            $context
        );
    }


    public function notice(
        string $message,
        array $context = []
    ): void
    {
        $this->log(
            LogLevel::NOTICE,
            $message,
            $context
        );
    }


    public function info(
        string $message,
        array $context = []
    ): void
    {
        $this->log(
            LogLevel::INFO,
            $message,
            $context
        );
    }


    public function debug(
        string $message,
        array $context = []
    ): void
    {
        $this->log(
            LogLevel::DEBUG,
            $message,
            $context
        );
    }


    private function log(
        string $level,
        string $message,
        array $context
    ): void
    {
        $this->handler->write(
            $level,
            $this->channel,
            $message,
            $context
        );
    }
}
