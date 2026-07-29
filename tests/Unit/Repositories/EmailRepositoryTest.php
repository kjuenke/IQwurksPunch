<?php
declare(strict_types=1);

use App\Repositories\EmailRepository;
use PHPUnit\Framework\TestCase;

final class EmailRepositoryTest extends TestCase
{
    private \PDO $database;

    private EmailRepository $repository;


    protected function setUp(): void
    {
        parent::setUp();


        $this->database =
            new \PDO(
                'sqlite::memory:'
            );


        $this->database->setAttribute(
            \PDO::ATTR_ERRMODE,
            \PDO::ERRMODE_EXCEPTION
        );


        $this->database->setAttribute(
            \PDO::ATTR_DEFAULT_FETCH_MODE,
            \PDO::FETCH_ASSOC
        );


        $this->database->exec(
            "
            CREATE TABLE email_log
            (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                report_type TEXT NOT NULL,
                recipients TEXT NOT NULL,
                status TEXT NOT NULL,
                sent_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
            "
        );


        $this->repository =
            new EmailRepository(
                $this->database
            );
    }


    public function testCreatePreservesBooleanInterface(): void
    {
        self::assertTrue(
            $this->repository
                ->create(
                    'Daily Payroll Report',
                    'payroll@example.com',
                    'sent'
                )
        );


        $rows =
            $this->repository
                ->all();


        self::assertCount(
            1,
            $rows
        );


        self::assertSame(
            'Daily Payroll Report',
            $rows[0]['report_type']
        );


        self::assertSame(
            'payroll@example.com',
            $rows[0]['recipients']
        );


        self::assertSame(
            'sent',
            $rows[0]['status']
        );
    }


    public function testCreateAndReturnIdReturnsInsertedRecordId(): void
    {
        $firstId =
            $this->repository
                ->createAndReturnId(
                    'First Report',
                    'one@example.com',
                    'sent'
                );


        $secondId =
            $this->repository
                ->createAndReturnId(
                    'Second Report',
                    'two@example.com',
                    'failed'
                );


        self::assertSame(
            1,
            $firstId
        );


        self::assertSame(
            2,
            $secondId
        );
    }


    public function testAllReturnsNewestHistoryFirst(): void
    {
        $this->repository
            ->create(
                'First Report',
                'one@example.com',
                'sent'
            );


        $this->repository
            ->create(
                'Second Report',
                'two@example.com',
                'failed'
            );


        $rows =
            $this->repository
                ->all();


        self::assertCount(
            2,
            $rows
        );


        self::assertSame(
            'Second Report',
            $rows[0]['report_type']
        );


        self::assertSame(
            'First Report',
            $rows[1]['report_type']
        );
    }
}
