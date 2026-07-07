<?php
declare(strict_types=1);

namespace App\Core;

class Config
{
    private static array $items = [];

    public static function load(): void
    {
        self::$items = require __DIR__ . '/../../config/app.php';
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$items[$key] ?? $default;
    }
}
