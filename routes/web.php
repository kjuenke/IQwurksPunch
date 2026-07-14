<?php
declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\EmailController;
use App\Controllers\EmailHistoryController;
use App\Controllers\EmployeeController;
use App\Controllers\HomeController;
use App\Controllers\KioskController;
use App\Controllers\ReportController;
use App\Controllers\ReportEmailController;
use App\Controllers\SettingsController;
use App\Controllers\SetupController;


/*
|--------------------------------------------------------------------------
| Home
|--------------------------------------------------------------------------
*/

$home = new HomeController();

$router->get(
    '/',
    [$home, 'index']
);


/*
|--------------------------------------------------------------------------
| Initial Setup
|--------------------------------------------------------------------------
*/

$setup = new SetupController();

$router->get(
    '/setup',
    [$setup, 'index']
);

$router->post(
    '/setup',
    [$setup, 'create']
);


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

$auth = new AuthController();

$router->get(
    '/login',
    [$auth, 'login']
);

$router->post(
    '/login',
    [$auth, 'authenticate']
);

$router->get(
    '/logout',
    [$auth, 'logout']
);


/*
|--------------------------------------------------------------------------
| Dashboard
|--------------------------------------------------------------------------
*/

$dashboard = new DashboardController();

$router->get(
    '/dashboard',
    [$dashboard, 'index']
);


/*
|--------------------------------------------------------------------------
| Employees
|--------------------------------------------------------------------------
*/

$employees = new EmployeeController();

$router->get(
    '/employees',
    [$employees, 'index']
);

$router->get(
    '/employees/create',
    [$employees, 'create']
);

$router->post(
    '/employees/create',
    [$employees, 'store']
);

$router->get(
    '/employees/edit/{id}',
    [$employees, 'edit']
);

$router->post(
    '/employees/edit/{id}',
    [$employees, 'update']
);

$router->get(
    '/employees/pin/{id}',
    [$employees, 'pin']
);

$router->post(
    '/employees/pin/{id}',
    [$employees, 'updatePin']
);


/*
|--------------------------------------------------------------------------
| Employee Kiosk
|--------------------------------------------------------------------------
*/

$kiosk = new KioskController();

$router->get(
    '/kiosk',
    [$kiosk, 'index']
);

$router->post(
    '/kiosk/authenticate',
    [$kiosk, 'authenticate']
);

$router->post(
    '/kiosk/verify-pin',
    [$kiosk, 'verifyPin']
);

$router->post(
    '/kiosk/punch',
    [$kiosk, 'punch']
);


/*
|--------------------------------------------------------------------------
| Reports
|--------------------------------------------------------------------------
*/

$reports = new ReportController();

$router->get(
    '/reports/punches',
    [$reports, 'punches']
);

$router->get(
    '/reports/payroll',
    [$reports, 'payroll']
);

$router->get(
    '/reports/payroll/weekly',
    [$reports, 'weeklyPayroll']
);


/*
|--------------------------------------------------------------------------
| Email Reports
|--------------------------------------------------------------------------
*/

$email = new EmailController();

$router->get(
    '/reports/email-test',
    [$email, 'test']
);


$reportEmail = new ReportEmailController();

$router->get(
    '/reports/email',
    [$reportEmail, 'index']
);

$router->post(
    '/reports/email/send-daily',
    [$reportEmail, 'sendDaily']
);

$router->post(
    '/reports/email/schedule',
    [$reportEmail, 'updateSchedule']
);


/*
|--------------------------------------------------------------------------
| Legacy Manual Email Route
|--------------------------------------------------------------------------
|
| Kept temporarily so any existing bookmark continues to work.
|
*/

$router->get(
    '/reports/send-daily-email',
    [$reportEmail, 'sendDaily']
);


/*
|--------------------------------------------------------------------------
| Email History
|--------------------------------------------------------------------------
*/

$emailHistory = new EmailHistoryController();

$router->get(
    '/reports/email-history',
    [$emailHistory, 'index']
);


/*
|--------------------------------------------------------------------------
| Company Settings
|--------------------------------------------------------------------------
*/

$settings = new SettingsController();

$router->get(
    '/admin/settings',
    [$settings, 'index']
);

$router->post(
    '/admin/settings',
    [$settings, 'update']
);
