<?php

namespace App\Exceptions;

class ValidationException extends ApiException
{
    /** @param array<int, array{field: string, message: string}> $details */
    public function __construct(array $details, string $message = 'Request payload failed validation.')
    {
        parent::__construct(400, 'VALIDATION_ERROR', $message, $details);
    }
}
