<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use RuntimeException;

final class Database
{
    private static ?PDO $connection = null;


    public static function connection(): PDO
    {
        if (self::$connection !== null) {

            return self::$connection;
        }


        $config =
            require __DIR__
            .
            '/../../config/database.php';


        $driver =
            trim(
                (string)(
                    $config['driver']
                    ??
                    ''
                )
            );


        $databasePath =
            trim(
                (string)(
                    $config['database']
                    ??
                    ''
                )
            );


        if ($driver !== 'sqlite') {

            throw new RuntimeException(
                'IQwurksPunch requires the SQLite database driver.'
            );
        }


        if ($databasePath === '') {

            throw new RuntimeException(
                'The SQLite database path is not configured.'
            );
        }


        /*
         * Runtime directories use the www-data group and setgid permissions.
         * This umask ensures SQLite WAL and SHM sidecars remain group-writable
         * when commands are executed by either www-data or an administrator.
         */
        umask(
            0007
        );


        $connection =
            new PDO(
                'sqlite:'
                .
                $databasePath
            );


        $connection->setAttribute(
            PDO::ATTR_ERRMODE,
            PDO::ERRMODE_EXCEPTION
        );


        $connection->setAttribute(
            PDO::ATTR_DEFAULT_FETCH_MODE,
            PDO::FETCH_ASSOC
        );


        $connection->exec(
            'PRAGMA foreign_keys = ON'
        );


        $connection->exec(
            'PRAGMA busy_timeout = 10000'
        );


        $journalStatement =
            $connection->query(
                'PRAGMA journal_mode = WAL'
            );


        if ($journalStatement === false) {

            throw new RuntimeException(
                'SQLite WAL mode could not be requested.'
            );
        }


        $journalMode =
            strtolower(
                trim(
                    (string)$journalStatement
                        ->fetchColumn()
                )
            );


        if ($journalMode !== 'wal') {

            throw new RuntimeException(
                'SQLite did not enter WAL mode. Current mode: '
                .
                (
                    $journalMode !== ''
                        ? $journalMode
                        : 'unknown'
                )
            );
        }


        $connection->exec(
            'PRAGMA synchronous = NORMAL'
        );


        $connection->exec(
            'PRAGMA wal_autocheckpoint = 1000'
        );


        $foreignKeys =
            (int)$connection
                ->query(
                    'PRAGMA foreign_keys'
                )
                ->fetchColumn();


        if ($foreignKeys !== 1) {

            throw new RuntimeException(
                'SQLite foreign-key enforcement could not be enabled.'
            );
        }


        self::$connection =
            $connection;


        return self::$connection;
    }
}
