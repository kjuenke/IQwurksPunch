<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PayrollPeriodRemovalAnalysisPresentationTest extends TestCase
{
    private string $controller;

    private string $routes;

    private string $view;


    protected function setUp(): void
    {
        parent::setUp();


        $root =
            dirname(
                __DIR__,
                3
            );


        $this->controller =
            (string)file_get_contents(
                $root
                .
                '/app/Controllers/PayrollPeriodController.php'
            );


        $this->routes =
            (string)file_get_contents(
                $root
                .
                '/routes/web.php'
            );


        $this->view =
            (string)file_get_contents(
                $root
                .
                '/app/Views/payroll-periods/show.twig'
            );
    }


    public function testControllerLoadsAnalysisOnlyForActiveAdministrator(): void
    {
        self::assertStringContainsString(
            'private PayrollPeriodRemovalService $removal;',
            $this->controller
        );


        self::assertStringContainsString(
            'private UserRepository $users;',
            $this->controller
        );


        self::assertStringContainsString(
            'private function isActiveAdministrator(',
            $this->controller
        );


        self::assertStringContainsString(
            "'admin';",
            $this->controller
        );


        self::assertStringContainsString(
            '$this->removal',
            $this->controller
        );


        self::assertStringContainsString(
            '->analyze(',
            $this->controller
        );


        self::assertStringContainsString(
            "'removalAnalysis' =>",
            $this->controller
        );
    }


    public function testActionCardIsGuardedByAdministratorAnalysis(): void
    {
        self::assertStringContainsString(
            '{# BEGIN PAYROLL PERIOD REMOVAL ANALYSIS #}',
            $this->view
        );


        self::assertStringContainsString(
            '{# END PAYROLL PERIOD REMOVAL ANALYSIS #}',
            $this->view
        );


        self::assertStringContainsString(
            '{% if removalAnalysis %}',
            $this->view
        );


        self::assertStringContainsString(
            'Removal &amp; Retention Actions',
            $this->view
        );


        self::assertStringContainsString(
            'Administrator Only',
            $this->view
        );
    }


    public function testActionFormsAreGatedByServiceEligibility(): void
    {
        self::assertStringContainsString(
            '{% if removalAnalysis.can_delete_draft %}',
            $this->view
        );


        self::assertStringContainsString(
            '{% if removalAnalysis.can_archive %}',
            $this->view
        );


        self::assertStringContainsString(
            '{% if removalAnalysis.can_void %}',
            $this->view
        );


        self::assertStringContainsString(
            'action="/payroll-periods/{{ period.id }}/delete-draft"',
            $this->view
        );


        self::assertStringContainsString(
            'action="/payroll-periods/{{ period.id }}/archive"',
            $this->view
        );


        self::assertStringContainsString(
            'action="/payroll-periods/{{ period.id }}/void"',
            $this->view
        );
    }


    public function testFormsContainRequiredReasonAndConfirmationFields(): void
    {
        self::assertStringContainsString(
            'Type DELETE to confirm',
            $this->view
        );


        self::assertStringContainsString(
            'pattern="DELETE"',
            $this->view
        );


        self::assertStringContainsString(
            'Archive reason',
            $this->view
        );


        self::assertStringContainsString(
            'Void reason',
            $this->view
        );


        self::assertStringContainsString(
            'minlength="10"',
            $this->view
        );


        self::assertStringContainsString(
            'maxlength="1000"',
            $this->view
        );


        self::assertStringContainsString(
            'Type VOID to confirm',
            $this->view
        );


        self::assertStringContainsString(
            'pattern="VOID"',
            $this->view
        );
    }


    public function testRemovalCardPresentsPeriodDataAndRetentionContext(): void
    {
        self::assertStringContainsString(
            'removalAnalysis.operational_context|default({})',
            $this->view
        );


        self::assertStringContainsString(
            'Period Data Context',
            $this->view
        );


        self::assertStringContainsString(
            'employee_count',
            $this->view
        );


        self::assertStringContainsString(
            'punch_count',
            $this->view
        );


        self::assertStringContainsString(
            'Generated Reports',
            $this->view
        );


        self::assertStringContainsString(
            'generated_report_tracking',
            $this->view
        );


        self::assertStringContainsString(
            'Not tracked',
            $this->view
        );


        self::assertStringContainsString(
            'does not delete punch records',
            $this->view
        );


        self::assertStringContainsString(
            'not stored as payroll-period-linked artifacts',
            $this->view
        );


        self::assertStringContainsString(
            'Lifecycle Dependency Records',
            $this->view
        );
    }


    public function testControllerAndPostRoutesWireAllRemovalActions(): void
    {
        self::assertStringContainsString(
            'public function deleteDraft(',
            $this->controller
        );


        self::assertStringContainsString(
            'public function archive(',
            $this->controller
        );


        self::assertStringContainsString(
            'public function void(',
            $this->controller
        );


        self::assertStringContainsString(
            '->deleteDraft(',
            $this->controller
        );


        self::assertStringContainsString(
            '->archive(',
            $this->controller
        );


        self::assertStringContainsString(
            '->void(',
            $this->controller
        );


        self::assertStringContainsString(
            "'/payroll-periods/{id}/delete-draft'",
            $this->routes
        );


        self::assertStringContainsString(
            '[$payrollPeriods, \'deleteDraft\']',
            $this->routes
        );


        self::assertStringContainsString(
            "'/payroll-periods/{id}/archive'",
            $this->routes
        );


        self::assertStringContainsString(
            '[$payrollPeriods, \'archive\']',
            $this->routes
        );


        self::assertStringContainsString(
            "'/payroll-periods/{id}/void'",
            $this->routes
        );


        self::assertStringContainsString(
            '[$payrollPeriods, \'void\']',
            $this->routes
        );
    }
}
