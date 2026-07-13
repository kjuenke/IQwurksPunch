<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Container;
use App\Logging\LoggerInterface;
use App\Repositories\EmailRepository;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;
use Throwable;

class MailService
{
    private array $config;

    private EmailRepository $emails;

    private LoggerInterface $logger;


    public function __construct()
    {
        $this->config =
            require __DIR__
            .
            '/../../config/mail.php';


        $this->emails =
            new EmailRepository(
                Container::db()
            );


        $this->logger =
            Container::logger(
                'mail'
            );
    }


    public function send(
        string $subject,
        string $body
    ): bool
    {
        $recipients =
            $this->config['recipients']
            ??
            [];


        $recipientCount =
            is_array(
                $recipients
            )
            ?
            count(
                $recipients
            )
            :
            0;


        $this->logger->info(
            'Email delivery started.',
            [
                'subject' =>
                    $subject,

                'recipient_count' =>
                    $recipientCount,

                'transport_host' =>
                    $this->config['host']
                    ??
                    null,

                'transport_port' =>
                    $this->config['port']
                    ??
                    null,
            ]
        );


        if ($recipientCount === 0) {

            $this->logger->error(
                'Email delivery cannot continue because no recipients are configured.',
                [
                    'subject' =>
                        $subject,
                ]
            );


            $this->recordHistory(
                $subject,
                $recipients,
                'failed'
            );


            return false;
        }


        try {

            $dsn =
                sprintf(
                    'smtp://%s:%s@%s:%s',
                    urlencode(
                        (string)$this->config['username']
                    ),
                    urlencode(
                        (string)$this->config['password']
                    ),
                    (string)$this->config['host'],
                    (string)$this->config['port']
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
                        (string)$this->config['from_email']
                    )
                    ->subject(
                        $subject
                    )
                    ->text(
                        $body
                    );


            foreach ($recipients as $recipient) {

                $email->addTo(
                    (string)$recipient
                );
            }


            $mailer->send(
                $email
            );


            $this->recordHistory(
                $subject,
                $recipients,
                'sent'
            );


            $this->logger->info(
                'Email delivery completed successfully.',
                [
                    'subject' =>
                        $subject,

                    'recipient_count' =>
                        $recipientCount,
                ]
            );


            return true;

        } catch (Throwable $exception) {

            $this->recordHistory(
                $subject,
                $recipients,
                'failed'
            );


            $this->logger->error(
                'Email delivery failed.',
                [
                    'subject' =>
                        $subject,

                    'recipient_count' =>
                        $recipientCount,

                    'exception_class' =>
                        $exception::class,

                    'exception_message' =>
                        $exception->getMessage(),

                    'exception_file' =>
                        $exception->getFile(),

                    'exception_line' =>
                        $exception->getLine(),
                ]
            );


            throw $exception;
        }
    }


    private function recordHistory(
        string $subject,
        array $recipients,
        string $status
    ): void
    {
        try {

            $recorded =
                $this->emails->create(
                    $subject,
                    implode(
                        ', ',
                        $recipients
                    ),
                    $status
                );


            if (!$recorded) {

                $this->logger->warning(
                    'Email delivery history could not be recorded.',
                    [
                        'subject' =>
                            $subject,

                        'status' =>
                            $status,

                        'recipient_count' =>
                            count(
                                $recipients
                            ),
                    ]
                );
            }

        } catch (Throwable $exception) {

            $this->logger->warning(
                'Email delivery history raised an exception.',
                [
                    'subject' =>
                        $subject,

                    'status' =>
                        $status,

                    'recipient_count' =>
                        count(
                            $recipients
                        ),

                    'exception_class' =>
                        $exception::class,

                    'exception_message' =>
                        $exception->getMessage(),
                ]
            );
        }
    }
}
