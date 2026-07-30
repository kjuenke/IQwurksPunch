<?php
declare(strict_types=1);

namespace App\Services;

use App\Exceptions\EmailAttachmentSizeExceededException;
use InvalidArgumentException;

final class EmailAttachmentSizePolicyService
{
    private const MAXIMUM_ALLOWED_BYTES =
        52428800;


    private int $maxTotalBytes;


    /**
     * @param array<string,mixed>|null $config
     */
    public function __construct(
        ?array $config = null
    )
    {
        $config =
            $config
            ??
            require __DIR__
            .
            '/../../config/reporting.php';


        $attachmentConfig =
            $config['email_attachments']
            ??
            null;


        if (!is_array($attachmentConfig)) {
            throw new InvalidArgumentException(
                'The reporting email-attachment configuration is missing.'
            );
        }


        $maxTotalBytes =
            $attachmentConfig['max_total_bytes']
            ??
            null;


        if (!is_int($maxTotalBytes)) {
            throw new InvalidArgumentException(
                'The maximum email-attachment size must be an integer.'
            );
        }


        if (
            $maxTotalBytes < 1
            ||
            $maxTotalBytes > self::MAXIMUM_ALLOWED_BYTES
        ) {
            throw new InvalidArgumentException(
                'The maximum email-attachment size must be between 1 and 52428800 bytes.'
            );
        }


        $this->maxTotalBytes =
            $maxTotalBytes;
    }


    public function maxTotalBytes(): int
    {
        return
            $this->maxTotalBytes;
    }


    public function assertWithinLimit(
        int $totalBytes
    ): void
    {
        if ($totalBytes < 0) {
            throw new InvalidArgumentException(
                'The total email-attachment size cannot be negative.'
            );
        }


        if ($totalBytes > $this->maxTotalBytes) {
            throw new EmailAttachmentSizeExceededException(
                $totalBytes,
                $this->maxTotalBytes
            );
        }
    }
}
