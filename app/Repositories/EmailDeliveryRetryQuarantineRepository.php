<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Container;
use InvalidArgumentException;
use PDO;

final class EmailDeliveryRetryQuarantineRepository
{
    private PDO $db;


    public function __construct(
        ?PDO $db = null
    )
    {
        $this->db =
            $db
            ??
            Container::db();
    }


    public function markPermanentFailure(
        int $attemptId,
        string $errorMessage
    ): bool
    {
        if ($attemptId < 1) {
            throw new InvalidArgumentException(
                'Delivery attempt ID must be greater than zero.'
            );
        }


        $errorMessage =
            trim(
                $errorMessage
            );


        if ($errorMessage === '') {
            $errorMessage =
                'The email delivery retry metadata is invalid.';
        }


        if (
            strlen(
                $errorMessage
            ) > 4000
        ) {
            $errorMessage =
                substr(
                    $errorMessage,
                    0,
                    4000
                );
        }


        $statement =
            $this->db->prepare(
                "
                UPDATE email_delivery_attempts

                SET
                    permanent_failure = 1,
                    error_message = :error_message,
                    completed_at =
                        COALESCE
                        (
                            completed_at,
                            CURRENT_TIMESTAMP
                        )

                WHERE id = :id
                  AND status = 'failed'
                  AND permanent_failure = 0
                "
            );


        $statement->execute(
            [
                'error_message' =>
                    $errorMessage,

                'id' =>
                    $attemptId
            ]
        );


        return
            $statement->rowCount() === 1;
    }
}
