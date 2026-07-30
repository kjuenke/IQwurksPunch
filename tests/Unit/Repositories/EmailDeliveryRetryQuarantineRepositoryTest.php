<?php
declare(strict_types=1);

use App\Repositories\EmailDeliveryRetryQuarantineRepository;
use PHPUnit\Framework\TestCase;

final class EmailDeliveryRetryQuarantineRepositoryTest extends TestCase
{
    private PDO $database;

    private EmailDeliveryRetryQuarantineRepository $repository;


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


        $this->repository =
            new EmailDeliveryRetryQuarantineRepository(
                $this->database
            );
    }


    public function testMarksFailedAttemptAsPermanent(): void
    {
        $attemptId =
            $this->insertAttempt(
                'failed',
                0,
                'Original SMTP error.',
                '2026-07-30 18:00:00'
            );


        self::assertTrue(
            $this->repository
                ->markPermanentFailure(
                    $attemptId,
                    'Retry metadata is invalid: report date is missing.'
                )
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


        self::assertSame(
            'Retry metadata is invalid: report date is missing.',
            $attempt['error_message']
        );


        self::assertSame(
            '2026-07-30 18:00:00',
            $attempt['completed_at']
        );
    }


    public function testDoesNotAlterIneligibleAttempts(): void
    {
        $permanentAttemptId =
            $this->insertAttempt(
                'failed',
                1,
                'Existing permanent failure.',
                '2026-07-30 18:01:00'
            );


        $sentAttemptId =
            $this->insertAttempt(
                'sent',
                0,
                null,
                '2026-07-30 18:02:00'
            );


        self::assertFalse(
            $this->repository
                ->markPermanentFailure(
                    $permanentAttemptId,
                    'Replacement error.'
                )
        );


        self::assertFalse(
            $this->repository
                ->markPermanentFailure(
                    $sentAttemptId,
                    'Replacement error.'
                )
        );


        $permanentAttempt =
            $this->attempt(
                $permanentAttemptId
            );


        $sentAttempt =
            $this->attempt(
                $sentAttemptId
            );


        self::assertSame(
            'Existing permanent failure.',
            $permanentAttempt['error_message']
        );


        self::assertSame(
            1,
            (int)$permanentAttempt['permanent_failure']
        );


        self::assertSame(
            'sent',
            $sentAttempt['status']
        );


        self::assertSame(
            0,
            (int)$sentAttempt['permanent_failure']
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


    private function insertAttempt(
        string $status,
        int $permanentFailure,
        ?string $errorMessage,
        ?string $completedAt
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
                    :status,
                    :permanent_failure,
                    :error_message,
                    :completed_at
                )
                "
            );


        $statement->execute(
            [
                'status' =>
                    $status,

                'permanent_failure' =>
                    $permanentFailure,

                'error_message' =>
                    $errorMessage,

                'completed_at' =>
                    $completedAt
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
