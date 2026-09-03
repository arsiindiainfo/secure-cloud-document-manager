<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Exceptions;

class ValidationException extends ApiException
{
    /** @param array<int, array{field: string, message: string}> $details */
    public function __construct(array $details, string $message = 'Request payload failed validation.')
    {
        parent::__construct(400, 'VALIDATION_ERROR', $message, $details);
    }
}

