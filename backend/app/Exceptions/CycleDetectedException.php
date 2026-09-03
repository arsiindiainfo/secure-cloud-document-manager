<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Exceptions;

/**
 * Extends the base §14 catalog with one project-specific code: rejecting a
 * folder move into its own descendant (sp_folder_rename_move's cycle check).
 */
class CycleDetectedException extends ApiException
{
    public function __construct(string $message = 'Cannot move a folder into its own descendant.')
    {
        parent::__construct(422, 'CYCLE_DETECTED', $message);
    }
}

