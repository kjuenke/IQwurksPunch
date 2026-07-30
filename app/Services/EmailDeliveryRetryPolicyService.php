<?php
declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

final class EmailDeliveryRetryPolicyService
{
    private int $scheduledMaxAttempts;

    private int $batchLimit;

    private int $delayMinutes;


    /**
     * @param array<string,mixed>|null $configuration
     */
    public function __construct(
        ?array $configuration = null
    )
    {
        if ($configuration === null) {

            $configuration =
                require dirname(
                    __DIR__,
                    2
                )
                .
                '/config/reporting.php';
        }


        $retryConfiguration =
            $configuration['delivery_retry']
            ??
            null;


        if (!is_array($retryConfiguration)) {
            throw new InvalidArgumentException(
                'The reporting delivery-retry configuration is missing.'
            );
        }


        $this->scheduledMaxAttempts =
            $this->integerWithinRange(
                $retryConfiguration['scheduled_max_attempts']
                ??
                null,
                'Scheduled maximum attempts',
                2,
                10
            );


        $this->batchLimit =
            $this->integerWithinRange(
                $retryConfiguration['batch_limit']
                ??
                null,
                'Retry batch limit',
                1,
                250
            );


        $this->delayMinutes =
            $this->integerWithinRange(
                $retryConfiguration['delay_minutes']
                ??
                null,
                'Retry delay minutes',
                1,
                1440
            );
    }


    public function scheduledMaxAttempts(): int
    {
        return
            $this->scheduledMaxAttempts;
    }


    public function batchLimit(): int
    {
        return
            $this->batchLimit;
    }


    public function delayMinutes(): int
    {
        return
            $this->delayMinutes;
    }


    /**
     * @return array{
     *     scheduled_max_attempts:int,
     *     batch_limit:int,
     *     delay_minutes:int
     * }
     */
    public function all(): array
    {
        return [
            'scheduled_max_attempts' =>
                $this->scheduledMaxAttempts,

            'batch_limit' =>
                $this->batchLimit,

            'delay_minutes' =>
                $this->delayMinutes
        ];
    }


    private function integerWithinRange(
        mixed $value,
        string $label,
        int $minimum,
        int $maximum
    ): int
    {
        if (!is_int($value)) {
            throw new InvalidArgumentException(
                $label
                .
                ' must be an integer.'
            );
        }


        if (
            $value < $minimum
            ||
            $value > $maximum
        ) {
            throw new InvalidArgumentException(
                $label
                .
                ' must be between '
                .
                $minimum
                .
                ' and '
                .
                $maximum
                .
                '.'
            );
        }


        return $value;
    }
}
