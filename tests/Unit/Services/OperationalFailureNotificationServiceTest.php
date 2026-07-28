<?php
declare(strict_types=1);

use App\Services\OperationalFailureNotificationService;
use PHPUnit\Framework\TestCase;

final class OperationalFailureNotificationServiceTest extends TestCase
{
    private OperationalFailureNotificationService $service;


    protected function setUp(): void
    {
        parent::setUp();


        $reflection =
            new ReflectionClass(
                OperationalFailureNotificationService::class
            );


        $this->service =
            $reflection->newInstanceWithoutConstructor();
    }


    public function testSubjectIdentifiesOperationalFailureSource(): void
    {
        $subject =
            $this->invokePrivate(
                'buildSubject',
                [
                    'Database Check'
                ]
            );


        self::assertSame(
            'IQwurksPunch Operational Failure: Database Check',
            $subject
        );
    }


    public function testBodyContainsOperationalFailureDetails(): void
    {
        $body =
            $this->invokePrivate(
                'buildBody',
                [
                    'Database Check',
                    'The active SQLite database failed its integrity check.',
                    [
                        'exit_code' =>
                            1,

                        'failure_count' =>
                            2,

                        'retry_attempted' =>
                            false,

                        'result' =>
                            null,

                        'checks' => [
                            'integrity_check' =>
                                'failed',

                            'foreign_key_check' =>
                                'passed'
                        ]
                    ]
                ]
            );


        self::assertStringContainsString(
            'IQwurksPunch Operational Failure Notification',
            $body
        );


        self::assertStringContainsString(
            'An application operation or diagnostic check failed.',
            $body
        );


        self::assertStringContainsString(
            'Source: Database Check',
            $body
        );


        self::assertStringContainsString(
            'Summary: The active SQLite database failed its integrity check.',
            $body
        );


        self::assertStringContainsString(
            'Detected At:',
            $body
        );


        self::assertStringContainsString(
            'Timezone:',
            $body
        );


        self::assertStringContainsString(
            'Hostname:',
            $body
        );


        self::assertStringContainsString(
            'PHP Version: '
            .
            PHP_VERSION,
            $body
        );


        self::assertStringContainsString(
            'Details:',
            $body
        );


        self::assertStringContainsString(
            '- Exit Code: 1',
            $body
        );


        self::assertStringContainsString(
            '- Failure Count: 2',
            $body
        );


        self::assertStringContainsString(
            '- Retry Attempted: no',
            $body
        );


        self::assertStringContainsString(
            '- Result: Not recorded',
            $body
        );


        self::assertStringContainsString(
            '"integrity_check":"failed"',
            $body
        );


        self::assertStringContainsString(
            'Review the IQwurksPunch application logs and correct the underlying failure.',
            $body
        );
    }


    public function testDetailFormattingUsesReadableLabelsAndValues(): void
    {
        self::assertSame(
            'Failure Count',
            $this->invokePrivate(
                'formatLabel',
                [
                    'failure_count'
                ]
            )
        );


        self::assertSame(
            'Backup Path',
            $this->invokePrivate(
                'formatLabel',
                [
                    'backup-path'
                ]
            )
        );


        self::assertSame(
            'yes',
            $this->invokePrivate(
                'formatValue',
                [
                    true
                ]
            )
        );


        self::assertSame(
            'no',
            $this->invokePrivate(
                'formatValue',
                [
                    false
                ]
            )
        );


        self::assertSame(
            'Not recorded',
            $this->invokePrivate(
                'formatValue',
                [
                    null
                ]
            )
        );


        self::assertSame(
            '(empty)',
            $this->invokePrivate(
                'formatValue',
                [
                    '   '
                ]
            )
        );
    }


    public function testValidationRequiresFailureSource(): void
    {
        $this->expectException(
            RuntimeException::class
        );


        $this->expectExceptionMessage(
            'An operational failure source is required.'
        );


        $this->invokePrivate(
            'validateFailure',
            [
                '',
                'A diagnostic failed.'
            ]
        );
    }


    public function testValidationRequiresFailureSummary(): void
    {
        $this->expectException(
            RuntimeException::class
        );


        $this->expectExceptionMessage(
            'An operational failure summary is required.'
        );


        $this->invokePrivate(
            'validateFailure',
            [
                'System Doctor',
                ''
            ]
        );
    }


    private function invokePrivate(
        string $methodName,
        array $arguments
    ): mixed
    {
        $reflection =
            new ReflectionClass(
                OperationalFailureNotificationService::class
            );


        $method =
            $reflection->getMethod(
                $methodName
            );


        return
            $method->invokeArgs(
                $this->service,
                $arguments
            );
    }
}
