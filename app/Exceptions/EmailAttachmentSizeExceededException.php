<?php
declare(strict_types=1);

namespace App\Exceptions;

use InvalidArgumentException;

final class EmailAttachmentSizeExceededException extends InvalidArgumentException
{
    private int $totalBytes;

    private int $maxTotalBytes;


    public function __construct(
        int $totalBytes,
        int $maxTotalBytes
    )
    {
        $this->totalBytes =
            $totalBytes;


        $this->maxTotalBytes =
            $maxTotalBytes;


        parent::__construct(
            'Email attachments total '
            .
            $totalBytes
            .
            ' bytes, exceeding the configured maximum of '
            .
            $maxTotalBytes
            .
            ' bytes.'
        );
    }


    public function totalBytes(): int
    {
        return
            $this->totalBytes;
    }


    public function maxTotalBytes(): int
    {
        return
            $this->maxTotalBytes;
    }
}
