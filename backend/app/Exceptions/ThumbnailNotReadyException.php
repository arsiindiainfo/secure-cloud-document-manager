<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Exceptions;

class ThumbnailNotReadyException extends ApiException
{
    public function __construct(string $message = 'Thumbnail is still processing.')
    {
        parent::__construct(404, 'THUMBNAIL_NOT_READY', $message);
    }
}

