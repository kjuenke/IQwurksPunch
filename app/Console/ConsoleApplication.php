<?php
declare(strict_types=1);

namespace App\Console;

use Throwable;

class ConsoleApplication
{
    private string $name;

    private string $version;

    /**
     * @var array<string, CommandInterface>
     */
    private array $commands = [];


    public function __construct(
        string $name,
        string $version
    )
    {
        $this->name = $name;
        $this->version = $version;
    }


    public function register(
        CommandInterface $command
    ): void
    {
        $this->commands[
            $command->name()
        ] = $command;
    }


    public function run(
        array $arguments
    ): int
    {
        $commandName =
            $arguments[1]
            ?? 'help';


        if (
            $commandName === 'help'
            ||
            $commandName === '--help'
            ||
            $commandName === '-h'
        ) {
            $this->displayHelp();

            return 0;
        }


        if (!isset($this->commands[$commandName])) {

            fwrite(
                STDERR,
                "Unknown command: {$commandName}"
                . PHP_EOL
                . PHP_EOL
            );


            $this->displayHelp();

            return 1;
        }


        $commandArguments =
            array_slice(
                $arguments,
                2
            );


        try {

            return $this
                ->commands[$commandName]
                ->execute(
                    $commandArguments
                );

        } catch (Throwable $e) {

            fwrite(
                STDERR,
                'Command failed: '
                . $e->getMessage()
                . PHP_EOL
            );


            return 1;
        }
    }


    private function displayHelp(): void
    {
        echo
            $this->name
            . ' '
            . $this->version
            . PHP_EOL
            . PHP_EOL;


        echo
            'Usage:'
            . PHP_EOL;


        echo
            '  php iqwurks <command>'
            . PHP_EOL
            . PHP_EOL;


        echo
            'Available commands:'
            . PHP_EOL;


        echo
            '  help'
            . str_repeat(' ', 18)
            . 'Display this help screen.'
            . PHP_EOL;


        ksort(
            $this->commands
        );


        foreach ($this->commands as $command) {

            echo
                '  '
                . str_pad(
                    $command->name(),
                    22
                )
                . $command->description()
                . PHP_EOL;
        }
    }
}
