<?php
declare(strict_types=1);

namespace App\Core;

use App\Logging\LoggerFactory;
use App\Logging\LoggerInterface;
use App\Repositories\AuditRepository;
use App\Repositories\EmployeeRepository;
use App\Repositories\PunchRepository;
use App\Services\AuditService;
use App\Services\EmployeeService;
use App\Services\PunchService;
use App\Services\PunchReportService;
use App\Repositories\EmailRepository;
use App\Repositories\CompanySettingsRepository;
use App\Services\CompanySettingsService;
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

    private static ?EmployeeService $employeeService = null;

    private static ?AuditService $auditService = null;

    private static ?PunchService $punchService = null;

    private static ?EmailRepository $emailRepository = null;

    private static ?PunchReportService $punchReportService = null;

    private static ?CompanySettingsRepository $companySettingsRepository = null;

    private static ?CompanySettingsService $companySettingsService = null;

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
                    self::punchRepository()
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

    public static function clear(): void
    {
        self::$db = null;

        self::$loggers = [];

        self::$employeeRepository = null;

        self::$auditRepository = null;

        self::$punchRepository = null;

        self::$employeeService = null;

        self::$auditService = null;

        self::$punchService = null;

        self::$punchReportService = null;

        self::$emailRepository = null;

        self::$companySettingsRepository = null;

        self::$companySettingsService = null;
    }
}
