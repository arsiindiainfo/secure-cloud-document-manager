<?php

namespace App\Exceptions;

class InternalErrorException extends ApiException
{
    public function __construct(string $message = 'An unexpected error occurred.')
    {
        parent::__construct(500, 'INTERNAL_ERROR', $message);
    }
}
