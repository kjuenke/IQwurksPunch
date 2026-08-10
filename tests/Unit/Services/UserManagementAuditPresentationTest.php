<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class UserManagementAuditPresentationTest extends TestCase
{
    private string $controller;

    private string $repository;

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
                '/app/Controllers/UserManagementController.php'
            );


        $this->repository =
            (string)file_get_contents(
                $root
                .
                '/app/Repositories/AuditRepository.php'
            );


        $this->view =
            (string)file_get_contents(
                $root
                .
                '/app/Views/users/activity.twig'
            );
    }


    public function testRoleChangesRecordDedicatedBeforeAndAfterAuditDetails(): void
    {
        self::assertStringContainsString(
            "'user.role_changed'",
            $this->controller
        );


        self::assertStringContainsString(
            '$oldRole',
            $this->controller
        );


        self::assertStringContainsString(
            "' from role '",
            $this->controller
        );


        self::assertStringContainsString(
            "' to role '",
            $this->controller
        );
    }


    public function testActivityFeedIncludesAndLabelsUserAuditActions(): void
    {
        self::assertStringContainsString(
            'LIKE "user.%"',
            $this->repository
        );


        self::assertStringContainsString(
            'action.action|replace({',
            $this->view
        );
    }
}
