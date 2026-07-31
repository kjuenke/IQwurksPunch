<?php
declare(strict_types=1);

namespace App\Services;

interface UpgradeExecutionJournalInterface
{
    /**
     * @param array<string,mixed> $context
     *
     * @return array<string,mixed>
     */
    public function start(
        string $version,
        string $confirmation,
        array $context = []
    ): array;


    /**
     * @param array<string,mixed> $result
     *
     * @return array<string,mixed>
     */
    public function complete(
        string $executionId,
        array $result
    ): array;


    /**
     * @return array<string,mixed>
     */
    public function read(
        string $executionId
    ): array;


    /**
     * @return array<string,mixed>|null
     */
    public function latest(): ?array;
}
