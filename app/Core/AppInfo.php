<?php
declare(strict_types=1);

namespace App\Core;

class AppInfo
{
    public static function name(): string
    {
        return 'IQwurksPunch';
    }


    public static function version(): string
    {
        return trim(
            file_get_contents(
                __DIR__ . '/../../VERSION'
            )
        );
    }


    public static function company(): string
    {
        return 'IQwurks';
    }
}
