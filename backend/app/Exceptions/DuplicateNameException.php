<?php

namespace App\Exceptions;

class DuplicateNameException extends ApiException
{
    public function __construct(string $message = 'A resource with this name already exists here.')
    {
        parent::__construct(409, 'DUPLICATE_NAME', $message);
    }
}
