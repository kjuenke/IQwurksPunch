<?php
declare(strict_types=1);

namespace App\Core;

use App\Services\CsrfService;
use DateTime;
use DateTimeZone;
use Throwable;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;

final class View
{
    private Environment $twig;

    private CsrfService $csrf;


    public function __construct()
    {
        $loader =
            new FilesystemLoader(
                __DIR__
                .
                '/../Views'
            );


        $this->twig =
            new Environment(
                $loader,
                [
                    'cache' =>
                        false,

                    'debug' =>
                        true,

                    'auto_reload' =>
                        true
                ]
            );


        $this->csrf =
            new CsrfService();


        $this->twig->addFilter(
            new TwigFilter(
                'company_date',
                function (
                    ?string $date
                ): string {

                    if (
                        empty(
                            $date
                        )
                    ) {

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
                                (string)$company['timezone'];
                        }


                        $datetime =
                            new DateTime(
                                $date,
                                new DateTimeZone(
                                    'UTC'
                                )
                            );


                        $datetime->setTimezone(
                            new DateTimeZone(
                                $timezone
                            )
                        );


                        return $datetime->format(
                            'Y-m-d h:i A T'
                        );

                    } catch (Throwable) {

                        return $date;
                    }
                }
            )
        );
    }


    /**
     * @param array<string,mixed> $data
     */
    public function render(
        string $template,
        array $data = []
    ): void
    {
        $csrfToken =
            $this->csrf
                ->token();


        $data['appName'] =
            AppInfo::name();


        $data['appVersion'] =
            AppInfo::version();


        $data['companyName'] =
            AppInfo::company();


        $data['flash'] =
            Flash::get();


        $data['csrfToken'] =
            $csrfToken;


        $data['csrfFieldName'] =
            CsrfService::FIELD_NAME;


        $html =
            $this->twig
                ->render(
                    $template,
                    $data
                );


        echo
            $this->injectCsrfFields(
                $html,
                $csrfToken
            );
    }


    private function injectCsrfFields(
        string $html,
        string $csrfToken
    ): string
    {
        $fieldName =
            htmlspecialchars(
                CsrfService::FIELD_NAME,
                ENT_QUOTES
                |
                ENT_SUBSTITUTE,
                'UTF-8'
            );


        $escapedToken =
            htmlspecialchars(
                $csrfToken,
                ENT_QUOTES
                |
                ENT_SUBSTITUTE,
                'UTF-8'
            );


        $csrfField =
            PHP_EOL
            .
            '<input'
            .
            ' type="hidden"'
            .
            ' name="'
            .
            $fieldName
            .
            '"'
            .
            ' value="'
            .
            $escapedToken
            .
            '"'
            .
            '>'
            .
            PHP_EOL;


        $result =
            preg_replace_callback(
                '~'
                .
                '<form\b'
                .
                '(?=[^>]*\bmethod\s*=\s*'
                .
                '(?:"post"|\'post\'|post)'
                .
                '(?:\s|>))'
                .
                '[^>]*>'
                .
                '~i',
                static function (
                    array $matches
                ) use (
                    $csrfField
                ): string {

                    return
                        $matches[0]
                        .
                        $csrfField;
                },
                $html
            );


        return
            is_string(
                $result
            )
                ? $result
                : $html;
    }
}
