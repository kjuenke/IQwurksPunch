<?php
declare(strict_types=1);

namespace App\Core;

use App\Logging\LoggerFactory;
use App\Logging\LoggerInterface;
use PDO;

final class Services
{
    /**
     * @var array<string,mixed>
     */
    private static array $instances = [];


    public static function database(): PDO
    {
        if (
            !isset(
                self::$instances['database']
            )
        ) {
            self::$instances['database'] =
                Database::connection();
        }


        return self::$instances['database'];
    }


    public static function logger(
        string $channel = 'application'
    ): LoggerInterface
    {
        $key =
            'logger.'
            .
            $channel;


        if (
            !isset(
                self::$instances[$key]
            )
        ) {
            self::$instances[$key] =
                LoggerFactory::create(
                    $channel
                );
        }


        return self::$instances[$key];
    }


    public static function clear(): void
    {
        self::$instances = [];
    }
}
