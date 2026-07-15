<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Mail Transport Configuration
|--------------------------------------------------------------------------
|
| Report recipients are managed through the IQwurksPunch Notification
| Center and stored in the notification_recipients database table.
|
| This file contains only SMTP transport and sender configuration.
|
*/

return [

    'host' =>
        'smtp.example.com',

    'port' =>
        587,

    'username' =>
        'SMTP_USERNAME',

    'password' =>
        'SMTP_PASSWORD',

    'encryption' =>
        'tls',

    'from_email' =>
        'timeclock@example.com',

    'from_name' =>
        'IQwurksPunch',
];
