<?php
declare(strict_types=1);

namespace App\Logging;

interface LogHandlerInterface
{
    public function write(
        string $level,
        string $channel,
        string $message,
        array $context = []
    ): void;
}
