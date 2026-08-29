<?php

namespace App\Exceptions;

class ForbiddenActionException extends ApiException
{
    public function __construct(string $message = 'Your role does not permit this action.')
    {
        parent::__construct(403, 'FORBIDDEN_ROLE', $message);
    }
}
