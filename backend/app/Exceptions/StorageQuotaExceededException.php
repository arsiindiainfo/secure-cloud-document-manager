<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Exceptions;

use App\Constants\Quotas;

class StorageQuotaExceededException extends ApiException
{
    public function __construct()
    {
        $limitMb = (int) (Quotas::MAX_TOTAL_STORAGE_BYTES / 1024 / 1024);
        parent::__construct(
            409,
            'STORAGE_QUOTA_EXCEEDED',
            "This would put you over your {$limitMb}MB storage limit — delete something first, or upload a smaller file.",
        );
    }
}
