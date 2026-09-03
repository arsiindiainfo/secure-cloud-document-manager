<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Exceptions;

class ShareLinkNotFoundException extends ApiException
{
    public function __construct(string $message = 'This link is invalid.')
    {
        parent::__construct(404, 'SHARE_LINK_NOT_FOUND', $message);
    }
}

