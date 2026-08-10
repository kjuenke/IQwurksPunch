<?php
declare(strict_types=1);

use App\Repositories\PunchRepository;
use PHPUnit\Framework\TestCase;

final class PunchRepositoryMutationTest extends TestCase
{
    private PDO $db;

    private PunchRepository $punches;


    protected function setUp(): void
    {
        parent::setUp();


        $this->db =
            new PDO(
                'sqlite::memory:'
            );


        $this->db->setAttribute(
            PDO::ATTR_ERRMODE,
            PDO::ERRMODE_EXCEPTION
        );


        $this->db->exec(
            "
            CREATE TABLE punches
            (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                employee_id INTEGER NOT NULL,
                punch_time DATETIME NOT NULL,
                punch_type TEXT NOT NULL,
                created_at DATETIME
                    DEFAULT CURRENT_TIMESTAMP,
                source TEXT NOT NULL
                    DEFAULT 'kiosk',
                notes TEXT NOT NULL
                    DEFAULT '',
                corrected_at DATETIME
                    DEFAULT NULL,
                corrected_by_user_id INTEGER
                    DEFAULT NULL,
                correction_reason TEXT NOT NULL
                    DEFAULT ''
            )
            "
        );


        $this->punches =
            new PunchRepository(
                $this->db
            );
    }


    public function testMissingPunchUpdateReturnsFalse(): void
    {
        self::assertFalse(
            $this->punches->updateManual(
                999,
                '2026-08-10 16:30:00',
                'clock_in',
                'Corrected notes',
                7,
                'Missing-row regression test'
            )
        );
    }


    public function testExistingPunchUpdateReturnsTrueAndPersistsCorrection(): void
    {
        $punchId =
            $this->insertPunch();


        self::assertTrue(
            $this->punches->updateManual(
                $punchId,
                '2026-08-10 16:30:00',
                'clock_out',
                'Supervisor corrected the punch.',
                7,
                'Employee selected the wrong punch type.'
            )
        );


        $statement =
            $this->db->prepare(
                '
                SELECT
                    punch_time,
                    punch_type,
                    source,
                    notes,
                    corrected_at,
                    corrected_by_user_id,
                    correction_reason

                FROM punches

                WHERE id = :id
                '
            );


        $statement->execute(
            [
                'id' =>
                    $punchId
            ]
        );


        $punch =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );


        self::assertIsArray(
            $punch
        );


        self::assertSame(
            '2026-08-10 16:30:00',
            $punch['punch_time']
        );


        self::assertSame(
            'clock_out',
            $punch['punch_type']
        );


        self::assertSame(
            'manual',
            $punch['source']
        );


        self::assertSame(
            'Supervisor corrected the punch.',
            $punch['notes']
        );


        self::assertNotEmpty(
            $punch['corrected_at']
        );


        self::assertSame(
            7,
            (int)$punch['corrected_by_user_id']
        );


        self::assertSame(
            'Employee selected the wrong punch type.',
            $punch['correction_reason']
        );
    }


    public function testMissingPunchDeleteReturnsFalse(): void
    {
        self::assertFalse(
            $this->punches->delete(
                999
            )
        );
    }


    public function testExistingPunchDeleteReturnsTrue(): void
    {
        $punchId =
            $this->insertPunch();


        self::assertTrue(
            $this->punches->delete(
                $punchId
            )
        );


        $statement =
            $this->db->prepare(
                '
                SELECT COUNT(*)
                FROM punches
                WHERE id = :id
                '
            );


        $statement->execute(
            [
                'id' =>
                    $punchId
            ]
        );


        self::assertSame(
            0,
            (int)$statement->fetchColumn()
        );
    }


    private function insertPunch(): int
    {
        $statement =
            $this->db->prepare(
                "
                INSERT INTO punches
                (
                    employee_id,
                    punch_time,
                    punch_type,
                    source,
                    notes,
                    correction_reason
                )

                VALUES
                (
                    :employee_id,
                    :punch_time,
                    :punch_type,
                    'kiosk',
                    '',
                    ''
                )
                "
            );


        $statement->execute(
            [
                'employee_id' =>
                    12,

                'punch_time' =>
                    '2026-08-10 15:00:00',

                'punch_type' =>
                    'clock_in'
            ]
        );


        return
            (int)$this->db
                ->lastInsertId();
    }
}
