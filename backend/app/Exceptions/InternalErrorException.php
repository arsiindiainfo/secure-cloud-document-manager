<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Exceptions;

class InternalErrorException extends ApiException
{
    public function __construct(string $message = 'An unexpected error occurred.')
    {
        parent::__construct(500, 'INTERNAL_ERROR', $message);
    }
}

