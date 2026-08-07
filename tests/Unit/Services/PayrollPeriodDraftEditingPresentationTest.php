<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PayrollPeriodDraftEditingPresentationTest extends TestCase
{
    private string $controller;

    private string $routes;


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
    }


    public function testDraftEditRoutesAreRegisteredBeforeDetailRoute(): void
    {
        $editRoute =
            strpos(
                $this->routes,
                "'/payroll-periods/{id}/edit'"
            );

        $detailRoute =
            strpos(
                $this->routes,
                "'/payroll-periods/{id}'"
            );


        self::assertNotFalse(
            $editRoute
        );

        self::assertNotFalse(
            $detailRoute
        );

        self::assertLessThan(
            $detailRoute,
            $editRoute
        );

        self::assertStringContainsString(
            '[$payrollPeriods, \'edit\']',
            $this->routes
        );

        self::assertStringContainsString(
            '[$payrollPeriods, \'update\']',
            $this->routes
        );
    }


    public function testControllerUsesAuthoritativeDraftEditingService(): void
    {
        self::assertStringContainsString(
            'public function edit(',
            $this->controller
        );

        self::assertStringContainsString(
            'public function update(',
            $this->controller
        );

        self::assertStringContainsString(
            '->requireEditableDraft(',
            $this->controller
        );

        self::assertStringContainsString(
            '->updateDraft(',
            $this->controller
        );

        self::assertStringContainsString(
            'private function renderEditForm(',
            $this->controller
        );
    }


    public function testSuccessfulChangesAreAuditedWithoutNoOpNoise(): void
    {
        self::assertStringContainsString(
            "'payroll_period.updated'",
            $this->controller
        );

        self::assertStringContainsString(
            'private function periodUpdateAuditDetails(',
            $this->controller
        );

        self::assertStringContainsString(
            'No payroll-period changes were necessary.',
            $this->controller
        );

        self::assertStringContainsString(
            'Payroll period updated successfully.',
            $this->controller
        );
    }


    public function testReusableFormSupportsCreateAndEditModes(): void
    {
        $form =
            $this->viewSource(
                'payroll-periods/create.twig'
            );


        self::assertStringContainsString(
            "formMode|default('create')",
            $form
        );

        self::assertStringContainsString(
            "formAction|default('/payroll-periods/create')",
            $form
        );

        self::assertStringContainsString(
            "formBackUrl|default('/payroll-periods')",
            $form
        );

        self::assertStringContainsString(
            'Edit Payroll Period',
            $form
        );

        self::assertStringContainsString(
            'Save Changes',
            $form
        );
    }


    public function testDetailPageOffersEditingOnlyForActiveOpenDrafts(): void
    {
        $detail =
            $this->viewSource(
                'payroll-periods/show.twig'
            );


        self::assertStringContainsString(
            'period.archived_at',
            $detail
        );

        self::assertStringContainsString(
            'period.voided_at',
            $detail
        );

        self::assertStringContainsString(
            "status == 'open' and not isInactive",
            $detail
        );

        self::assertStringContainsString(
            '/payroll-periods/{{ period.id }}/edit',
            $detail
        );

        self::assertStringContainsString(
            'Edit Draft',
            $detail
        );
    }


    public function testInactiveDetailPageIsConsistentlyReadOnly(): void
    {
        $detail =
            $this->viewSource(
                'payroll-periods/show.twig'
            );


        self::assertStringContainsString(
            'This payroll period is read-only.',
            $detail
        );

        self::assertStringContainsString(
            'Workflow, review-note, and exception actions are unavailable',
            $detail
        );

        self::assertGreaterThanOrEqual(
            5,
            substr_count(
                $detail,
                'not isInactive'
            )
        );

        self::assertStringContainsString(
            'Voided',
            $detail
        );

        self::assertStringContainsString(
            'Archived',
            $detail
        );
    }


    private function viewSource(
        string $relativePath
    ): string
    {
        $root =
            dirname(
                __DIR__,
                3
            );


        return
            (string)file_get_contents(
                $root
                .
                '/app/Views/'
                .
                $relativePath
            );
    }


}
