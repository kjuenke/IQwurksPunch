<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuditRepository;

class AuditService
{
    private AuditRepository $audit;


    public function __construct(
        AuditRepository $audit
    )
    {
        $this->audit = $audit;
    }

    public function log(
        string $action,
        string $details,
        ?int $userId = null
    ): void
    {
        file_put_contents(
            __DIR__ . '/../../storage/logs/audit-debug.log',
            date('Y-m-d H:i:s') .
            " | " .
            $action .
            " | " .
            $details .
            PHP_EOL,
            FILE_APPEND
        );

        $this->audit->create(
            $action,
            $details,
            $userId
        );
    }
}
