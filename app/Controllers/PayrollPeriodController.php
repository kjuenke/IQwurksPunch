<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Container;
use App\Core\Flash;
use App\Repositories\PayrollExceptionResolutionRepository;
use App\Repositories\PayrollPeriodHistoryRepository;
use App\Repositories\PayrollReviewNoteRepository;
use App\Repositories\UserRepository;
use App\Services\ApprovalNotificationEmailService;
use App\Services\AuditService;
use App\Services\AuthGuardService;
use App\Services\CompanySettingsService;
use App\Services\PayrollApprovalService;
use App\Services\PayrollExceptionResolutionService;
use App\Services\PayrollExceptionService;
use App\Services\PayrollPeriodRemovalService;
use App\Services\PayrollPeriodService;
use App\Services\PayrollReviewNoteService;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final class PayrollPeriodController extends Controller
{
    private PayrollPeriodService $payrollPeriods;

    private PayrollPeriodRemovalService $removal;

    private PayrollApprovalService $approval;

    private ApprovalNotificationEmailService $approvalNotifications;

    private PayrollReviewNoteService $reviewNoteService;

    private PayrollExceptionService $payrollExceptions;

    private PayrollExceptionResolutionService $exceptionResolution;

    private PayrollPeriodHistoryRepository $history;

    private PayrollReviewNoteRepository $notes;

    private PayrollExceptionResolutionRepository $exceptions;

    private CompanySettingsService $companySettings;

    private AuditService $audit;

    private UserRepository $users;

    private AuthGuardService $authGuard;


    public function __construct()
    {
        $this->payrollPeriods =
            Container::payrollPeriodService();


        $this->removal =
            Container::payrollPeriodRemovalService();


        $this->approval =
            Container::payrollApprovalService();


        $this->approvalNotifications =
            new ApprovalNotificationEmailService();


        $this->reviewNoteService =
            Container::payrollReviewNoteService();


        $this->payrollExceptions =
            Container::payrollExceptionService();


        $this->exceptionResolution =
            Container::payrollExceptionResolutionService();


        $this->history =
            Container::payrollPeriodHistoryRepository();


        $this->notes =
            Container::payrollReviewNoteRepository();


        $this->exceptions =
            Container::payrollExceptionResolutionRepository();


        $this->companySettings =
            Container::companySettingsService();


        $this->audit =
            Container::auditService();


        $this->users =
            Container::userRepository();


        $this->authGuard =
            new AuthGuardService(
                $this->users
            );
    }


    public function index(): void
    {
        $this->requireSupervisor();


        $this->render(
            'payroll-periods/index.twig',
            [
                'title' =>
                    'Payroll Periods',

                'activeMenu' =>
                    'payroll-periods',

                'payrollPeriods' =>
                    $this->payrollPeriods
                        ->all()
            ]
        );
    }


    public function create(): void
    {
        $this->requireSupervisor();


        $this->renderCreateForm(
            $this->defaultPeriodValues()
        );
    }


    public function store(): void
    {
        $userId =
            $this->requireSupervisor();


        try {

            $payrollPeriodId =
                $this->payrollPeriods
                    ->create(
                        $_POST,
                        $userId
                    );


            $period =
                $this->payrollPeriods
                    ->requirePeriod(
                        $payrollPeriodId
                    );


            $this->audit->log(
                'payroll_period.created',
                $this->periodAuditDetails(
                    $period
                ),
                $userId
            );


            Flash::success(
                'Payroll period created successfully.'
            );


            $this->redirectToShow(
                $payrollPeriodId
            );

        } catch (Throwable $exception) {

            $this->renderCreateForm(
                $_POST,
                [
                    'payroll_period' =>
                        $exception->getMessage()
                ]
            );
        }
    }


    public function show(
        int $id
    ): void
    {
        $actingUserId =
            $this->requireSupervisor();


        try {

            $period =
                $this->payrollPeriods
                    ->requirePeriod(
                        $id
                    );

        } catch (Throwable $exception) {

            Flash::error(
                $exception->getMessage()
            );


            $this->redirectToIndex();
        }


        $removalAnalysis =
            null;


        if (
            $this->isActiveAdministrator(
                $actingUserId
            )
        ) {
            try {

                $removalAnalysis =
                    $this->removal
                        ->analyze(
                            $id,
                            $actingUserId
                        );

            } catch (Throwable $exception) {

                Flash::warning(
                    'Removal and retention analysis is temporarily unavailable: '
                    .
                    $exception->getMessage()
                );
            }
        }


        $this->render(
            'payroll-periods/show.twig',
            [
                'title' =>
                    (string)$period['period_name'],

                'activeMenu' =>
                    'payroll-periods',

                'period' =>
                    $period,

                'history' =>
                    $this->history
                        ->allForPeriod(
                            $id
                        ),

                'notes' =>
                    $this->notes
                        ->allForPeriod(
                            $id
                        ),

                'exceptions' =>
                    $this->exceptions
                        ->allForPeriod(
                            $id
                        ),

                'openExceptionCount' =>
                    $this->exceptions
                        ->countOpenForPeriod(
                            $id
                        ),

                'removalAnalysis' =>
                    $removalAnalysis
            ]
        );
    }



    public function deleteDraft(
        int $id
    ): void
    {
        $actingUserId =
            $this->requireSupervisor();


        $confirmation =
            (string)(
                $_POST['confirmation']
                ??
                ''
            );


        try {

            $period =
                $this->removal
                    ->deleteDraft(
                        $id,
                        $actingUserId,
                        $confirmation
                    );


            Flash::success(
                'Draft payroll period '
                .
                (string)(
                    $period['period_name']
                    ??
                    ''
                )
                .
                ' was permanently deleted.'
            );


            $this->redirectToIndex();

        } catch (Throwable $exception) {

            Flash::error(
                $exception->getMessage()
            );


            $this->redirectToShow(
                $id
            );
        }
    }


    public function archive(
        int $id
    ): void
    {
        $actingUserId =
            $this->requireSupervisor();


        $reason =
            (string)(
                $_POST['reason']
                ??
                ''
            );


        try {

            $this->removal
                ->archive(
                    $id,
                    $actingUserId,
                    $reason
                );


            Flash::success(
                'Payroll period archived successfully.'
            );

        } catch (Throwable $exception) {

            Flash::error(
                $exception->getMessage()
            );
        }


        $this->redirectToShow(
            $id
        );
    }


    public function void(
        int $id
    ): void
    {
        $actingUserId =
            $this->requireSupervisor();


        $reason =
            (string)(
                $_POST['reason']
                ??
                ''
            );


        $confirmation =
            (string)(
                $_POST['confirmation']
                ??
                ''
            );


        try {

            $this->removal
                ->void(
                    $id,
                    $actingUserId,
                    $reason,
                    $confirmation
                );


            Flash::success(
                'Payroll period voided successfully.'
            );

        } catch (Throwable $exception) {

            Flash::error(
                $exception->getMessage()
            );
        }


        $this->redirectToShow(
            $id
        );
    }


    public function beginReview(
        int $id
    ): void
    {
        $userId =
            $this->requireSupervisor();


        try {

            $period =
                $this->approval
                    ->beginReview(
                        $id,
                        $userId
                    );


            $this->audit->log(
                'payroll_period.review_started',
                $this->periodAuditDetails(
                    $period
                ),
                $userId
            );


            Flash::success(
                'Payroll review started successfully.'
            );

        } catch (Throwable $exception) {

            Flash::error(
                $exception->getMessage()
            );
        }


        $this->redirectToShow(
            $id
        );
    }


    public function returnToOpen(
        int $id
    ): void
    {
        $userId =
            $this->requireSupervisor();


        $reason =
            trim(
                (string)(
                    $_POST['reason']
                    ??
                    ''
                )
            );


        try {

            $period =
                $this->approval
                    ->returnToOpen(
                        $id,
                        $userId,
                        $reason === ''
                            ? null
                            : $reason
                    );


            $this->audit->log(
                'payroll_period.returned_to_open',
                $this->periodAuditDetails(
                    $period,
                    $reason === ''
                        ? null
                        : $reason
                ),
                $userId
            );


            Flash::success(
                'Payroll period returned to open.'
            );

        } catch (Throwable $exception) {

            Flash::error(
                $exception->getMessage()
            );
        }


        $this->redirectToShow(
            $id
        );
    }


    public function approve(
        int $id
    ): void
    {
        $userId =
            $this->requireSupervisor();


        $confirmed =
            isset(
                $_POST['confirmed']
            )
            &&
            in_array(
                strtolower(
                    trim(
                        (string)$_POST['confirmed']
                    )
                ),
                [
                    '1',
                    'true',
                    'yes',
                    'on',
                    'approve'
                ],
                true
            );


        try {

            $period =
                $this->approval
                    ->approve(
                        $id,
                        $userId,
                        $confirmed
                    );


            $this->audit->log(
                'payroll_period.approved',
                $this->periodAuditDetails(
                    $period
                ),
                $userId
            );


            Flash::success(
                'Payroll period approved successfully.'
            );


            try {

                $notificationSent =
                    $this->approvalNotifications
                        ->sendApprovedPeriod(
                            $period
                        );


                if (!$notificationSent) {

                    Flash::warning(
                        'The payroll period was approved, but the approval notification email could not be sent. Review the mail and report logs for details.'
                    );
                }

            } catch (Throwable) {

                Flash::warning(
                    'The payroll period was approved, but the approval notification email could not be sent. Review the mail and report logs for details.'
                );
            }

        } catch (Throwable $exception) {

            Flash::error(
                $exception->getMessage()
            );
        }


        $this->redirectToShow(
            $id
        );
    }


    public function lock(
        int $id
    ): void
    {
        $userId =
            $this->requireSupervisor();


        $confirmation =
            trim(
                (string)(
                    $_POST['confirmation']
                    ??
                    ''
                )
            );


        try {

            $period =
                $this->approval
                    ->lock(
                        $id,
                        $userId,
                        $confirmation
                    );


            $this->audit->log(
                'payroll_period.locked',
                $this->periodAuditDetails(
                    $period
                ),
                $userId
            );


            Flash::success(
                'Payroll period locked successfully.'
            );

        } catch (Throwable $exception) {

            Flash::error(
                $exception->getMessage()
            );
        }


        $this->redirectToShow(
            $id
        );
    }


    public function reopen(
        int $id
    ): void
    {
        $userId =
            $this->requireSupervisor();


        $reason =
            trim(
                (string)(
                    $_POST['reason']
                    ??
                    ''
                )
            );


        try {

            $period =
                $this->approval
                    ->reopen(
                        $id,
                        $userId,
                        $reason
                    );


            $this->audit->log(
                'payroll_period.reopened',
                $this->periodAuditDetails(
                    $period,
                    $reason
                ),
                $userId
            );


            Flash::success(
                'Payroll period reopened for review.'
            );

        } catch (Throwable $exception) {

            Flash::error(
                $exception->getMessage()
            );
        }


        $this->redirectToShow(
            $id
        );
    }


    public function addNote(
        int $id
    ): void
    {
        $userId =
            $this->requireSupervisor();


        $note =
            (string)(
                $_POST['note']
                ??
                ''
            );


        try {

            $reviewNote =
                $this->reviewNoteService
                    ->add(
                        $id,
                        $userId,
                        $note
                    );


            $period =
                $this->payrollPeriods
                    ->requirePeriod(
                        $id
                    );


            $this->audit->log(
                'payroll_period.note_added',
                $this->reviewNoteAuditDetails(
                    $period,
                    $reviewNote
                ),
                $userId
            );


            Flash::success(
                'Payroll review note added successfully.'
            );

        } catch (Throwable $exception) {

            Flash::error(
                $exception->getMessage()
            );
        }


        $this->redirectToShow(
            $id
        );
    }


    public function refreshExceptions(
        int $id
    ): void
    {
        $userId =
            $this->requireSupervisor();


        try {

            $result =
                $this->payrollExceptions
                    ->refresh(
                        $id
                    );


            $period =
                $this->payrollPeriods
                    ->requirePeriod(
                        $id
                    );


            $this->audit->log(
                'payroll_period.exceptions_refreshed',
                $this->exceptionRefreshAuditDetails(
                    $period,
                    $result
                ),
                $userId
            );


            Flash::success(
                sprintf(
                    'Payroll exceptions refreshed: %d detected, %d open, %d stale open removed.',
                    (int)$result['detected_count'],
                    (int)$result['open_count'],
                    (int)$result['deleted_stale_open_count']
                )
            );

        } catch (Throwable $exception) {

            Flash::error(
                $exception->getMessage()
            );
        }


        $this->redirectToShow(
            $id
        );
    }


    public function resolveException(
        int $id
    ): void
    {
        $userId =
            $this->requireSupervisor();


        $exceptionId =
            (int)(
                $_POST['exception_id']
                ??
                0
            );


        $resolutionNote =
            trim(
                (string)(
                    $_POST['resolution_note']
                    ??
                    ''
                )
            );


        try {

            $exception =
                $this->exceptionResolution
                    ->resolve(
                        $id,
                        $exceptionId,
                        $userId,
                        $resolutionNote === ''
                            ? null
                            : $resolutionNote
                    );


            $period =
                $this->payrollPeriods
                    ->requirePeriod(
                        $id
                    );


            $this->audit->log(
                'payroll_period.exception_resolved',
                $this->exceptionAuditDetails(
                    $period,
                    $exception
                ),
                $userId
            );


            Flash::success(
                'Payroll exception marked resolved.'
            );

        } catch (Throwable $exception) {

            Flash::error(
                $exception->getMessage()
            );
        }


        $this->redirectToShow(
            $id
        );
    }


    public function acceptException(
        int $id
    ): void
    {
        $userId =
            $this->requireSupervisor();


        $exceptionId =
            (int)(
                $_POST['exception_id']
                ??
                0
            );


        $resolutionNote =
            (string)(
                $_POST['resolution_note']
                ??
                ''
            );


        try {

            $exception =
                $this->exceptionResolution
                    ->accept(
                        $id,
                        $exceptionId,
                        $userId,
                        $resolutionNote
                    );


            $period =
                $this->payrollPeriods
                    ->requirePeriod(
                        $id
                    );


            $this->audit->log(
                'payroll_period.exception_accepted',
                $this->exceptionAuditDetails(
                    $period,
                    $exception
                ),
                $userId
            );


            Flash::success(
                'Payroll exception accepted with documentation.'
            );

        } catch (Throwable $exception) {

            Flash::error(
                $exception->getMessage()
            );
        }


        $this->redirectToShow(
            $id
        );
    }


    /**
     * @param array<string,mixed> $old
     * @param array<string,string> $errors
     */
    private function renderCreateForm(
        array $old,
        array $errors = []
    ): void
    {
        $company =
            $this->companySettings
                ->get()
            ??
            [];


        $this->render(
            'payroll-periods/create.twig',
            [
                'title' =>
                    'Create Payroll Period',

                'activeMenu' =>
                    'payroll-periods',

                'old' =>
                    $old,

                'errors' =>
                    $errors,

                'companyTimezone' =>
                    $this->companyTimezone(
                        $company
                    )
            ]
        );
    }


    /**
     * @return array{
     *     period_name:string,
     *     start_date:string,
     *     end_date:string
     * }
     */
    private function defaultPeriodValues(): array
    {
        $company =
            $this->companySettings
                ->get()
            ??
            [];


        $timezone =
            new DateTimeZone(
                $this->companyTimezone(
                    $company
                )
            );


        $startDate =
            new DateTimeImmutable(
                'today',
                $timezone
            );


        $endDate =
            $startDate->modify(
                '+6 days'
            );


        return [
            'period_name' =>
                sprintf(
                    'Weekly Payroll %s through %s',
                    $startDate->format(
                        'Y-m-d'
                    ),
                    $endDate->format(
                        'Y-m-d'
                    )
                ),

            'start_date' =>
                $startDate->format(
                    'Y-m-d'
                ),

            'end_date' =>
                $endDate->format(
                    'Y-m-d'
                )
        ];
    }


    /**
     * @param array<string,mixed> $company
     */
    private function companyTimezone(
        array $company
    ): string
    {
        $timezone =
            trim(
                (string)(
                    $company['timezone']
                    ??
                    date_default_timezone_get()
                )
            );


        if (
            !in_array(
                $timezone,
                timezone_identifiers_list(),
                true
            )
        ) {

            return date_default_timezone_get();
        }


        return $timezone;
    }


    private function isActiveAdministrator(
        int $userId
    ): bool
    {
        if ($userId < 1) {

            return false;
        }


        $user =
            $this->users
                ->findById(
                    $userId
                );


        if (!is_array($user)) {

            return false;
        }


        return
            (int)(
                $user['active']
                ??
                0
            )
            ===
            1
            &&
            strtolower(
                trim(
                    (string)(
                        $user['role']
                        ??
                        ''
                    )
                )
            )
            ===
            'admin';
    }


    private function requireSupervisor(): int
    {
        return
            $this->authGuard
                ->requireAuthorizedUserId(
                    (string)(
                        $_SERVER['REQUEST_URI']
                        ??
                        '/payroll-periods'
                    ),
                    (string)(
                        $_SERVER['REQUEST_METHOD']
                        ??
                        'GET'
                    )
                );
    }


    /**
     * @param array<string,mixed> $period
     */
    private function periodAuditDetails(
        array $period,
        ?string $reason = null
    ): string
    {
        $details = [
            'payroll_period_id' =>
                (int)(
                    $period['id']
                    ??
                    0
                ),

            'period_name' =>
                (string)(
                    $period['period_name']
                    ??
                    ''
                ),

            'start_date' =>
                (string)(
                    $period['start_date']
                    ??
                    ''
                ),

            'end_date' =>
                (string)(
                    $period['end_date']
                    ??
                    ''
                ),

            'status' =>
                (string)(
                    $period['status']
                    ??
                    ''
                )
        ];


        if (
            $reason !== null
            &&
            $reason !== ''
        ) {

            $details['reason'] =
                $reason;
        }


        return $this->auditDetails(
            $details
        );
    }


    /**
     * @param array<string,mixed> $period
     * @param array<string,mixed> $reviewNote
     */
    private function reviewNoteAuditDetails(
        array $period,
        array $reviewNote
    ): string
    {
        return
            $this->auditDetails(
                [
                    'payroll_period_id' =>
                        (int)(
                            $period['id']
                            ??
                            0
                        ),

                    'period_name' =>
                        (string)(
                            $period['period_name']
                            ??
                            ''
                        ),

                    'status' =>
                        (string)(
                            $period['status']
                            ??
                            ''
                        ),

                    'review_note_id' =>
                        (int)(
                            $reviewNote['id']
                            ??
                            0
                        ),

                    'created_by_user_id' =>
                        (int)(
                            $reviewNote['created_by_user_id']
                            ??
                            0
                        )
                ]
            );
    }


    /**
     * @param array<string,mixed> $period
     * @param array<string,mixed> $result
     */
    private function exceptionRefreshAuditDetails(
        array $period,
        array $result
    ): string
    {
        return
            $this->auditDetails(
                [
                    'payroll_period_id' =>
                        (int)(
                            $period['id']
                            ??
                            0
                        ),

                    'period_name' =>
                        (string)(
                            $period['period_name']
                            ??
                            ''
                        ),

                    'status' =>
                        (string)(
                            $period['status']
                            ??
                            ''
                        ),

                    'detected_count' =>
                        (int)(
                            $result['detected_count']
                            ??
                            0
                        ),

                    'created_count' =>
                        (int)(
                            $result['created_count']
                            ??
                            0
                        ),

                    'refreshed_count' =>
                        (int)(
                            $result['refreshed_count']
                            ??
                            0
                        ),

                    'reopened_count' =>
                        (int)(
                            $result['reopened_count']
                            ??
                            0
                        ),

                    'accepted_preserved_count' =>
                        (int)(
                            $result['accepted_preserved_count']
                            ??
                            0
                        ),

                    'deleted_stale_open_count' =>
                        (int)(
                            $result['deleted_stale_open_count']
                            ??
                            0
                        ),

                    'open_count' =>
                        (int)(
                            $result['open_count']
                            ??
                            0
                        ),

                    'total_count' =>
                        (int)(
                            $result['total_count']
                            ??
                            0
                        )
                ]
            );
    }


    /**
     * @param array<string,mixed> $period
     * @param array<string,mixed> $exception
     */
    private function exceptionAuditDetails(
        array $period,
        array $exception
    ): string
    {
        return
            $this->auditDetails(
                [
                    'payroll_period_id' =>
                        (int)(
                            $period['id']
                            ??
                            0
                        ),

                    'period_name' =>
                        (string)(
                            $period['period_name']
                            ??
                            ''
                        ),

                    'period_status' =>
                        (string)(
                            $period['status']
                            ??
                            ''
                        ),

                    'exception_id' =>
                        (int)(
                            $exception['id']
                            ??
                            0
                        ),

                    'exception_type' =>
                        (string)(
                            $exception['exception_type']
                            ??
                            ''
                        ),

                    'exception_date' =>
                        (string)(
                            $exception['exception_date']
                            ??
                            ''
                        ),

                    'description' =>
                        (string)(
                            $exception['description']
                            ??
                            ''
                        ),

                    'resolution_status' =>
                        (string)(
                            $exception['resolution_status']
                            ??
                            ''
                        ),

                    'resolution_note' =>
                        $exception['resolution_note']
                        ??
                        null,

                    'resolved_by_user_id' =>
                        (int)(
                            $exception['resolved_by_user_id']
                            ??
                            0
                        )
                ]
            );
    }


    /**
     * @param array<string,mixed> $details
     */
    private function auditDetails(
        array $details
    ): string
    {
        $encoded =
            json_encode(
                $details,
                JSON_UNESCAPED_SLASHES
                |
                JSON_UNESCAPED_UNICODE
            );


        return
            $encoded === false
                ? 'Payroll-period details could not be encoded.'
                : $encoded;
    }


    private function redirectToShow(
        int $id
    ): never
    {
        header(
            'Location: /payroll-periods/'
            .
            $id
        );


        exit;
    }


    private function redirectToIndex(): never
    {
        header(
            'Location: /payroll-periods'
        );


        exit;
    }
}
