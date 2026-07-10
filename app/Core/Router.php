<?php
declare(strict_types=1);

namespace App\Core;

class Router
{
    private array $routes = [];


    public function get(
        string $path,
        callable $handler
    ): void
    {
        $this->routes['GET'][] = [
            'path' => $path,
            'handler' => $handler
        ];
    }


    public function post(
        string $path,
        callable $handler
    ): void
    {
        $this->routes['POST'][] = [
            'path' => $path,
            'handler' => $handler
        ];
    }


    public function dispatch(): void
    {
        $uri = parse_url(
            $_SERVER['REQUEST_URI'],
            PHP_URL_PATH
        );

        $method = $_SERVER['REQUEST_METHOD'];


        foreach (
            $this->routes[$method] ?? []
            as $route
        ) {

            $pattern = preg_replace(
                '/\{[^\/]+\}/',
                '([0-9]+)',
                $route['path']
            );


            $pattern = '#^' . $pattern . '$#';


            if (
                preg_match(
                    $pattern,
                    $uri,
                    $matches
                )
            ) {

                array_shift($matches);


                call_user_func_array(
                    $route['handler'],
                    $matches
                );

                return;
            }
        }


        http_response_code(404);

        echo "404 - Page Not Found";
    }
}
