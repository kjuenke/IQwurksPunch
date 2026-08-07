<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PayrollPeriodListLifecyclePresentationTest extends TestCase
{
    private string $controller;


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
    }


    public function testControllerDefaultsToActiveAndSupportsRemovedView(): void
    {
        self::assertStringContainsString(
            "\$_GET['view']",
            $this->controller
        );

        self::assertStringContainsString(
            "->active()",
            $this->controller
        );

        self::assertStringContainsString(
            "->removed()",
            $this->controller
        );

        self::assertStringContainsString(
            "'listView' =>",
            $this->controller
        );

        self::assertStringContainsString(
            "'active'",
            $this->controller
        );

        self::assertStringContainsString(
            "'removed'",
            $this->controller
        );
    }

    public function testListProvidesActiveAndRemovedNavigation(): void
    {
        $view =
            $this->viewSource();


        self::assertStringContainsString(
            'Active Periods',
            $view
        );

        self::assertStringContainsString(
            'Removed History',
            $view
        );

        self::assertStringContainsString(
            '/payroll-periods?view=removed',
            $view
        );

        self::assertStringContainsString(
            "listView == 'removed'",
            $view
        );
    }


    public function testRemovedRecordsHaveDistinctHistoricalLabels(): void
    {
        $view =
            $this->viewSource();


        self::assertStringContainsString(
            'Workflow / Disposition',
            $view
        );

        self::assertStringContainsString(
            'payrollPeriod.archived_at',
            $view
        );

        self::assertStringContainsString(
            'payrollPeriod.voided_at',
            $view
        );

        self::assertStringContainsString(
            'View Archived Record',
            $view
        );

        self::assertStringContainsString(
            'View Voided Record',
            $view
        );

        self::assertStringContainsString(
            'read-only detail page',
            $view
        );
    }


    private function viewSource(): string
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
                '/app/Views/payroll-periods/index.twig'
            );
    }

}
