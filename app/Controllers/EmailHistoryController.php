<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\EmailRepository;
use App\Core\Database;

class EmailHistoryController extends Controller
{
    private EmailRepository $emails;


    public function __construct()
    {
        $this->emails =
            new EmailRepository(
                Database::connection()
            );
    }



    public function index(): void
    {
        $emails =
            $this->emails->all();


        $this->render(
            'reports/email-history.twig',
            [
                'title' => 'Email History',
                'activeMenu' => 'reports',
                'emails' => $emails
            ]
        );
    }
}
