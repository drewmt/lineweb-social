<?php

namespace App\Media;

use RuntimeException;

final class VideoProcessingException extends RuntimeException
{
    public function __construct(public readonly string $failureCode)
    {
        parent::__construct($failureCode);
    }
}
