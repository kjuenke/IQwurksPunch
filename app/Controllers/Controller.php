<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Flash;

abstract class Controller
{
    protected function render(
        string $template,
        array $data = []
    ): void
    {
        $data['flash'] =
            Flash::get();


        $view = new View();


        $view->render(
            $template,
            $data
        );
    }
}
