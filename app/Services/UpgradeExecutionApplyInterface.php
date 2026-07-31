<?php
declare(strict_types=1);

namespace App\Services;

interface UpgradeExecutionApplyInterface
{
    /**
     * @return array<string,mixed>
     */
    public function apply(
        string $confirmation
    ): array;
}
