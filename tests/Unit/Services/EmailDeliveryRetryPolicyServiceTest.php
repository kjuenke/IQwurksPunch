<?php
declare(strict_types=1);

use App\Services\EmailDeliveryRetryPolicyService;
use PHPUnit\Framework\TestCase;

final class EmailDeliveryRetryPolicyServiceTest extends TestCase
{
    public function testApplicationConfigurationLoadsExpectedPolicy(): void
    {
        $policy =
            new EmailDeliveryRetryPolicyService();


        self::assertSame(
            3,
            $policy->scheduledMaxAttempts()
        );


        self::assertSame(
            10,
            $policy->batchLimit()
        );


        self::assertSame(
            5,
            $policy->delayMinutes()
        );


        self::assertSame(
            [
                'scheduled_max_attempts' =>
                    3,

                'batch_limit' =>
                    10,

                'delay_minutes' =>
                    5
            ],
            $policy->all()
        );
    }


    public function testInjectedConfigurationIsSupported(): void
    {
        $policy =
            new EmailDeliveryRetryPolicyService(
                [
                    'delivery_retry' => [
                        'scheduled_max_attempts' =>
                            4,

                        'batch_limit' =>
                            25,

                        'delay_minutes' =>
                            15
                    ]
                ]
            );


        self::assertSame(
            4,
            $policy->scheduledMaxAttempts()
        );


        self::assertSame(
            25,
            $policy->batchLimit()
        );


        self::assertSame(
            15,
            $policy->delayMinutes()
        );
    }


    public function testConfigurationRequiresRetrySection(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );


        $this->expectExceptionMessage(
            'The reporting delivery-retry configuration is missing.'
        );


        new EmailDeliveryRetryPolicyService(
            []
        );
    }


    public function testScheduledMaximumAttemptsMustPermitRetry(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );


        $this->expectExceptionMessage(
            'Scheduled maximum attempts must be between 2 and 10.'
        );


        new EmailDeliveryRetryPolicyService(
            [
                'delivery_retry' => [
                    'scheduled_max_attempts' =>
                        1,

                    'batch_limit' =>
                        10,

                    'delay_minutes' =>
                        5
                ]
            ]
        );
    }


    public function testRetryPolicyValuesMustBeIntegers(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );


        $this->expectExceptionMessage(
            'Retry batch limit must be an integer.'
        );


        new EmailDeliveryRetryPolicyService(
            [
                'delivery_retry' => [
                    'scheduled_max_attempts' =>
                        3,

                    'batch_limit' =>
                        '10',

                    'delay_minutes' =>
                        5
                ]
            ]
        );
    }


    public function testRetryDelayMustRemainWithinSafeRange(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );


        $this->expectExceptionMessage(
            'Retry delay minutes must be between 1 and 1440.'
        );


        new EmailDeliveryRetryPolicyService(
            [
                'delivery_retry' => [
                    'scheduled_max_attempts' =>
                        3,

                    'batch_limit' =>
                        10,

                    'delay_minutes' =>
                        0
                ]
            ]
        );
    }
}
