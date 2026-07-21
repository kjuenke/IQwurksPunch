<?php
declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\CommandInterface;
use App\Services\MaintenanceModeService;
use Throwable;

final class MaintenanceStatusCommand implements CommandInterface
{
    private MaintenanceModeService $maintenance;


    public function __construct(
        ?MaintenanceModeService $maintenance = null
    )
    {
        $this->maintenance =
            $maintenance
            ??
            $this->makeService();
    }


    public function name(): string
    {
        return 'maintenance:status';
    }


    public function description(): string
    {
        return 'Display the current web maintenance-mode status.';
    }


    public function execute(
        array $arguments = []
    ): int
    {
        if ($arguments !== []) {

            fwrite(
                STDERR,
                'The maintenance:status command does not accept arguments.'
                .
                PHP_EOL
            );


            return 1;
        }


        try {

            $status =
                $this->maintenance->status();


            if (!$status['active']) {

                echo
                    'Maintenance mode is INACTIVE.'
                    .
                    PHP_EOL;


                echo
                    'File: '
                    .
                    $status['file']
                    .
                    PHP_EOL;


                return 0;
            }


            echo
                'Maintenance mode is ACTIVE.'
                .
                PHP_EOL;


            echo
                'Reason: '
                .
                (
                    $status['reason']
                    ??
                    'Not recorded'
                )
                .
                PHP_EOL;


            echo
                'Started: '
                .
                (
                    $status['started_at_display']
                    ??
                    $status['started_at']
                    ??
                    'Not recorded'
                )
                .
                PHP_EOL;


            echo
                'Application version: '
                .
                (
                    $status['version']
                    ??
                    'Not recorded'
                )
                .
                PHP_EOL;


            echo
                'Process ID: '
                .
                (
                    $status['process_id']
                    ??
                    'Not recorded'
                )
                .
                PHP_EOL;


            echo
                'Hostname: '
                .
                (
                    $status['hostname']
                    ??
                    'Not recorded'
                )
                .
                PHP_EOL;


            echo
                'Metadata valid: '
                .
                (
                    (
                        $status['metadata_valid']
                        ??
                        false
                    )
                        ? 'yes'
                        : 'no'
                )
                .
                PHP_EOL;


            echo
                'File: '
                .
                $status['file']
                .
                PHP_EOL;


            return 0;

        } catch (Throwable $exception) {

            fwrite(
                STDERR,
                'Maintenance status could not be read: '
                .
                $exception->getMessage()
                .
                PHP_EOL
            );


            return 1;
        }
    }


    private function makeService(): MaintenanceModeService
    {
        $config =
            require dirname(
                __DIR__,
                3
            )
            .
            '/config/maintenance.php';


        return
            new MaintenanceModeService(
                (string)$config['file']
            );
    }
}
