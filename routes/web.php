<?php
declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\EmailController;
use App\Controllers\EmailHistoryController;
use App\Controllers\EmployeeController;
use App\Controllers\HomeController;
use App\Controllers\KioskController;
use App\Controllers\LaborRulesController;
use App\Controllers\NotificationRecipientController;
use App\Controllers\PayrollController;
use App\Controllers\PayrollExportController;
use App\Controllers\PayrollPeriodController;
use App\Controllers\PunchCorrectionController;
use App\Controllers\ReportController;
use App\Controllers\ReportEmailController;
use App\Controllers\SettingsController;
use App\Controllers\SetupController;


/*
|--------------------------------------------------------------------------
| Home
|--------------------------------------------------------------------------
*/

$home =
    new HomeController();

$router->get(
    '/',
    [$home, 'index']
);


/*
|--------------------------------------------------------------------------
| Initial Setup
|--------------------------------------------------------------------------
*/

$setup =
    new SetupController();

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

$auth =
    new AuthController();

$router->get(
    '/login',
    [$auth, 'login']
);

$router->post(
    '/login',
    [$auth, 'authenticate']
);

$router->post(
    '/logout',
    [$auth, 'logout']
);


/*
|--------------------------------------------------------------------------
| Dashboard
|--------------------------------------------------------------------------
*/

$dashboard =
    new DashboardController();

$router->get(
    '/dashboard',
    [$dashboard, 'index']
);


/*
|--------------------------------------------------------------------------
| Employees
|--------------------------------------------------------------------------
*/

$employees =
    new EmployeeController();

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

$router->post(
    '/employees/activate',
    [$employees, 'activate']
);

$router->post(
    '/employees/deactivate',
    [$employees, 'deactivate']
);

$router->post(
    '/employees/delete',
    [$employees, 'delete']
);


/*
|--------------------------------------------------------------------------
| Employee Punch Corrections
|--------------------------------------------------------------------------
*/

$punchCorrections =
    new PunchCorrectionController();

$router->get(
    '/employees/punches/{id}',
    [$punchCorrections, 'index']
);

$router->get(
    '/employees/punches/{id}/create',
    [$punchCorrections, 'create']
);

$router->post(
    '/employees/punches/{id}/create',
    [$punchCorrections, 'store']
);

$router->get(
    '/employees/punches/edit/{id}',
    [$punchCorrections, 'edit']
);

$router->post(
    '/employees/punches/edit/{id}',
    [$punchCorrections, 'update']
);

$router->post(
    '/employees/punches/delete/{id}',
    [$punchCorrections, 'delete']
);


/*
|--------------------------------------------------------------------------
| Employee Kiosk
|--------------------------------------------------------------------------
*/

$kiosk =
    new KioskController();

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
| Punch Reports
|--------------------------------------------------------------------------
*/

$reports =
    new ReportController();

$router->get(
    '/reports/punches',
    [$reports, 'punches']
);


/*
|--------------------------------------------------------------------------
| Payroll Reports
|--------------------------------------------------------------------------
*/

$payroll =
    new PayrollController();

$router->get(
    '/reports/payroll',
    [$payroll, 'daily']
);

$router->get(
    '/reports/payroll/weekly',
    [$payroll, 'weekly']
);

$router->get(
    '/reports/payroll/workspace',
    [$payroll, 'workspace']
);


/*
|--------------------------------------------------------------------------
| Payroll Review Periods
|--------------------------------------------------------------------------
*/

$payrollPeriods =
    new PayrollPeriodController();

$router->get(
    '/payroll-periods',
    [$payrollPeriods, 'index']
);

$router->get(
    '/payroll-periods/create',
    [$payrollPeriods, 'create']
);

$router->post(
    '/payroll-periods/create',
    [$payrollPeriods, 'store']
);

$router->get(
    '/payroll-periods/{id}',
    [$payrollPeriods, 'show']
);

$router->post(
    '/payroll-periods/{id}/begin-review',
    [$payrollPeriods, 'beginReview']
);

$router->post(
    '/payroll-periods/{id}/return-open',
    [$payrollPeriods, 'returnToOpen']
);

$router->post(
    '/payroll-periods/{id}/approve',
    [$payrollPeriods, 'approve']
);

$router->post(
    '/payroll-periods/{id}/lock',
    [$payrollPeriods, 'lock']
);

$router->post(
    '/payroll-periods/{id}/reopen',
    [$payrollPeriods, 'reopen']
);

$router->post(
    '/payroll-periods/{id}/notes',
    [$payrollPeriods, 'addNote']
);

$router->post(
    '/payroll-periods/{id}/exceptions/refresh',
    [$payrollPeriods, 'refreshExceptions']
);

$router->post(
    '/payroll-periods/{id}/exceptions/resolve',
    [$payrollPeriods, 'resolveException']
);

$router->post(
    '/payroll-periods/{id}/exceptions/accept',
    [$payrollPeriods, 'acceptException']
);


/*
|--------------------------------------------------------------------------
| Payroll Exports
|--------------------------------------------------------------------------
*/

$payrollExports =
    new PayrollExportController();

$router->get(
    '/reports/payroll/export/csv',
    [$payrollExports, 'dailyCsv']
);

$router->get(
    '/reports/payroll/export/pdf',
    [$payrollExports, 'dailyPdf']
);

$router->get(
    '/reports/payroll/weekly/export/csv',
    [$payrollExports, 'weeklyCsv']
);

$router->get(
    '/reports/payroll/weekly/export/pdf',
    [$payrollExports, 'weeklyPdf']
);

$router->get(
    '/reports/payroll/workspace/export/csv',
    [$payrollExports, 'workspaceCsv']
);

$router->get(
    '/reports/payroll/workspace/export/pdf',
    [$payrollExports, 'workspacePdf']
);

$router->get(
    '/reports/payroll/workspace/export/time-card/pdf',
    [$payrollExports, 'timeCardPdf']
);


/*
|--------------------------------------------------------------------------
| Email Reports
|--------------------------------------------------------------------------
*/

$email =
    new EmailController();

$router->get(
    '/reports/email-test',
    [$email, 'test']
);


$reportEmail =
    new ReportEmailController();

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
| Email History
|--------------------------------------------------------------------------
*/

$emailHistory =
    new EmailHistoryController();

$router->get(
    '/reports/email-history',
    [$emailHistory, 'index']
);


/*
|--------------------------------------------------------------------------
| Notification Center
|--------------------------------------------------------------------------
*/

$notifications =
    new NotificationRecipientController();

$router->get(
    '/admin/notifications',
    [$notifications, 'index']
);

$router->post(
    '/admin/notifications/create',
    [$notifications, 'create']
);

$router->post(
    '/admin/notifications/update',
    [$notifications, 'update']
);

$router->post(
    '/admin/notifications/activate',
    [$notifications, 'activate']
);

$router->post(
    '/admin/notifications/deactivate',
    [$notifications, 'deactivate']
);

$router->post(
    '/admin/notifications/delete',
    [$notifications, 'delete']
);


/*
|--------------------------------------------------------------------------
| Company Settings
|--------------------------------------------------------------------------
*/

$settings =
    new SettingsController();

$router->get(
    '/admin/settings',
    [$settings, 'index']
);

$router->post(
    '/admin/settings',
    [$settings, 'update']
);


/*
|--------------------------------------------------------------------------
| Labor Rules
|--------------------------------------------------------------------------
*/

$laborRules =
    new LaborRulesController();

$router->get(
    '/admin/labor-rules',
    [$laborRules, 'index']
);

$router->post(
    '/admin/labor-rules',
    [$laborRules, 'update']
);
