<?php
declare(strict_types=1);

namespace App\Core;

use App\Exports\DailyPayrollCsvExporter;
use App\Exports\Pdf\PayrollRegisterPdfExporter;
use App\Exports\WeeklyPayrollCsvExporter;
use App\Logging\LoggerFactory;
use App\Logging\LoggerInterface;
use App\Repositories\AuditRepository;
use App\Repositories\CompanySettingsRepository;
use App\Repositories\EmailRepository;
use App\Repositories\EmployeeRepository;
use App\Repositories\NotificationRecipientRepository;
use App\Repositories\PunchRepository;
use App\Repositories\ReportScheduleRepository;
use App\Repositories\UserRepository;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\CompanySettingsService;
use App\Services\DashboardService;
use App\Services\EmployeeService;
use App\Services\MailService;
use App\Services\NotificationRecipientService;
use App\Services\PunchReportService;
use App\Services\PunchService;
use App\Services\ReportEmailService;
use App\Services\ReportScheduleService;
use PDO;

final class Container
{
    private static ?PDO $db = null;

    /**
     * @var array<string,LoggerInterface>
     */
    private static array $loggers = [];

    private static ?EmployeeRepository $employeeRepository = null;

    private static ?AuditRepository $auditRepository = null;

    private static ?PunchRepository $punchRepository = null;

    private static ?EmailRepository $emailRepository = null;

    private static ?CompanySettingsRepository $companySettingsRepository = null;

    private static ?ReportScheduleRepository $reportScheduleRepository = null;

    private static ?UserRepository $userRepository = null;

    private static ?NotificationRecipientRepository $notificationRecipientRepository = null;

    private static ?EmployeeService $employeeService = null;

    private static ?AuditService $auditService = null;

    private static ?PunchService $punchService = null;

    private static ?PunchReportService $punchReportService = null;

    private static ?CompanySettingsService $companySettingsService = null;

    private static ?ReportScheduleService $reportScheduleService = null;

    private static ?ReportEmailService $reportEmailService = null;

    private static ?MailService $mailService = null;

    private static ?AuthService $authService = null;

    private static ?NotificationRecipientService $notificationRecipientService = null;

    private static ?DashboardService $dashboardService = null;

    private static ?DailyPayrollCsvExporter $dailyPayrollCsvExporter = null;

    private static ?WeeklyPayrollCsvExporter $weeklyPayrollCsvExporter = null;

    private static ?PayrollRegisterPdfExporter $payrollRegisterPdfExporter = null;


    public static function db(): PDO
    {
        if (self::$db === null) {

            self::$db =
                Database::connection();
        }


        return self::$db;
    }


    public static function logger(
        string $channel = 'application'
    ): LoggerInterface
    {
        if (
            !isset(
                self::$loggers[$channel]
            )
        ) {
            self::$loggers[$channel] =
                LoggerFactory::create(
                    $channel
                );
        }


        return self::$loggers[$channel];
    }


    public static function employeeRepository(): EmployeeRepository
    {
        if (self::$employeeRepository === null) {

            self::$employeeRepository =
                new EmployeeRepository(
                    self::db()
                );
        }


        return self::$employeeRepository;
    }


    public static function auditRepository(): AuditRepository
    {
        if (self::$auditRepository === null) {

            self::$auditRepository =
                new AuditRepository(
                    self::db()
                );
        }


        return self::$auditRepository;
    }


    public static function punchRepository(): PunchRepository
    {
        if (self::$punchRepository === null) {

            self::$punchRepository =
                new PunchRepository(
                    self::db()
                );
        }


        return self::$punchRepository;
    }


    public static function emailRepository(): EmailRepository
    {
        if (self::$emailRepository === null) {

            self::$emailRepository =
                new EmailRepository(
                    self::db()
                );
        }


        return self::$emailRepository;
    }


    public static function companySettingsRepository(): CompanySettingsRepository
    {
        if (self::$companySettingsRepository === null) {

            self::$companySettingsRepository =
                new CompanySettingsRepository(
                    self::db()
                );
        }


        return self::$companySettingsRepository;
    }


    public static function reportScheduleRepository(): ReportScheduleRepository
    {
        if (self::$reportScheduleRepository === null) {

            self::$reportScheduleRepository =
                new ReportScheduleRepository(
                    self::db()
                );
        }


        return self::$reportScheduleRepository;
    }


    public static function userRepository(): UserRepository
    {
        if (self::$userRepository === null) {

            self::$userRepository =
                new UserRepository(
                    self::db()
                );
        }


        return self::$userRepository;
    }


