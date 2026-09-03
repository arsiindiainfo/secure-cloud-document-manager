<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Exceptions;

class ForbiddenActionException extends ApiException
{
    public function __construct(string $message = 'Your role does not permit this action.')
    {
        parent::__construct(403, 'FORBIDDEN_ROLE', $message);
    }
}

