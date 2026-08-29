<?php

namespace App\Exceptions;

class InvalidSignatureException extends ApiException
{
    public function __construct(string $message = 'Request signature is missing or invalid.')
    {
        parent::__construct(401, 'INVALID_SIGNATURE', $message);
    }
}
