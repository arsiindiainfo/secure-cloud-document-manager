<?php

namespace App\Exceptions;

class UnsupportedFileTypeException extends ApiException
{
    public function __construct(string $message = 'This file type is not supported.')
    {
        parent::__construct(400, 'UNSUPPORTED_FILE_TYPE', $message);
    }
}
