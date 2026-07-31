<?php
declare(strict_types=1);

namespace App\Services;

interface UpgradeExecutionOperationsInterface
{
    /**
     * @return array<string,mixed>
     */
    public function maintenanceStatus(): array;


    /**
     * @return array<string,mixed>
     */
    public function activateMaintenance(
        string $reason
    ): array;


    /**
     * @return array<string,mixed>
     */
    public function deactivateMaintenance(): array;


    /**
     * @return array<string,mixed>
     */
    public function createVerifiedBackup(): array;


    /**
     * @return array<string,mixed>
     */
    public function applyPermissions(): array;
}
