<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;

class HomeController
{
    public function index(): void
    {
        echo "
        <h1>" . Config::get('name') . "</h1>
        <p>Open-source employee time clock and payroll system.</p>
        ";
    }
}
