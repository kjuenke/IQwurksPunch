<?php
declare(strict_types=1);

namespace App\Console;

use Closure;
use RuntimeException;

final class LazyCommand implements CommandInterface
{
    private string $commandName;

    private string $commandDescription;

    private Closure $factory;


    public function __construct(
        string $commandName,
        string $commandDescription,
        callable $factory
    )
    {
        $this->commandName =
            $commandName;


        $this->commandDescription =
            $commandDescription;


        $this->factory =
            Closure::fromCallable(
                $factory
            );
    }


    public function name(): string
    {
        return
            $this->commandName;
    }


    public function description(): string
    {
        return
            $this->commandDescription;
    }


    public function execute(
        array $arguments = []
    ): int
    {
        $command =
            (
                $this->factory
            )();


        if (
            !$command
            instanceof
            CommandInterface
        ) {
            throw new RuntimeException(
                'The command factory for '
                .
                $this->commandName
                .
                ' did not return a valid console command.'
            );
        }


        if (
            $command->name()
            !==
            $this->commandName
        ) {
            throw new RuntimeException(
                'The command factory returned '
                .
                $command->name()
                .
                ' instead of '
                .
                $this->commandName
                .
                '.'
            );
        }


        return
            $command->execute(
                $arguments
            );
    }
}
