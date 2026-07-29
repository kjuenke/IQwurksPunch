<?php
declare(strict_types=1);

use App\Services\MailService;
use PHPUnit\Framework\TestCase;

final class MailServiceAttachmentTest extends TestCase
{
    private MailService $service;


    protected function setUp(): void
    {
        parent::setUp();


        $reflection =
            new ReflectionClass(
                MailService::class
            );


        $this->service =
            $reflection
                ->newInstanceWithoutConstructor();
    }


    public function testValidAttachmentIsNormalized(): void
    {
        $attachments =
            $this->normalize(
                [
                    [
                        'filename' =>
                            'daily-payroll.csv',

                        'content_type' =>
                            'text/csv',

                        'contents' =>
                            "Date,Hours\n2026-07-29,8.00\n"
                    ]
                ]
            );


        self::assertCount(
            1,
            $attachments
        );


        self::assertSame(
            'daily-payroll.csv',
            $attachments[0]['filename']
        );


        self::assertSame(
            'text/csv',
            $attachments[0]['content_type']
        );


        self::assertSame(
            "Date,Hours\n2026-07-29,8.00\n",
            $attachments[0]['contents']
        );
    }


    public function testAttachmentFilenameIsReducedToSafeBasename(): void
    {
        $attachments =
            $this->normalize(
                [
                    [
                        'filename' =>
                            "../exports/payroll\r\n.csv",

                        'content_type' =>
                            'text/csv',

                        'contents' =>
                            'content'
                    ]
                ]
            );


        self::assertSame(
            'payroll.csv',
            $attachments[0]['filename']
        );
    }


    public function testMissingContentTypeUsesBinaryFallback(): void
    {
        $attachments =
            $this->normalize(
                [
                    [
                        'filename' =>
                            'payroll.dat',

                        'contents' =>
                            'content'
                    ]
                ]
            );


        self::assertSame(
            'application/octet-stream',
            $attachments[0]['content_type']
        );
    }


    public function testEmptyContentTypeUsesBinaryFallback(): void
    {
        $attachments =
            $this->normalize(
                [
                    [
                        'filename' =>
                            'payroll.dat',

                        'content_type' =>
                            '',

                        'contents' =>
                            'content'
                    ]
                ]
            );


        self::assertSame(
            'application/octet-stream',
            $attachments[0]['content_type']
        );
    }


    public function testAttachmentListProvidesNamesAndTotalSize(): void
    {
        $attachments =
            $this->normalize(
                [
                    [
                        'filename' =>
                            'daily.csv',

                        'content_type' =>
                            'text/csv',

                        'contents' =>
                            '12345'
                    ],
                    [
                        'filename' =>
                            'daily.pdf',

                        'content_type' =>
                            'application/pdf',

                        'contents' =>
                            '1234567890'
                    ]
                ]
            );


        self::assertSame(
            [
                'daily.csv',
                'daily.pdf'
            ],
            $this->attachmentNames(
                $attachments
            )
        );


        self::assertSame(
            15,
            $this->attachmentSizeBytes(
                $attachments
            )
        );
    }


    public function testAttachmentMustBeAnArray(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );


        $this->expectExceptionMessage(
            'Email attachment #1 must be an array.'
        );


        $this->normalize(
            [
                'not-an-array'
            ]
        );
    }


    public function testAttachmentRequiresValidFilename(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );


        $this->expectExceptionMessage(
            'Email attachment #1 requires a valid filename.'
        );


        $this->normalize(
            [
                [
                    'filename' =>
                        '../',

                    'contents' =>
                        'content'
                ]
            ]
        );
    }


    public function testAttachmentContentsMustBeStringData(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );


        $this->expectExceptionMessage(
            'Email attachment payroll.csv must contain string data.'
        );


        $this->normalize(
            [
                [
                    'filename' =>
                        'payroll.csv',

                    'content_type' =>
                        'text/csv',

                    'contents' =>
                        [
                            'not',
                            'a',
                            'string'
                        ]
                ]
            ]
        );
    }


    public function testAttachmentCannotBeEmpty(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );


        $this->expectExceptionMessage(
            'Email attachment payroll.csv cannot be empty.'
        );


        $this->normalize(
            [
                [
                    'filename' =>
                        'payroll.csv',

                    'content_type' =>
                        'text/csv',

                    'contents' =>
                        ''
                ]
            ]
        );
    }


    /**
     * @param array<int,mixed> $attachments
     *
     * @return array<int,array{
     *     filename:string,
     *     content_type:string,
     *     contents:string
     * }>
     */
    private function normalize(
        array $attachments
    ): array
    {
        $reflection =
            new ReflectionClass(
                MailService::class
            );


        $method =
            $reflection->getMethod(
                'normalizeAttachments'
            );


        return
            $method->invoke(
                $this->service,
                $attachments
            );
    }


    /**
     * @param array<int,array{
     *     filename:string,
     *     content_type:string,
     *     contents:string
     * }> $attachments
     *
     * @return array<int,string>
     */
    private function attachmentNames(
        array $attachments
    ): array
    {
        $reflection =
            new ReflectionClass(
                MailService::class
            );


        $method =
            $reflection->getMethod(
                'attachmentNames'
            );


        return
            $method->invoke(
                $this->service,
                $attachments
            );
    }


    /**
     * @param array<int,array{
     *     filename:string,
     *     content_type:string,
     *     contents:string
     * }> $attachments
     */
    private function attachmentSizeBytes(
        array $attachments
    ): int
    {
        $reflection =
            new ReflectionClass(
                MailService::class
            );


        $method =
            $reflection->getMethod(
                'attachmentSizeBytes'
            );


        return
            $method->invoke(
                $this->service,
                $attachments
            );
    }
}
