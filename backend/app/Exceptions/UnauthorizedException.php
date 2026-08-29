<?php

namespace App\Exceptions;

class UnauthorizedException extends ApiException
{
    public function __construct(string $message = 'Missing or expired access token.')
    {
        parent::__construct(401, 'UNAUTHORIZED', $message);
    }
}