    public static function notificationRecipientRepository(): NotificationRecipientRepository
    {
        if (self::$notificationRecipientRepository === null) {

            self::$notificationRecipientRepository =
                new NotificationRecipientRepository(
                    self::db()
                );
        }


        return self::$notificationRecipientRepository;
    }


    public static function employeeService(): EmployeeService
    {
        if (self::$employeeService === null) {

            self::$employeeService =
                new EmployeeService(
                    self::employeeRepository()
                );
        }


        return self::$employeeService;
    }


    public static function auditService(): AuditService
    {
        if (self::$auditService === null) {

            self::$auditService =
                new AuditService(
                    self::auditRepository()
                );
        }


        return self::$auditService;
    }


    public static function punchService(): PunchService
    {
        if (self::$punchService === null) {

            self::$punchService =
                new PunchService(
                    self::punchRepository()
                );
        }


        return self::$punchService;
    }


    public static function punchReportService(): PunchReportService
    {
        if (self::$punchReportService === null) {

            self::$punchReportService =
                new PunchReportService(
                    self::punchRepository(),
                    self::companySettingsRepository()
                );
        }


        return self::$punchReportService;
    }


    public static function companySettingsService(): CompanySettingsService
    {
        if (self::$companySettingsService === null) {

            self::$companySettingsService =
                new CompanySettingsService(
                    self::companySettingsRepository()
                );
        }


        return self::$companySettingsService;
    }


    public static function reportScheduleService(): ReportScheduleService
    {
        if (self::$reportScheduleService === null) {

            self::$reportScheduleService =
                new ReportScheduleService(
                    self::reportScheduleRepository()
                );
        }


        return self::$reportScheduleService;
    }


    public static function reportEmailService(): ReportEmailService
    {
        if (self::$reportEmailService === null) {

            self::$reportEmailService =
                new ReportEmailService();
        }


        return self::$reportEmailService;
    }


    public static function mailService(): MailService
    {
        if (self::$mailService === null) {

            self::$mailService =
                new MailService();
        }


        return self::$mailService;
    }


    public static function authService(): AuthService
    {
        if (self::$authService === null) {

            self::$authService =
                new AuthService(
                    self::userRepository()
                );
        }


        return self::$authService;
    }


    public static function notificationRecipientService(): NotificationRecipientService
    {
        if (self::$notificationRecipientService === null) {

            self::$notificationRecipientService =
                new NotificationRecipientService(
                    self::notificationRecipientRepository()
                );
        }


        return self::$notificationRecipientService;
    }


    public static function dashboardService(): DashboardService
    {
        if (self::$dashboardService === null) {

            self::$dashboardService =
                new DashboardService(
                    self::employeeService(),
                    self::punchService(),
                    self::punchReportService(),
                    self::emailRepository(),
                    self::reportScheduleService(),
                    self::companySettingsService()
                );
        }


        return self::$dashboardService;
    }


    public static function dailyPayrollCsvExporter(): DailyPayrollCsvExporter
    {
        if (self::$dailyPayrollCsvExporter === null) {

            self::$dailyPayrollCsvExporter =
                new DailyPayrollCsvExporter();
        }


        return self::$dailyPayrollCsvExporter;
    }


    public static function weeklyPayrollCsvExporter(): WeeklyPayrollCsvExporter
    {
        if (self::$weeklyPayrollCsvExporter === null) {

            self::$weeklyPayrollCsvExporter =
                new WeeklyPayrollCsvExporter();
        }


        return self::$weeklyPayrollCsvExporter;
    }


    public static function payrollRegisterPdfExporter(): PayrollRegisterPdfExporter
    {
        if (self::$payrollRegisterPdfExporter === null) {

            self::$payrollRegisterPdfExporter =
                new PayrollRegisterPdfExporter();
        }


        return self::$payrollRegisterPdfExporter;
    }


    public static function clear(): void
    {
        self::$db = null;

        self::$loggers = [];

        self::$employeeRepository = null;

        self::$auditRepository = null;

        self::$punchRepository = null;

        self::$emailRepository = null;

        self::$companySettingsRepository = null;

        self::$reportScheduleRepository = null;

        self::$userRepository = null;

        self::$notificationRecipientRepository = null;

        self::$employeeService = null;

        self::$auditService = null;

        self::$punchService = null;

        self::$punchReportService = null;

        self::$companySettingsService = null;

        self::$reportScheduleService = null;

        self::$reportEmailService = null;

        self::$mailService = null;

        self::$authService = null;

        self::$notificationRecipientService = null;

        self::$dashboardService = null;

        self::$dailyPayrollCsvExporter = null;

        self::$weeklyPayrollCsvExporter = null;

        self::$payrollRegisterPdfExporter = null;
    }
}
