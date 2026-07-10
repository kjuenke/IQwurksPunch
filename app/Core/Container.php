<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use App\Repositories\AuditRepository;
use App\Repositories\EmployeeRepository;
use App\Services\AuditService;
use App\Services\EmployeeService;
use App\Repositories\PunchRepository;
use App\Services\PunchService;

class Container
{
    private static ?PDO $db = null;

    private static ?EmployeeRepository $employeeRepository = null;
    private static ?AuditRepository $auditRepository = null;

    private static ?EmployeeService $employeeService = null;
    private static ?AuditService $auditService = null;
    private static ?PunchRepository $punchRepository = null;
    private static ?PunchService $punchService = null;

    public static function db(): PDO
    {
        if (self::$db === null) {
            self::$db = Database::connection();
        }

        return self::$db;
    }

    public static function employeeRepository(): EmployeeRepository
    {
        if (self::$employeeRepository === null) {
            self::$employeeRepository = new EmployeeRepository(
                self::db()
            );
        }

        return self::$employeeRepository;
    }

    public static function auditRepository(): AuditRepository
    {
        if (self::$auditRepository === null) {
            self::$auditRepository = new AuditRepository(
                self::db()
            );
        }

        return self::$auditRepository;
    }

    public static function employeeService(): EmployeeService
    {
        if (self::$employeeService === null) {
            self::$employeeService = new EmployeeService(
                self::employeeRepository()
            );
        }

        return self::$employeeService;
    }

    public static function auditService(): AuditService
    {
        if (self::$auditService === null) {
            self::$auditService = new AuditService(
                self::auditRepository()
            );
        }

        return self::$auditService;
    }

    public static function punchRepository(): PunchRepository
    {
        if (self::$punchRepository === null) {

            self::$punchRepository = new PunchRepository(
                self::db()
            );

        }

        return self::$punchRepository;
    }


    public static function punchService(): PunchService
    {
        if (self::$punchService === null) {

            self::$punchService = new PunchService(
                self::punchRepository()
            );

        }

        return self::$punchService;
    }

}

