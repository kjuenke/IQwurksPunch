<?php
declare(strict_types=1);

use App\Exceptions\EmailAttachmentSizeExceededException;
use App\Services\EmailAttachmentSizePolicyService;
use PHPUnit\Framework\TestCase;

final class EmailAttachmentSizePolicyServiceTest extends TestCase
{
    public function testApplicationConfigurationLoadsExpectedLimit(): void
    {
        $policy =
            new EmailAttachmentSizePolicyService();


        self::assertSame(
            10485760,
            $policy->maxTotalBytes()
        );
    }


    public function testInjectedConfigurationIsSupported(): void
    {
        $policy =
            new EmailAttachmentSizePolicyService(
                [
                    'email_attachments' => [
                        'max_total_bytes' =>
                            2048
                    ]
                ]
            );


        self::assertSame(
            2048,
            $policy->maxTotalBytes()
        );


        $policy->assertWithinLimit(
            2048
        );


        self::assertTrue(
            true
        );
    }


    public function testConfigurationRequiresAttachmentSection(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );


        $this->expectExceptionMessage(
            'The reporting email-attachment configuration is missing.'
        );


        new EmailAttachmentSizePolicyService(
            []
        );
    }


    public function testMaximumSizeMustBeAnInteger(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );


        $this->expectExceptionMessage(
            'The maximum email-attachment size must be an integer.'
        );


        new EmailAttachmentSizePolicyService(
            [
                'email_attachments' => [
                    'max_total_bytes' =>
                        '10485760'
                ]
            ]
        );
    }


    public function testMaximumSizeMustBeWithinSupportedRange(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );


        $this->expectExceptionMessage(
            'The maximum email-attachment size must be between 1 and 52428800 bytes.'
        );


        new EmailAttachmentSizePolicyService(
            [
                'email_attachments' => [
                    'max_total_bytes' =>
                        52428801
                ]
            ]
        );
    }


    public function testNegativeAttachmentTotalIsRejected(): void
    {
        $policy =
            $this->policy(
                100
            );


        $this->expectException(
            InvalidArgumentException::class
        );


        $this->expectExceptionMessage(
            'The total email-attachment size cannot be negative.'
        );


        $policy->assertWithinLimit(
            -1
        );
    }


    public function testAttachmentTotalAboveLimitUsesPermanentFailureException(): void
    {
        $policy =
            $this->policy(
                100
            );


        try {

            $policy->assertWithinLimit(
                101
            );


            self::fail(
                'An oversized attachment total should be rejected.'
            );

        } catch (EmailAttachmentSizeExceededException $exception) {

            self::assertSame(
                101,
                $exception->totalBytes()
            );


            self::assertSame(
                100,
                $exception->maxTotalBytes()
            );


            self::assertSame(
                'Email attachments total 101 bytes, exceeding the configured maximum of 100 bytes.',
                $exception->getMessage()
            );
        }
    }


    private function policy(
        int $maxTotalBytes
    ): EmailAttachmentSizePolicyService
    {
        return
            new EmailAttachmentSizePolicyService(
                [
                    'email_attachments' => [
                        'max_total_bytes' =>
                            $maxTotalBytes
                    ]
                ]
            );
    }
}
