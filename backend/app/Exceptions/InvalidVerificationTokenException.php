<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Exceptions;

class InvalidVerificationTokenException extends ApiException
{
    public function __construct(string $message = 'This verification link is invalid or has already been used.')
    {
        parent::__construct(400, 'INVALID_VERIFICATION_TOKEN', $message);
    }
}
