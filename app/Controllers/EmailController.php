<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\MailService;
use App\Core\Container;

class EmailController extends Controller
{
    private MailService $mail;


    public function __construct()
    {
        $this->mail =
            Container::mailService();
    }

    public function test(): void
    {
        try {

            $this->mail->send(
                'IQwurksPunch Email Test',
                'The IQwurksPunch email system is working.'
            );

            echo "Email sent successfully.";

        } catch (\Throwable $e) {

            http_response_code(500);

            echo "Email failed: ";
            echo $e->getMessage();

        }
    }
}
