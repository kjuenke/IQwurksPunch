<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Container;
use App\Core\Flash;
use App\Services\LaborRulesService;
use Throwable;

final class LaborRulesController extends Controller
{
    private LaborRulesService $rules;


    public function __construct()
    {
        $this->rules =
            Container::laborRulesService();
    }


    public function index(): void
    {
        $this->render(
            'settings/labor-rules.twig',
            [
                'title' =>
                    'Labor Rules',

                'activeMenu' =>
                    'settings',

                'rules' =>
                    $this->rules->get()
            ]
        );
    }


    public function update(): void
    {
        $validation =
            $this->validate(
                $_POST
            );


        if ($validation['errors'] !== []) {

            Flash::error(
                implode(
                    ' ',
                    $validation['errors']
                )
            );


            header(
                'Location: /admin/labor-rules'
            );

            exit;
        }


        try {

            $this->rules->update(
                $validation['data']
            );


            Flash::success(
                'Labor rules saved successfully.'
            );

        } catch (Throwable) {

            Flash::error(
                'Unable to save labor rules.'
            );
        }


        header(
            'Location: /admin/labor-rules'
        );

        exit;
    }


    /**
     * @param array<string,mixed> $input
     *
     * @return array{
     *     data:array<string,mixed>,
     *     errors:array<int,string>
     * }
     */
    private function validate(
        array $input
    ): array
    {
        $daily =
            filter_var(
                $input['daily_overtime_hours']
                ??
                null,
                FILTER_VALIDATE_FLOAT
            );


        $weekly =
            filter_var(
                $input['weekly_overtime_hours']
                ??
                null,
                FILTER_VALIDATE_FLOAT
            );


        $double =
            filter_var(
                $input['double_time_hours']
                ??
                null,
                FILTER_VALIDATE_FLOAT
            );


        $workweekStart =
            strtolower(
                trim(
                    (string)(
                        $input['workweek_start_day']
                        ??
                        ''
                    )
                )
            );


        $errors = [];


        if (
            $daily === false
            ||
            $daily <= 0
            ||
            $daily > 24
        ) {
            $errors[] =
                'Daily overtime must be greater than 0 and no more than 24 hours.';
        }


        if (
            $weekly === false
            ||
            $weekly <= 0
            ||
            $weekly > 168
        ) {
            $errors[] =
                'Weekly overtime must be greater than 0 and no more than 168 hours.';
        }


        if (
            $double === false
            ||
            $double <= 0
            ||
            $double > 24
        ) {
            $errors[] =
                'Double-time must be greater than 0 and no more than 24 hours.';
        }


        if (
            $daily !== false
            &&
            $double !== false
            &&
            $double <= $daily
        ) {
            $errors[] =
                'Double-time must begin after the daily overtime threshold.';
        }


        if (
            !in_array(
                $workweekStart,
                [
                    'sunday',
                    'monday'
                ],
                true
            )
        ) {
            $errors[] =
                'Workweek start must be Sunday or Monday.';
        }


        return [
            'data' => [
                'daily_overtime_hours' =>
                    $daily === false
                        ? 8
                        : (float)$daily,

                'weekly_overtime_hours' =>
                    $weekly === false
                        ? 40
                        : (float)$weekly,

                'double_time_hours' =>
                    $double === false
                        ? 12
                        : (float)$double,

                'workweek_start_day' =>
                    $workweekStart
            ],

            'errors' =>
                $errors
        ];
    }
}
