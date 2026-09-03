<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Exceptions;

class PermissionGrantNotFoundException extends ApiException
{
    public function __construct(string $message = 'This user has no direct access to revoke.')
    {
        parent::__construct(404, 'PERMISSION_GRANT_NOT_FOUND', $message);
    }
}

