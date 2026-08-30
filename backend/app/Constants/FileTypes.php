<?php

namespace App\Constants;

/**
 * The demo's allowed upload types (§2, §14) — PDF, DOCX, XLSX, PNG, JPG,
 * ZIP. Shared by Config\Validation's rule group and DocumentService so the
 * two can never silently drift apart.
 */
final class FileTypes
{
    public const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'image/png',
        'image/jpeg',
        'application/zip',
    ];

    public const MAX_UPLOAD_SIZE_BYTES = 25 * 1024 * 1024;

    public const EXTENSION_BY_MIME_TYPE = [
        'application/pdf'                                                          => 'pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'   => 'docx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'         => 'xlsx',
        'image/png'                                                                 => 'png',
        'image/jpeg'                                                                => 'jpg',
        'application/zip'                                                           => 'zip',
    ];
}
