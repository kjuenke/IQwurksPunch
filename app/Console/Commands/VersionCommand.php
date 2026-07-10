<?php
declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\CommandInterface;
use App\Core\AppInfo;

class VersionCommand implements CommandInterface
{
    public function name(): string
    {
        return 'version';
    }


    public function description(): string
    {
        return 'Display the installed application version.';
    }


    public function execute(
        array $arguments = []
    ): int
    {
        echo
            AppInfo::name()
            . ' '
            . AppInfo::version()
            . PHP_EOL;


        return 0;
    }
}
