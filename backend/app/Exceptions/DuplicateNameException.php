<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Exceptions;

class DuplicateNameException extends ApiException
{
    public function __construct(string $message = 'A resource with this name already exists here.')
    {
        parent::__construct(409, 'DUPLICATE_NAME', $message);
    }
}

