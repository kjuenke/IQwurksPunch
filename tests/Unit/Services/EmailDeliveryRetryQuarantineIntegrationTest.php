<?php
declare(strict_types=1);

use App\Repositories\EmailDeliveryRetryQuarantineRepository;
use App\Services\EmailDeliveryRetryExecutionService;
use App\Services\EmailDeliveryRetryPlanService;
use PHPUnit\Framework\TestCase;

final class EmailDeliveryRetryQuarantineIntegrationTest extends TestCase
{
    private PDO $database;

    private EmailDeliveryRetryQuarantineRepository $quarantine;


    protected function setUp(): void
    {
        parent::setUp();


        $this->database =
            new PDO(
                'sqlite::memory:'
            );


        $this->database->setAttribute(
            PDO::ATTR_ERRMODE,
            PDO::ERRMODE_EXCEPTION
        );


        $this->database->setAttribute(
            PDO::ATTR_DEFAULT_FETCH_MODE,
            PDO::FETCH_ASSOC
        );


        $this->createSchema();


        $this->quarantine =
            new EmailDeliveryRetryQuarantineRepository(
                $this->database
            );
    }


    public function testMalformedRetryMetadataIsQuarantinedBeforeDelivery(): void
    {
        $attemptId =
            $this->insertFailedAttempt(
                'Original SMTP failure.'
            );


        $dailyCalled = false;

        $weeklyCalled = false;

        $exceptionCalled = false;


        $service =
            new EmailDeliveryRetryExecutionService(
                new EmailDeliveryRetryPlanService(),

                static function (
                    mixed ...$arguments
                ) use (
                    &$dailyCalled
                ): bool {
                    $dailyCalled = true;


                    return true;
                },

                static function (
                    mixed ...$arguments
                ) use (
                    &$weeklyCalled
                ): bool {
                    $weeklyCalled = true;


                    return true;
                },

                static function (
                    mixed ...$arguments
                ) use (
                    &$exceptionCalled
                ): bool {
                    $exceptionCalled = true;


                    return true;
                },

                $this->quarantine
            );


        try {

            $service->retry(
                [
                    'id' =>
                        $attemptId,

                    'schedule_id' =>
                        1,

                    'notification_type' =>
                        'daily_payroll',

                    'status' =>
                        'failed',

                    'attempt_number' =>
                        1,

                    'max_attempts' =>
                        3,

                    'permanent_failure' =>
                        0,

                    'attachment_names' =>
                        '{not-json}'
                ]
            );


            self::fail(
                'Malformed retry metadata should raise an exception.'
            );

        } catch (InvalidArgumentException $exception) {

            self::assertSame(
                'The email attachment metadata is not valid JSON.',
                $exception->getMessage()
            );
        }


        self::assertFalse(
            $dailyCalled
        );


        self::assertFalse(
            $weeklyCalled
        );


        self::assertFalse(
            $exceptionCalled
        );


        $attempt =
            $this->attempt(
                $attemptId
            );


        self::assertSame(
            'failed',
            $attempt['status']
        );


        self::assertSame(
            1,
            (int)$attempt['permanent_failure']
        );


        self::assertStringContainsString(
            'Retry metadata is invalid: '
            .
            'The email attachment metadata is not valid JSON.',
            (string)$attempt['error_message']
        );
    }


    public function testDeliveryExceptionDoesNotQuarantineValidRetryMetadata(): void
    {
        $attemptId =
            $this->insertFailedAttempt(
                'Temporary SMTP failure.'
            );


        $service =
            new EmailDeliveryRetryExecutionService(
                new EmailDeliveryRetryPlanService(),

                static function (
                    string $source,
                    ?int $scheduleId,
                    int $attemptNumber,
                    int $maxAttempts,
                    int $retryOfId,
                    string $reportDate
                ): bool {
                    throw new RuntimeException(
                        'Synthetic retry delivery failure.'
                    );
                },

                static fn (
                    mixed ...$arguments
                ): bool =>
                    true,

                static fn (
                    mixed ...$arguments
                ): bool =>
                    true,

                $this->quarantine
            );


        try {

            $service->retry(
                [
                    'id' =>
                        $attemptId,

                    'schedule_id' =>
                        1,

                    'notification_type' =>
                        'daily_payroll',

                    'status' =>
                        'failed',

                    'attempt_number' =>
                        1,

                    'max_attempts' =>
                        3,

                    'permanent_failure' =>
                        0,

                    'attachment_names' =>
                        json_encode(
                            [
                                'iqwurkspunch-daily-payroll-2026-07-29.csv'
                            ]
                        )
                ]
            );


            self::fail(
                'The synthetic delivery exception should be preserved.'
            );

        } catch (RuntimeException $exception) {

            self::assertSame(
                'Synthetic retry delivery failure.',
                $exception->getMessage()
            );
        }


        $attempt =
            $this->attempt(
                $attemptId
            );


        self::assertSame(
            'failed',
            $attempt['status']
        );


        self::assertSame(
            0,
            (int)$attempt['permanent_failure']
        );


        self::assertSame(
            'Temporary SMTP failure.',
            $attempt['error_message']
        );
    }


    private function createSchema(): void
    {
        $this->database->exec(
            "
            CREATE TABLE email_delivery_attempts
            (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                status TEXT NOT NULL,
                permanent_failure INTEGER NOT NULL DEFAULT 0,
                error_message TEXT DEFAULT NULL,
                completed_at DATETIME DEFAULT NULL
            )
            "
        );
    }


    private function insertFailedAttempt(
        string $errorMessage
    ): int
    {
        $statement =
            $this->database->prepare(
                "
                INSERT INTO email_delivery_attempts
                (
                    status,
                    permanent_failure,
                    error_message,
                    completed_at
                )

                VALUES
                (
                    'failed',
                    0,
                    :error_message,
                    '2026-07-30 18:00:00'
                )
                "
            );


        $statement->execute(
            [
                'error_message' =>
                    $errorMessage
            ]
        );


        return
            (int)$this->database
                ->lastInsertId();
    }


    /**
     * @return array<string,mixed>
     */
    private function attempt(
        int $attemptId
    ): array
    {
        $statement =
            $this->database->prepare(
                "
                SELECT *
                FROM email_delivery_attempts

                WHERE id = :id
                "
            );


        $statement->execute(
            [
                'id' =>
                    $attemptId
            ]
        );


        $attempt =
            $statement->fetch();


        self::assertIsArray(
            $attempt
        );


        return $attempt;
    }
}
