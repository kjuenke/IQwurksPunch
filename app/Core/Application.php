<?php
declare(strict_types=1);

namespace App\Core;

class Application
{
    private Router $router;

    public function setRouter(Router $router): void
    {
        $this->router = $router;
    }

    public function run(): void
    {
        $this->router->dispatch();
    }
}
