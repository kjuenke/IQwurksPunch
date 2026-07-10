<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\PunchRepository;

class PunchService
{
    private PunchRepository $punches;


    public function __construct(
        PunchRepository $punches
    )
    {
        $this->punches = $punches;
    }


    public function punch(
        int $employeeId,
        string $type
    ): array
    {
        $allowed = [
            'clock_in',
            'clock_out',
            'break_out',
            'break_in',
            'meal_out',
            'meal_in'
        ];


        if (!in_array($type, $allowed, true)) {

            return [
                'success' => false,
                'error' => 'Invalid punch type.'
            ];
        }


        $status = $this->status(
            $employeeId
        );


        if (
            $type === 'clock_in'
            &&
            $status['state'] === 'working'
        ) {

            return [
                'success' => false,
                'error' => 'Employee is already clocked in.'
            ];

        }


        if (
            $type === 'clock_out'
            &&
            $status['state'] !== 'working'
        ) {

            return [
                'success' => false,
                'error' => 'Employee is not clocked in.'
            ];

        }


        $created = $this->punches->create(
            $employeeId,
            $type
        );


        return [
            'success' => $created
        ];
    }


    public function latest(
        int $employeeId
    ): ?array
    {
        return $this->punches->latest(
            $employeeId
        );
    }


    public function status(
        int $employeeId
    ): array
    {
        $latest =
            $this->latest(
                $employeeId
            );


        if (!$latest) {

            return [
                'state' => 'not_started',
                'label' => 'Not Clocked In',
                'punch' => null
            ];

        }


        if (
            $latest['punch_type']
            ===
            'clock_in'
        ) {

            return [
                'state' => 'working',
                'label' => 'Clocked In',
                'punch' => $latest
            ];

        }


        return [
            'state' => 'not_working',
            'label' => 'Clocked Out',
            'punch' => $latest
        ];
    }
}
