<?php
declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\CommandInterface;
use App\Core\Database;
use App\Repositories\ReportScheduleRepository;
use App\Services\ReportEmailService;
use App\Services\ReportScheduleService;

class ScheduleRunCommand implements CommandInterface
{
    private ReportScheduleService $schedule;

    private ReportEmailService $reports;


    public function __construct()
    {
        $repository =
            new ReportScheduleRepository(
                Database::connection()
            );


        $this->schedule =
            new ReportScheduleService(
                $repository
            );


        $this->reports =
            new ReportEmailService();
    }


    public function name(): string
    {
        return 'schedule:run';
    }


    public function description(): string
    {
        return 'Send the scheduled payroll report when it is due.';
    }


    public function execute(
        array $arguments = []
    ): int
    {
        $schedule =
            $this->schedule->get();


        if (!$schedule) {

            fwrite(
                STDERR,
                'Report schedule settings were not found.'
                . PHP_EOL
            );


            return 1;
        }


        if (!(bool)$schedule['enabled']) {

            echo
                'Automatic report delivery is disabled.'
                . PHP_EOL;


            return 0;
        }


        if (!$this->schedule->isDue()) {

            echo
                'No scheduled report is due.'
                . PHP_EOL;


            return 0;
        }


        echo
            'Scheduled report is due. Sending...'
            . PHP_EOL;


        $sent =
            $this->reports
                ->sendDailyPayrollReport();


        if (!$sent) {

            fwrite(
                STDERR,
                'Scheduled report failed to send.'
                . PHP_EOL
            );


            return 1;
        }


        if (!$this->schedule->markSent()) {

            fwrite(
                STDERR,
                'Report was sent, but last_sent_at could not be updated.'
                . PHP_EOL
            );


            return 1;
        }


        echo
            'Scheduled payroll report sent successfully.'
            . PHP_EOL;


        return 0;
    }
}
