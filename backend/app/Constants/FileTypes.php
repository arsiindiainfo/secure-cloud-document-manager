<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Constants;

/**
 * The demo's allowed upload types (§2, §14) — common office documents,
 * images, and archives. Deliberately excludes anything that can carry
 * executable/script content when opened (HTML, SVG, JS, executables) —
 * see §14's security note. Shared by Config\Validation's rule group and
 * DocumentService so the two can never silently drift apart.
 */
final class FileTypes
{
    public const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/csv',
        'text/plain',
        'application/rtf',
        'image/png',
        'image/jpeg',
        'image/gif',
        'image/webp',
        'application/zip',
    ];

    public const MAX_UPLOAD_SIZE_BYTES = 25 * 1024 * 1024;

    /** §22.4 — inline preview for PDF/image; everything else falls back to a thumbnail or download-only. */
    public const INLINE_PREVIEWABLE_MIME_TYPES = [
        'application/pdf',
        'image/png',
        'image/jpeg',
        'image/gif',
        'image/webp',
    ];

    public const EXTENSION_BY_MIME_TYPE = [
        'application/pdf'                                                          => 'pdf',
        'application/msword'                                                       => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'   => 'docx',
        'application/vnd.ms-excel'                                                  => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'         => 'xlsx',
        'application/vnd.ms-powerpoint'                                             => 'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'text/csv'                                                                  => 'csv',
        'text/plain'                                                                => 'txt',
        'application/rtf'                                                           => 'rtf',
        'image/png'                                                                 => 'png',
        'image/jpeg'                                                                => 'jpg',
        'image/gif'                                                                 => 'gif',
        'image/webp'                                                                => 'webp',
        'application/zip'                                                           => 'zip',
    ];
}

