<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Exceptions;

class IncorrectPasswordException extends ApiException
{
    public function __construct(string $message = 'Current password is incorrect.')
    {
        parent::__construct(400, 'INCORRECT_PASSWORD', $message);
    }
}
