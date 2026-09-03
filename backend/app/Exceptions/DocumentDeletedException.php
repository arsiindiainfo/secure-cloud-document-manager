<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Exceptions;

class DocumentDeletedException extends ApiException
{
    public function __construct(string $message = 'This document has been moved to trash.')
    {
        parent::__construct(409, 'DOCUMENT_DELETED', $message);
    }
}

