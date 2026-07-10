<?php
declare(strict_types=1);

namespace App\Services;

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

use App\Core\Database;
use App\Repositories\EmailRepository;

class MailService
{
    private array $config;

    private EmailRepository $emails;

    public function __construct()
    {
        $this->config =
            require __DIR__
            . '/../../config/mail.php';

       $this->emails =
           new EmailRepository(
               Database::connection()
           );
    }



    public function send(
        string $subject,
        string $body
    ): bool
    {
        $dsn =
            sprintf(
                'smtp://%s:%s@%s:%s',
                urlencode(
                    $this->config['username']
                ),
                urlencode(
                    $this->config['password']
                ),
                $this->config['host'],
                $this->config['port']
            );


        $transport =
            Transport::fromDsn(
                $dsn
            );


        $mailer =
            new Mailer(
                $transport
            );


        $email =
            (new Email())
                ->from(
                    $this->config['from_email']
                )
                ->subject(
                    $subject
                )
                ->text(
                    $body
                );


        foreach (
            $this->config['recipients']
            as $recipient
        ) {
            $email->addTo(
                $recipient
            );
        }
        try {

            $mailer->send(
                $email
            );


            $this->emails->create(
                $subject,
                implode(
                    ', ',
                    $this->config['recipients']
                ),
                'sent'
            );


            return true;


        } catch (\Throwable $e) {


            $this->emails->create(
                $subject,
                implode(
                    ', ',
                    $this->config['recipients']
                ),
                'failed'
            );


            throw $e;
        }

    }
}
