<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Exceptions;

class InvalidSignatureException extends ApiException
{
    public function __construct(string $message = 'Request signature is missing or invalid.')
    {
        parent::__construct(401, 'INVALID_SIGNATURE', $message);
    }
}

