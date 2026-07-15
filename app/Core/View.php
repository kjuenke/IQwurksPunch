<?php
declare(strict_types=1);

namespace App\Core;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;


class View
{
    private Environment $twig;


    public function __construct()
    {
        $loader = new FilesystemLoader(
            __DIR__ . '/../Views'
        );


        $this->twig = new Environment(
            $loader,
            [
                'cache' => false,
                'debug' => true,
                'auto_reload' => true,
            ]
        );


        $this->twig->addFilter(
            new TwigFilter(
                'company_date',
                function (?string $date): string {

                    if (empty($date)) {
                        return '';
                    }


                    try {

                        $timezone =
                            date_default_timezone_get();


                        $company =
                            Container::companySettingsRepository()
                                ->get();

                        if (
                            !empty(
                                $company['timezone']
                            )
                        ) {

                            $timezone =
                                $company['timezone'];
                        }


                        $datetime =
                            new \DateTime(
                                $date,
                                new \DateTimeZone('UTC')
                            );


                        $datetime->setTimezone(
                            new \DateTimeZone(
                                $timezone
                            )
                        );


                        return $datetime->format(
                            'Y-m-d h:i A T'
                        );


                    } catch (\Throwable $e) {

                        return $date;
                    }
                }
            )
        );
    }



    public function render(
        string $template,
        array $data = []
    ): void
    {
        $data['appName'] =
            AppInfo::name();


        $data['appVersion'] =
            AppInfo::version();


        $data['companyName'] =
            AppInfo::company();


        $data['flash'] =
            Flash::get();


        echo $this->twig->render(
            $template,
            $data
        );
    }
}
