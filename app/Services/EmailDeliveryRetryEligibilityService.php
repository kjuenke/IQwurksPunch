<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Container;
use App\Repositories\EmailDeliveryAttemptRepository;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class EmailDeliveryRetryEligibilityService
{
    private EmailDeliveryAttemptRepository $attempts;

    private EmailDeliveryRetryPolicyService $policy;


    public function __construct(
        ?EmailDeliveryAttemptRepository $attempts = null,
        ?EmailDeliveryRetryPolicyService $policy = null
    )
    {
        $this->attempts =
            $attempts
            ??
            new EmailDeliveryAttemptRepository(
                Container::db()
            );


        $this->policy =
            $policy
            ??
            new EmailDeliveryRetryPolicyService();
    }


    /**
     * Return failures that have remained failed for at least the configured
     * delay and are otherwise eligible for another delivery attempt.
     *
     * SQLite CURRENT_TIMESTAMP values are stored in UTC, so completed_at is
     * interpreted as UTC regardless of the company's display timezone.
     *
     * @return array<int,array<string,mixed>>
     */
    public function eligibleFailures(
        ?int $limit = null,
        ?DateTimeImmutable $now = null
    ): array
    {
        $limit =
            $limit
            ??
            $this->policy
                ->batchLimit();


        if (
            $limit < 1
            ||
            $limit > 250
        ) {
            throw new InvalidArgumentException(
                'Retry eligibility limit must be between 1 and 250.'
            );
        }


        $utc =
            new DateTimeZone(
                'UTC'
            );


        $now =
            (
                $now
                ??
                new DateTimeImmutable(
                    'now',
                    $utc
                )
            )->setTimezone(
                $utc
            );


        $eligibleBefore =
            $now->modify(
                '-'
                .
                $this->policy
                    ->delayMinutes()
                .
                ' minutes'
            );


        $eligible = [];


        foreach (
            $this->attempts
                ->retryableFailures(
                    250
                )
            as $failure
        ) {
            $completedAt =
                $this->completedAt(
                    $failure['completed_at']
                    ??
                    null,
                    $utc
                );


            if ($completedAt === null) {

                continue;
            }


            if ($completedAt > $eligibleBefore) {

                continue;
            }


            $eligible[] =
                $failure;


            if (
                count(
                    $eligible
                )
                >=
                $limit
            ) {
                break;
            }
        }


        return $eligible;
    }


    public function batchLimit(): int
    {
        return
            $this->policy
                ->batchLimit();
    }


    public function delayMinutes(): int
    {
        return
            $this->policy
                ->delayMinutes();
    }


    private function completedAt(
        mixed $value,
        DateTimeZone $utc
    ): ?DateTimeImmutable
    {
        if (!is_string($value)) {

            return null;
        }


        $value =
            trim(
                $value
            );


        if ($value === '') {

            return null;
        }


        $completedAt =
            DateTimeImmutable::createFromFormat(
                '!Y-m-d H:i:s',
                $value,
                $utc
            );


        if (!$completedAt instanceof DateTimeImmutable) {

            return null;
        }


        $errors =
            DateTimeImmutable::getLastErrors();


        if (
            is_array(
                $errors
            )
            &&
            (
                $errors['warning_count'] > 0
                ||
                $errors['error_count'] > 0
            )
        ) {
            return null;
        }


        return $completedAt;
    }
}
