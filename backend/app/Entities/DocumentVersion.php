<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Entities;

use CodeIgniter\Entity\Entity;

/**
 * @property int         $id
 * @property int         $document_id
 * @property int         $version_no
 * @property string      $s3_bucket
 * @property string      $s3_key
 * @property string      $mime_type
 * @property int         $size_bytes
 * @property string      $checksum_sha256
 * @property string|null $thumbnail_s3_key
 * @property bool        $is_current
 * @property int         $uploaded_by
 * @property string|null $uploaded_at
 */
class DocumentVersion extends Entity
{
    protected $casts = [
        'id'          => 'integer',
        'document_id' => 'integer',
        'version_no'  => 'integer',
        'size_bytes'  => 'integer',
        'is_current'  => 'int-bool',
        'uploaded_by' => 'integer',
    ];

    /**
     * Field minimalism (§12) — s3_bucket/s3_key never leave this class; the
     * frontend only ever gets a presigned URL, never the raw object location.
     */
    public function toArray(bool $onlyChanged = false, bool $cast = true, bool $recursive = false): array
    {
        return [
            'id'              => $this->id,
            'documentId'      => $this->document_id,
            'versionNo'       => $this->version_no,
            'mimeType'        => $this->mime_type,
            'sizeBytes'       => $this->size_bytes,
            'checksumSha256'  => $this->checksum_sha256,
            'hasThumbnail'    => $this->thumbnail_s3_key !== null,
            'isCurrent'       => $this->is_current,
            'uploadedBy'      => $this->uploaded_by,
            'uploadedAt'      => $this->uploaded_at,
        ];
    }
}

