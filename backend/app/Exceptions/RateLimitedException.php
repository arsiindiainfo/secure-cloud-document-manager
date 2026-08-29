<?php

namespace App\Exceptions;

class RateLimitedException extends ApiException
{
    public function __construct(string $message = 'Too many requests. Please try again shortly.')
    {
        parent::__construct(429, 'RATE_LIMITED', $message);
    }
}
