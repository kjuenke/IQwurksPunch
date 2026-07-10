<?php
declare(strict_types=1);

namespace App\Console;

interface CommandInterface
{
    public function name(): string;


    public function description(): string;


    public function execute(
        array $arguments = []
    ): int;
}
