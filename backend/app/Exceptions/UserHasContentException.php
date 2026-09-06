<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Exceptions;

class UserHasContentException extends ApiException
{
    public function __construct(string $message = 'This user created folders or documents that still exist, so they can\'t be permanently deleted.')
    {
        parent::__construct(409, 'USER_HAS_CONTENT', $message);
    }
}
