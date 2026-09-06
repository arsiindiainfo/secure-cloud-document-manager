<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Exceptions;

class NotInTrashException extends ApiException
{
    public function __construct(string $message = 'This item must be moved to trash before it can be permanently deleted.')
    {
        parent::__construct(409, 'NOT_IN_TRASH', $message);
    }
}
