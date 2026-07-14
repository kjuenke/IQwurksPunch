<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Container;
use App\Core\Flash;
use App\Services\CompanySettingsService;

class SettingsController extends Controller
{
    private CompanySettingsService $settings;


    public function __construct()
    {
        $this->settings =
            Container::companySettingsService();
    }



    public function index(): void
    {
        $settings =
            $this->settings->get();


        $this->render(
            'settings/index.twig',
            [
                'title' =>
                    'Company Settings',

                'activeMenu' =>
                    'settings',

                'settings' =>
                    $settings
            ]
        );
    }



    public function update(): void
    {
        $result =
            $this->settings->update(
                $_POST
            );


        if (
            $result['success']
        ) {

            Flash::success(
                'Settings saved successfully.'
            );

        } else {

            Flash::error(
                'Unable to save settings.'
            );
        }


        header(
            'Location: /admin/settings'
        );

        exit;
    }
}
