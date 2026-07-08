<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;

abstract class Controller
{
    protected function render(
        string $template,
        array $data = []
    ): void
    {
        $view = new View();

        $view->render(
            $template,
            $data
        );
    }
}
