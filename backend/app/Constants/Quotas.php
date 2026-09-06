<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Constants;

/**
 * Per-user limits (demo-scale, not configurable per plan/tier). All four
 * are checked against the user's *current* (non-deleted) state, not a
 * lifetime/ever-created counter — deleting a folder or file frees up its
 * slot, same as it frees the storage bytes it held.
 */
final class Quotas
{
    public const MAX_FOLDERS_PER_USER = 20;
    public const MAX_FILES_PER_USER   = 100;

    /** Per-file — also FileTypes::MAX_UPLOAD_SIZE_BYTES, checked at upload time regardless of quota. */
    public const MAX_FILE_SIZE_BYTES = 10 * 1024 * 1024;

    /** Sum of every current-version size across a user's own (non-deleted) documents. */
    public const MAX_TOTAL_STORAGE_BYTES = 100 * 1024 * 1024;
}
