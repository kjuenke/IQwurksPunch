<?php
declare(strict_types=1);

use App\Core\Container;
use App\Repositories\UserRepository;
use App\Services\AuthGuardService;
use App\Services\MaintenanceModeService;

require_once __DIR__
    .
    '/../vendor/autoload.php';


$maintenanceConfig =
    require __DIR__
    .
    '/../config/maintenance.php';


$maintenance =
    new MaintenanceModeService(
        (string)$maintenanceConfig['file']
    );


if (
    $maintenance->isActive()
) {
    $status =
        $maintenance->status();


    $retryAfter =
        max(
            60,
            (int)(
                $maintenanceConfig['retry_after_seconds']
                ??
                300
            )
        );


    http_response_code(
        503
    );


    header(
        'Content-Type: text/html; charset=UTF-8'
    );


    header(
        'Retry-After: '
        .
        $retryAfter
    );


    header(
        'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
    );


    $reason =
        htmlspecialchars(
            (string)(
                $status['reason']
                ??
                'Scheduled maintenance is in progress.'
            ),
            ENT_QUOTES
            |
            ENT_SUBSTITUTE,
            'UTF-8'
        );


    $startedAt =
        htmlspecialchars(
            (string)(
                $status['started_at_display']
                ??
                $status['started_at']
                ??
                'Not recorded'
            ),
            ENT_QUOTES
            |
            ENT_SUBSTITUTE,
            'UTF-8'
        );


    echo
        '<!doctype html>'
        .
        '<html lang="en">'
        .
        '<head>'
        .
        '<meta charset="utf-8">'
        .
        '<meta name="viewport" content="width=device-width, initial-scale=1">'
        .
        '<meta name="robots" content="noindex,nofollow">'
        .
        '<title>IQwurksPunch Maintenance</title>'
        .
        '<style>'
        .
        'body{margin:0;min-height:100vh;display:grid;place-items:center;'
        .
        'font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;'
        .
        'background:#f3f4f6;color:#111827;padding:24px;box-sizing:border-box}'
        .
        '.card{width:min(620px,100%);background:#fff;border:1px solid #d1d5db;'
        .
        'border-radius:14px;padding:36px;box-sizing:border-box;'
        .
        'box-shadow:0 12px 30px rgba(0,0,0,.08)}'
        .
        'h1{margin:0 0 14px;font-size:30px}'
        .
        'p{line-height:1.6;margin:10px 0}'
        .
        '.reason{font-size:18px;font-weight:600}'
        .
        '.meta{margin-top:24px;padding-top:18px;border-top:1px solid #e5e7eb;'
        .
        'font-size:14px;color:#4b5563}'
        .
        '</style>'
        .
        '</head>'
        .
        '<body>'
        .
        '<main class="card">'
        .
        '<h1>IQwurksPunch is temporarily unavailable</h1>'
        .
        '<p>Maintenance is currently in progress. Please try again shortly.</p>'
        .
        '<p class="reason">'
        .
        $reason
        .
        '</p>'
        .
        '<div class="meta">Maintenance started: '
        .
        $startedAt
        .
        '</div>'
        .
        '</main>'
        .
        '</body>'
        .
        '</html>';


    exit;
}


$sessionPath =
    dirname(
        __DIR__
    )
    .
    '/storage/sessions';


if (
    is_dir(
        $sessionPath
    )
    &&
    is_writable(
        $sessionPath
    )
) {
    session_save_path(
        $sessionPath
    );
}


ini_set(
    'session.use_strict_mode',
    '1'
);


ini_set(
    'session.use_only_cookies',
    '1'
);


ini_set(
    'session.cookie_httponly',
    '1'
);


ini_set(
    'session.gc_maxlifetime',
    '28800'
);


$isHttps =
    (
        isset(
            $_SERVER['HTTPS']
        )
        &&
        strtolower(
            (string)$_SERVER['HTTPS']
        )
        !==
        'off'
    )
    ||
    strtolower(
        (string)(
            $_SERVER['HTTP_X_FORWARDED_PROTO']
            ??
            ''
        )
    )
    ===
    'https';


session_name(
    'IQWURKSPUNCHSESSID'
);


session_set_cookie_params(
    [
        'lifetime' =>
            0,

        'path' =>
            '/',

        'domain' =>
            '',

        'secure' =>
            $isHttps,

        'httponly' =>
            true,

        'samesite' =>
            'Lax'
    ]
);


session_start();


$app =
    require_once __DIR__
    .
    '/../bootstrap/app.php';


$guard =
    new AuthGuardService(
        new UserRepository(
            Container::db()
        )
    );


$guard->enforce(
    (string)(
        $_SERVER['REQUEST_URI']
        ??
        '/'
    ),
    (string)(
        $_SERVER['REQUEST_METHOD']
        ??
        'GET'
    )
);


$app->run();
