<?php
declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\CommandInterface;
use App\Core\Container;
use App\Services\MaintenanceModeService;
use Throwable;

final class MaintenanceOffCommand implements CommandInterface
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
        return 'maintenance:off';
    }


    public function description(): string
    {
        return 'Disable web maintenance mode.';
    }


    public function execute(
        array $arguments = []
    ): int
    {
        if ($arguments !== []) {

            fwrite(
                STDERR,
                'The maintenance:off command does not accept arguments.'
                .
                PHP_EOL
            );


            return 1;
        }


        try {

            $result =
                $this->maintenance->deactivate();


            if (!$result['removed']) {

                echo
                    'Maintenance mode was already INACTIVE.'
                    .
                    PHP_EOL;


                return 0;
            }


            Container::logger(
                'maintenance'
            )->info(
                'Application maintenance mode disabled.',
                [
                    'previous_reason' =>
                        $result['reason']
                        ??
                        null,

                    'previous_started_at' =>
                        $result['started_at']
                        ??
                        null,

                    'removed_at' =>
                        $result['removed_at']
                ]
            );


            echo
                'Maintenance mode is now INACTIVE.'
                .
                PHP_EOL;


            echo
                'Removed: '
                .
                $result['removed_at']
                .
                PHP_EOL;


            return 0;

        } catch (Throwable $exception) {

            fwrite(
                STDERR,
                'Maintenance mode could not be disabled: '
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
