<?php
declare(strict_types=1);

return [
    'directory' =>
        dirname(
            __DIR__
        )
        .
        '/storage/backups',

    'retention_count' =>
        30,

    'lock_file' =>
        dirname(
            __DIR__
        )
        .
        '/storage/cache/backup.lock',
];
