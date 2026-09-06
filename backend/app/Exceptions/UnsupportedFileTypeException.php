<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Exceptions;

class UnsupportedFileTypeException extends ApiException
{
    public function __construct(string $message = 'This file type is not supported. Allowed: PDF, Word, Excel, PowerPoint, CSV, TXT, RTF, PNG, JPG, GIF, WebP, ZIP.')
    {
        parent::__construct(400, 'UNSUPPORTED_FILE_TYPE', $message);
    }
}

