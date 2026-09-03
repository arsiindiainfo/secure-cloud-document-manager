<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Exceptions;

class ShareLinkExpiredException extends ApiException
{
    public function __construct(string $message = 'This link has expired.')
    {
        parent::__construct(409, 'SHARE_LINK_EXPIRED', $message);
    }
}

