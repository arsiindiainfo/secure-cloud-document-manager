<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Exceptions;

use App\Constants\Quotas;

class FolderQuotaExceededException extends ApiException
{
    public function __construct()
    {
        parent::__construct(
            409,
            'FOLDER_QUOTA_EXCEEDED',
            'You can have at most ' . Quotas::MAX_FOLDERS_PER_USER . ' folders at a time — delete one to free up a slot.',
        );
    }
}
