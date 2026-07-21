<?php
declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\MailDiagnosticService;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class MailDiagnosticServiceTest extends TestCase
{
    public function testNotificationRecipientDiagnosticUsesSubscriptionColumns(): void
    {
        $subscribedDatabase =
            $this->createDatabase();


        $subscribedDatabase->exec(
            "
            INSERT INTO notification_recipients
            (
                name,
                email,
                daily_payroll,
                weekly_payroll,
                exception_reports,
                active
            )

            VALUES
                (
                    'Payroll One',
                    'payroll-one@example.com',
                    1,
                    1,
                    1,
                    1
                ),
                (
                    'Payroll Two',
                    'payroll-two@example.com',
                    1,
                    0,
                    1,
                    1
                ),
                (
                    'Inactive Recipient',
                    'inactive@example.com',
                    1,
                    1,
                    1,
                    0
                )
            "
        );


        $subscribedChecks =
            $this->runRecipientCheck(
                $subscribedDatabase
            );


        self::assertCount(
            1,
            $subscribedChecks
        );


        self::assertSame(
            'PASS',
            $subscribedChecks[0]['status']
        );


        self::assertSame(
            'Notification recipients',
            $subscribedChecks[0]['name']
        );


        self::assertSame(
            'Active notification recipients are available.',
            $subscribedChecks[0]['message']
        );


        self::assertSame(
            [
                'Total active recipients: 2',
                'Daily payroll subscribers: 2',
                'Weekly payroll subscribers: 1',
                'Exception-report subscribers: 2'
            ],
            $subscribedChecks[0]['details']
        );


        $unsubscribedDatabase =
            $this->createDatabase();


        $unsubscribedDatabase->exec(
            "
            INSERT INTO notification_recipients
            (
                name,
                email,
                daily_payroll,
                weekly_payroll,
                exception_reports,
                active
            )

            VALUES
            (
                'No Subscriptions',
                'no-subscriptions@example.com',
                0,
                0,
                0,
                1
            )
            "
        );


        $unsubscribedChecks =
            $this->runRecipientCheck(
                $unsubscribedDatabase
            );


        self::assertCount(
            1,
            $unsubscribedChecks
        );


        self::assertSame(
            'WARN',
            $unsubscribedChecks[0]['status']
        );


        self::assertSame(
            'Active recipients exist, but none are subscribed to a notification type.',
            $unsubscribedChecks[0]['message']
        );


        self::assertSame(
            [
                'Total active recipients: 1',
                'Daily payroll subscribers: 0',
                'Weekly payroll subscribers: 0',
                'Exception-report subscribers: 0'
            ],
            $unsubscribedChecks[0]['details']
        );


        $inactiveDatabase =
            $this->createDatabase();


        $inactiveDatabase->exec(
            "
            INSERT INTO notification_recipients
            (
                name,
                email,
                daily_payroll,
                weekly_payroll,
                exception_reports,
                active
            )

            VALUES
            (
                'Inactive Recipient',
                'inactive-only@example.com',
                1,
                1,
                1,
                0
            )
            "
        );


        $inactiveChecks =
            $this->runRecipientCheck(
                $inactiveDatabase
            );


        self::assertCount(
            1,
            $inactiveChecks
        );


        self::assertSame(
            'WARN',
            $inactiveChecks[0]['status']
        );


        self::assertSame(
            'No active notification recipients are configured.',
            $inactiveChecks[0]['message']
        );


        self::assertSame(
            [
                'Total active recipients: 0',
                'Daily payroll subscribers: 0',
                'Weekly payroll subscribers: 0',
                'Exception-report subscribers: 0'
            ],
            $inactiveChecks[0]['details']
        );
    }


    private function createDatabase(): PDO
    {
        $database =
            new PDO(
                'sqlite::memory:'
            );


        $database->setAttribute(
            PDO::ATTR_ERRMODE,
            PDO::ERRMODE_EXCEPTION
        );


        $database->setAttribute(
            PDO::ATTR_DEFAULT_FETCH_MODE,
            PDO::FETCH_ASSOC
        );


        $database->exec(
            "
            CREATE TABLE notification_recipients
            (
                id INTEGER PRIMARY KEY AUTOINCREMENT,

                name TEXT NOT NULL DEFAULT '',

                email TEXT NOT NULL UNIQUE,

                daily_payroll INTEGER NOT NULL DEFAULT 1,

                weekly_payroll INTEGER NOT NULL DEFAULT 1,

                exception_reports INTEGER NOT NULL DEFAULT 1,

                active INTEGER NOT NULL DEFAULT 1,

                created_at DATETIME NOT NULL
                    DEFAULT CURRENT_TIMESTAMP,

                updated_at DATETIME NOT NULL
                    DEFAULT CURRENT_TIMESTAMP
            )
            "
        );


        return $database;
    }


    /**
     * @return array<int,array<string,mixed>>
     */
    private function runRecipientCheck(
        PDO $database
    ): array
    {
        $serviceReflection =
            new ReflectionClass(
                MailDiagnosticService::class
            );


        $service =
            $serviceReflection
                ->newInstanceWithoutConstructor();


        $databaseProperty =
            $serviceReflection
                ->getProperty(
                    'database'
                );


        $databaseProperty->setValue(
            $service,
            $database
        );


        $method =
            new ReflectionMethod(
                MailDiagnosticService::class,
                'checkRecipients'
            );


        $checks = [];


        $arguments = [
            &$checks
        ];


        $method->invokeArgs(
            $service,
            $arguments
        );


        return $checks;
    }
}
