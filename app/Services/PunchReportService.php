<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\PunchRepository;

class PunchReportService
{
    private PunchRepository $punches;


    public function __construct(
        PunchRepository $punches
    )
    {
        $this->punches = $punches;
    }



    public function employeeHistory(
        int $employeeId
    ): array
    {
        return $this->punches->employeePunches(
            $employeeId
        );
    }



    public function today(): array
    {
        return $this->punches->dailyPunches(
            date('Y-m-d')
        );
    }



    public function recent(
        int $limit = 100
    ): array
    {
        return $this->punches->recent(
            $limit
        );
    }



    public function calculateHours(
        array $punches
    ): float
    {
        $totalSeconds = 0;

        $clockIn = null;


        foreach ($punches as $punch) {


            if (
                $punch['punch_type']
                ===
                'clock_in'
            ) {

                $clockIn =
                    strtotime(
                        $punch['punch_time']
                    );

            }


            if (
                $punch['punch_type']
                ===
                'clock_out'
                &&
                $clockIn !== null
            ) {

                $clockOut =
                    strtotime(
                        $punch['punch_time']
                    );


                $totalSeconds +=
                    $clockOut - $clockIn;


                $clockIn = null;
            }
        }


        return round(
            $totalSeconds / 3600,
            2
        );
    }

    public function dailySummary(): array
    {
        $punches =
            $this->today();


        $employees = [];


        foreach ($punches as $punch) {

            $id =
                $punch['employee_id'];


            if (!isset($employees[$id])) {

                $employees[$id] = [
                    'employee_id' =>
                        $id,

                    'employee_number' =>
                        $punch['employee_number'],

                    'name' =>
                        $punch['first_name']
                        . ' '
                        .
                        $punch['last_name'],

                    'punches' => []
                ];

            }


            $employees[$id]['punches'][] =
                $punch;
        }


        foreach ($employees as &$employee) {

            $employee['hours'] =
                $this->calculateHours(
                    $employee['punches']
                );
        }


        return array_values(
            $employees
        );
    }

}
