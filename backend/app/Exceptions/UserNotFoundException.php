<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Exceptions;

class UserNotFoundException extends ApiException
{
    public function __construct(string $message = 'No user was found with this email address.')
    {
        parent::__construct(404, 'USER_NOT_FOUND', $message);
    }
}

