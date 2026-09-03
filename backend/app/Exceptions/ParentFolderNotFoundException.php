<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Exceptions;

class ParentFolderNotFoundException extends ApiException
{
    public function __construct(string $message = 'Restore the parent folder first.')
    {
        parent::__construct(422, 'PARENT_FOLDER_NOT_FOUND', $message);
    }
}

