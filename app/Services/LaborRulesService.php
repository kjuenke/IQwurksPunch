<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\LaborRulesRepository;

final class LaborRulesService
{
    public function __construct(
        private LaborRulesRepository $repository
    ) {
    }


    /**
     * @return array<string,mixed>
     */
    public function get(): array
    {
        return $this->repository->get();
    }


    /**
     * @param array<string,mixed> $data
     */
    public function update(
        array $data
    ): void
    {
        $this->repository->update(
            [
                'daily_overtime_hours' =>
                    (float)($data['daily_overtime_hours'] ?? 8),

                'weekly_overtime_hours' =>
                    (float)($data['weekly_overtime_hours'] ?? 40),

                'double_time_hours' =>
                    (float)($data['double_time_hours'] ?? 12),

                'workweek_start_day' =>
                    strtolower(
                        trim(
                            (string)($data['workweek_start_day'] ?? 'monday')
                        )
                    )
            ]
        );
    }
}
