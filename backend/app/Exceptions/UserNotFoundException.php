<?php

namespace App\Exceptions;

class UserNotFoundException extends ApiException
{
    public function __construct(string $message = 'No user was found with this email address.')
    {
        parent::__construct(404, 'USER_NOT_FOUND', $message);
    }
}
