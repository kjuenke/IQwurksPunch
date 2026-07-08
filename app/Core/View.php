<?php
declare(strict_types=1);

namespace App\Core;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class View
{
    private Environment $twig;


    public function __construct()
    {
        $loader = new FilesystemLoader(
            __DIR__ . '/../Views'
        );

        $this->twig = new Environment(
            $loader,
            [
                'cache' => false,
                'debug' => true,
                'auto_reload' => true,
            ]
        );
    }


    public function render(
        string $template,
        array $data = []
    ): void
    {
        $data['appName'] =
            AppInfo::name();

        $data['appVersion'] =
            AppInfo::version();

        $data['companyName'] =
            AppInfo::company();

        echo $this->twig->render(
            $template,
            $data
        );
    }
}
