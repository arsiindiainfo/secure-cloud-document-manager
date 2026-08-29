<?php

namespace App\Exceptions;

class FileTooLargeException extends ApiException
{
    public function __construct(string $message = 'File exceeds the maximum upload size.')
    {
        parent::__construct(400, 'FILE_TOO_LARGE', $message);
    }
}
