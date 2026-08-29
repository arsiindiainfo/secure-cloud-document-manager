<?php

namespace App\Exceptions;

class ThumbnailNotReadyException extends ApiException
{
    public function __construct(string $message = 'Thumbnail is still processing.')
    {
        parent::__construct(404, 'THUMBNAIL_NOT_READY', $message);
    }
}
