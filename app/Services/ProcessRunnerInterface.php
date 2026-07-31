<?php
declare(strict_types=1);

namespace App\Services;

interface ProcessRunnerInterface
{
    /**
     * @param array<int,string> $command
     * @param array<string,scalar|null> $environment
     *
     * @return array<string,mixed>
     */
    public function run(
        array $command,
        ?string $workingDirectory = null,
        array $environment = [],
        ?float $timeoutSeconds = null
    ): array;
}
