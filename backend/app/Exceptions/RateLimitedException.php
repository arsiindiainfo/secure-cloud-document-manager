<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Exceptions;

class RateLimitedException extends ApiException
{
    public function __construct(string $message = 'Too many requests. Please try again shortly.')
    {
        parent::__construct(429, 'RATE_LIMITED', $message);
    }
}

