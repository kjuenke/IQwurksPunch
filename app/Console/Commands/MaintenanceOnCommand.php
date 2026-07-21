<?php
declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\CommandInterface;
use App\Core\Container;
use App\Services\MaintenanceModeService;
use Throwable;

final class MaintenanceOnCommand implements CommandInterface
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
        return 'maintenance:on';
    }


    public function description(): string
    {
        return 'Enable web maintenance mode with an optional reason.';
    }


    public function execute(
        array $arguments = []
    ): int
    {
        try {

            $reason =
                trim(
                    implode(
                        ' ',
                        array_map(
                            static fn (
                                mixed $argument
                            ): string =>
                                (string)$argument,
                            $arguments
                        )
                    )
                );


            $status =
                $this->maintenance->activate(
                    $reason
                );


            Container::logger(
                'maintenance'
            )->warning(
                'Application maintenance mode enabled.',
                [
                    'reason' =>
                        $status['reason'],

                    'started_at' =>
                        $status['started_at'],

                    'process_id' =>
                        $status['process_id']
                ]
            );


            echo
                'Maintenance mode is now ACTIVE.'
                .
                PHP_EOL;


            echo
                'Reason: '
                .
                $status['reason']
                .
                PHP_EOL;


            echo
                'Started: '
                .
                $status['started_at_display']
                .
                PHP_EOL;


            echo
                'File: '
                .
                $this->maintenance->file()
                .
                PHP_EOL;


            return 0;

        } catch (Throwable $exception) {

            fwrite(
                STDERR,
                'Maintenance mode could not be enabled: '
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
