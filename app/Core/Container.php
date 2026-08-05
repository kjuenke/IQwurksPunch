<?php
declare(strict_types=1);

namespace App\Core;

use App\Exports\DailyPayrollCsvExporter;
use App\Exports\PayrollWorkspaceCsvExporter;
use App\Exports\Pdf\DailyPayrollPdfExporter;
use App\Exports\Pdf\EmployeeTimeCardPdfExporter;
use App\Exports\Pdf\PayrollRegisterPdfExporter;
use App\Exports\Pdf\PayrollWorkspacePdfExporter;
use App\Exports\WeeklyPayrollCsvExporter;
use App\Logging\LoggerFactory;
use App\Logging\LoggerInterface;
use App\Repositories\AuditRepository;
use App\Repositories\CompanySettingsRepository;
use App\Repositories\EmailRepository;
use App\Repositories\EmployeeRepository;
use App\Repositories\LaborRulesRepository;
use App\Repositories\NotificationRecipientRepository;
use App\Repositories\PayrollExceptionResolutionRepository;
use App\Repositories\PayrollPeriodHistoryRepository;
use App\Repositories\PayrollPeriodRepository;
use App\Repositories\PayrollReviewNoteRepository;
use App\Repositories\PunchCorrectionHistoryRepository;
use App\Repositories\PunchRepository;
use App\Repositories\ReportScheduleRepository;
use App\Repositories\UserRepository;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\UserManagementService;
use App\Services\CompanySettingsService;
use App\Services\DashboardService;
use App\Services\EmployeeService;
use App\Services\LaborRulesService;
use App\Services\MailService;
use App\Services\NotificationRecipientService;
use App\Services\PayrollApprovalService;
use App\Services\PayrollExceptionResolutionService;
use App\Services\PayrollExceptionService;
use App\Services\PayrollPeriodProtectionService;
use App\Services\PayrollPeriodService;
use App\Services\PayrollReportPeriodMetadataService;
use App\Services\PayrollReviewNoteService;
use App\Services\PayrollWorkspaceService;
use App\Services\PunchCorrectionService;
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

    private static ?PunchCorrectionHistoryRepository $punchCorrectionHistoryRepository = null;

    private static ?EmailRepository $emailRepository = null;

    private static ?CompanySettingsRepository $companySettingsRepository = null;

    private static ?ReportScheduleRepository $reportScheduleRepository = null;

    private static ?UserRepository $userRepository = null;

    private static ?NotificationRecipientRepository $notificationRecipientRepository = null;

    private static ?LaborRulesRepository $laborRulesRepository = null;

    private static ?PayrollPeriodRepository $payrollPeriodRepository = null;

    private static ?PayrollPeriodHistoryRepository $payrollPeriodHistoryRepository = null;

    private static ?PayrollReviewNoteRepository $payrollReviewNoteRepository = null;

    private static ?PayrollExceptionResolutionRepository $payrollExceptionResolutionRepository = null;

    private static ?EmployeeService $employeeService = null;

    private static ?AuditService $auditService = null;

    private static ?PunchService $punchService = null;

    private static ?PunchCorrectionService $punchCorrectionService = null;

    private static ?PunchReportService $punchReportService = null;

    private static ?CompanySettingsService $companySettingsService = null;

    private static ?ReportScheduleService $reportScheduleService = null;

    private static ?ReportEmailService $reportEmailService = null;

    private static ?MailService $mailService = null;

    private static ?AuthService $authService = null;

    private static ?NotificationRecipientService $notificationRecipientService = null;

    private static ?DashboardService $dashboardService = null;

    private static ?PayrollWorkspaceService $payrollWorkspaceService = null;

    private static ?LaborRulesService $laborRulesService = null;

    private static ?PayrollPeriodService $payrollPeriodService = null;

    private static ?PayrollApprovalService $payrollApprovalService = null;

    private static ?PayrollPeriodProtectionService $payrollPeriodProtectionService = null;

    private static ?PayrollReviewNoteService $payrollReviewNoteService = null;

    private static ?PayrollExceptionService $payrollExceptionService = null;

    private static ?PayrollExceptionResolutionService $payrollExceptionResolutionService = null;

    private static ?PayrollReportPeriodMetadataService $payrollReportPeriodMetadataService = null;

    private static ?DailyPayrollCsvExporter $dailyPayrollCsvExporter = null;

    private static ?WeeklyPayrollCsvExporter $weeklyPayrollCsvExporter = null;

    private static ?PayrollWorkspaceCsvExporter $payrollWorkspaceCsvExporter = null;

    private static ?DailyPayrollPdfExporter $dailyPayrollPdfExporter = null;

    private static ?PayrollRegisterPdfExporter $payrollRegisterPdfExporter = null;

    private static ?PayrollWorkspacePdfExporter $payrollWorkspacePdfExporter = null;

    private static ?EmployeeTimeCardPdfExporter $employeeTimeCardPdfExporter = null;


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


    public static function punchCorrectionHistoryRepository(): PunchCorrectionHistoryRepository
    {
        if (self::$punchCorrectionHistoryRepository === null) {

            self::$punchCorrectionHistoryRepository =
                new PunchCorrectionHistoryRepository(
                    self::db()
                );
        }


        return self::$punchCorrectionHistoryRepository;
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


    public static function laborRulesRepository(): LaborRulesRepository
    {
        if (self::$laborRulesRepository === null) {

            self::$laborRulesRepository =
                new LaborRulesRepository(
                    self::db()
                );
        }


        return self::$laborRulesRepository;
    }


    public static function payrollPeriodRepository(): PayrollPeriodRepository
    {
        if (self::$payrollPeriodRepository === null) {

            self::$payrollPeriodRepository =
                new PayrollPeriodRepository(
                    self::db()
                );
        }


        return self::$payrollPeriodRepository;
    }


    public static function payrollPeriodHistoryRepository(): PayrollPeriodHistoryRepository
    {
        if (self::$payrollPeriodHistoryRepository === null) {

            self::$payrollPeriodHistoryRepository =
                new PayrollPeriodHistoryRepository(
                    self::db()
                );
        }


        return self::$payrollPeriodHistoryRepository;
    }


    public static function payrollReviewNoteRepository(): PayrollReviewNoteRepository
    {
        if (self::$payrollReviewNoteRepository === null) {

            self::$payrollReviewNoteRepository =
                new PayrollReviewNoteRepository(
                    self::db()
                );
        }


        return self::$payrollReviewNoteRepository;
    }


    public static function payrollExceptionResolutionRepository(): PayrollExceptionResolutionRepository
    {
        if (self::$payrollExceptionResolutionRepository === null) {

            self::$payrollExceptionResolutionRepository =
                new PayrollExceptionResolutionRepository(
                    self::db()
                );
        }


        return self::$payrollExceptionResolutionRepository;
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


    public static function punchCorrectionService(): PunchCorrectionService
    {
        if (self::$punchCorrectionService === null) {

            $companySettings =
                self::companySettingsService()
                    ->get();


            $companyTimezone =
                trim(
                    (string)(
                        $companySettings['timezone']
                        ??
                        date_default_timezone_get()
                    )
                );


            if ($companyTimezone === '') {

                $companyTimezone =
                    date_default_timezone_get();
            }


            self::$punchCorrectionService =
                new PunchCorrectionService(
                    self::db(),
                    self::punchRepository(),
                    self::punchCorrectionHistoryRepository(),
                    self::employeeRepository(),
                    $companyTimezone,
                    self::payrollPeriodProtectionService()
                );
        }


        return self::$punchCorrectionService;
    }


    public static function punchReportService(): PunchReportService
    {
        if (self::$punchReportService === null) {

            self::$punchReportService =
                new PunchReportService(
                    self::punchRepository(),
                    self::companySettingsRepository(),
                    null,
                    self::laborRulesService()
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


    public static function userManagementService(): UserManagementService
    {
        return new UserManagementService(
            self::userRepository()
        );
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


    public static function laborRulesService(): LaborRulesService
    {
        if (self::$laborRulesService === null) {

            self::$laborRulesService =
                new LaborRulesService(
                    self::laborRulesRepository()
                );
        }


        return self::$laborRulesService;
    }


    public static function payrollPeriodService(): PayrollPeriodService
    {
        if (self::$payrollPeriodService === null) {

            self::$payrollPeriodService =
                new PayrollPeriodService(
                    self::db(),
                    self::payrollPeriodRepository(),
                    self::payrollPeriodHistoryRepository()
                );
        }


        return self::$payrollPeriodService;
    }


    public static function payrollApprovalService(): PayrollApprovalService
    {
        if (self::$payrollApprovalService === null) {

            self::$payrollApprovalService =
                new PayrollApprovalService(
                    self::db(),
                    self::payrollPeriodRepository(),
                    self::payrollPeriodHistoryRepository(),
                    self::payrollExceptionResolutionRepository()
                );
        }


        return self::$payrollApprovalService;
    }


    public static function payrollPeriodProtectionService(): PayrollPeriodProtectionService
    {
        if (self::$payrollPeriodProtectionService === null) {

            self::$payrollPeriodProtectionService =
                new PayrollPeriodProtectionService(
                    self::payrollPeriodRepository()
                );
        }


        return self::$payrollPeriodProtectionService;
    }


    public static function payrollReviewNoteService(): PayrollReviewNoteService
    {
        if (self::$payrollReviewNoteService === null) {

            self::$payrollReviewNoteService =
                new PayrollReviewNoteService(
                    self::db(),
                    self::payrollPeriodRepository(),
                    self::payrollReviewNoteRepository(),
                    self::payrollPeriodHistoryRepository()
                );
        }


        return self::$payrollReviewNoteService;
    }


    public static function payrollExceptionService(): PayrollExceptionService
    {
        if (self::$payrollExceptionService === null) {

            self::$payrollExceptionService =
                new PayrollExceptionService(
                    self::db(),
                    self::payrollPeriodRepository(),
                    self::payrollExceptionResolutionRepository(),
                    self::payrollWorkspaceService()
                );
        }


        return self::$payrollExceptionService;
    }


    public static function payrollExceptionResolutionService(): PayrollExceptionResolutionService
    {
        if (self::$payrollExceptionResolutionService === null) {

            self::$payrollExceptionResolutionService =
                new PayrollExceptionResolutionService(
                    self::db(),
                    self::payrollPeriodRepository(),
                    self::payrollExceptionResolutionRepository(),
                    self::payrollPeriodHistoryRepository()
                );
        }


        return self::$payrollExceptionResolutionService;
    }


    public static function payrollReportPeriodMetadataService(): PayrollReportPeriodMetadataService
    {
        if (self::$payrollReportPeriodMetadataService === null) {

            self::$payrollReportPeriodMetadataService =
                new PayrollReportPeriodMetadataService(
                    self::payrollPeriodRepository(),
                    self::payrollExceptionResolutionRepository()
                );
        }


        return self::$payrollReportPeriodMetadataService;
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


    public static function payrollWorkspaceService(): PayrollWorkspaceService
    {
        if (self::$payrollWorkspaceService === null) {

            self::$payrollWorkspaceService =
                new PayrollWorkspaceService(
                    self::punchReportService(),
                    self::companySettingsService(),
                    null,
                    self::laborRulesService()
                );
        }


        return self::$payrollWorkspaceService;
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


    public static function payrollWorkspaceCsvExporter(): PayrollWorkspaceCsvExporter
    {
        if (self::$payrollWorkspaceCsvExporter === null) {

            self::$payrollWorkspaceCsvExporter =
                new PayrollWorkspaceCsvExporter();
        }


        return self::$payrollWorkspaceCsvExporter;
    }


    public static function dailyPayrollPdfExporter(): DailyPayrollPdfExporter
    {
        if (self::$dailyPayrollPdfExporter === null) {

            self::$dailyPayrollPdfExporter =
                new DailyPayrollPdfExporter();
        }


        return self::$dailyPayrollPdfExporter;
    }


    public static function payrollRegisterPdfExporter(): PayrollRegisterPdfExporter
    {
        if (self::$payrollRegisterPdfExporter === null) {

            self::$payrollRegisterPdfExporter =
                new PayrollRegisterPdfExporter();
        }


        return self::$payrollRegisterPdfExporter;
    }


    public static function payrollWorkspacePdfExporter(): PayrollWorkspacePdfExporter
    {
        if (self::$payrollWorkspacePdfExporter === null) {

            self::$payrollWorkspacePdfExporter =
                new PayrollWorkspacePdfExporter();
        }


        return self::$payrollWorkspacePdfExporter;
    }


    public static function employeeTimeCardPdfExporter(): EmployeeTimeCardPdfExporter
    {
        if (self::$employeeTimeCardPdfExporter === null) {

            self::$employeeTimeCardPdfExporter =
                new EmployeeTimeCardPdfExporter();
        }


        return self::$employeeTimeCardPdfExporter;
    }


    public static function clear(): void
    {
        self::$db = null;

        self::$loggers = [];

        self::$employeeRepository = null;

        self::$auditRepository = null;

        self::$punchRepository = null;

        self::$punchCorrectionHistoryRepository = null;

        self::$emailRepository = null;

        self::$companySettingsRepository = null;

        self::$reportScheduleRepository = null;

        self::$userRepository = null;

        self::$notificationRecipientRepository = null;

        self::$laborRulesRepository = null;

        self::$payrollPeriodRepository = null;

        self::$payrollPeriodHistoryRepository = null;

        self::$payrollReviewNoteRepository = null;

        self::$payrollExceptionResolutionRepository = null;

        self::$employeeService = null;

        self::$auditService = null;

        self::$punchService = null;

        self::$punchCorrectionService = null;

        self::$punchReportService = null;

        self::$companySettingsService = null;

        self::$reportScheduleService = null;

        self::$reportEmailService = null;

        self::$mailService = null;

        self::$authService = null;

        self::$notificationRecipientService = null;

        self::$dashboardService = null;

        self::$payrollWorkspaceService = null;

        self::$laborRulesService = null;

        self::$payrollPeriodService = null;

        self::$payrollApprovalService = null;

        self::$payrollPeriodProtectionService = null;

        self::$payrollReviewNoteService = null;

        self::$payrollExceptionService = null;

        self::$payrollExceptionResolutionService = null;

        self::$payrollReportPeriodMetadataService = null;

        self::$dailyPayrollCsvExporter = null;

        self::$weeklyPayrollCsvExporter = null;

        self::$payrollWorkspaceCsvExporter = null;

        self::$dailyPayrollPdfExporter = null;

        self::$payrollRegisterPdfExporter = null;

        self::$payrollWorkspacePdfExporter = null;

        self::$employeeTimeCardPdfExporter = null;
    }
}
