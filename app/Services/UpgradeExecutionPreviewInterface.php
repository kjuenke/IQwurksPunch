<?php
declare(strict_types=1);

namespace App\Services;

interface UpgradeExecutionPreviewInterface
{
    /**
     * @return array<string,mixed>
     */
    public function preview(): array;
}
