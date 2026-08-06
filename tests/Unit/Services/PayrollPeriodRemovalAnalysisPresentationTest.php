<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PayrollPeriodRemovalAnalysisPresentationTest extends TestCase
{
    private string $controller;

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


        self::assertMatchesRegularExpression(
            '/if\\s*\\(\\s*'
            .
            '\\$this->isActiveAdministrator\\(\\s*'
            .
            '\\$actingUserId\\s*'
            .
            '\\)\\s*'
            .
            '\\)\\s*'
            .
            '\\{.*?'
            .
            '\\$this->removal\\s*'
            .
            '->analyze\\s*\\(/s',
            $this->controller
        );


        self::assertStringContainsString(
            "'removalAnalysis' =>",
            $this->controller
        );
    }


    public function testViewCardIsGuardedByRemovalAnalysis(): void
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
            'Removal &amp; Retention Analysis',
            $this->view
        );


        self::assertStringContainsString(
            'Administrator Only',
            $this->view
        );
    }


    public function testRemovalAnalysisCardIsReadOnly(): void
    {
        $start =
            strpos(
                $this->view,
                '{# BEGIN PAYROLL PERIOD REMOVAL ANALYSIS #}'
            );


        $end =
            strpos(
                $this->view,
                '{# END PAYROLL PERIOD REMOVAL ANALYSIS #}'
            );


        self::assertNotFalse(
            $start
        );


        self::assertNotFalse(
            $end
        );


        $block =
            substr(
                $this->view,
                (int)$start,
                (int)$end
                -
                (int)$start
            );


        self::assertStringNotContainsString(
            '<form',
            $block
        );


        self::assertStringNotContainsString(
            'action=',
            $block
        );


        self::assertStringNotContainsString(
            '/payroll-periods/',
            $block
        );
    }


    public function testCardDisplaysEligibilityAndDependencies(): void
    {
        self::assertStringContainsString(
            'removalAnalysis.can_delete_draft',
            $this->view
        );


        self::assertStringContainsString(
            'removalAnalysis.can_archive',
            $this->view
        );


        self::assertStringContainsString(
            'removalAnalysis.can_void',
            $this->view
        );


        self::assertStringContainsString(
            'removalAnalysis.dependencies',
            $this->view
        );


        self::assertStringContainsString(
            'Delete Draft',
            $this->view
        );


        self::assertStringContainsString(
            'Archive',
            $this->view
        );


        self::assertStringContainsString(
            'Void',
            $this->view
        );
    }
}
