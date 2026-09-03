<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Models;

use App\Entities\DocumentVersion;
use CodeIgniter\Model;

/**
 * Plain single-table reads — versions are only ever written by
 * DocumentModel::uploadCommit()/newVersion() (§8.2), never here.
 */
class DocumentVersionModel extends Model
{
    protected $table         = 'document_versions';
    protected $primaryKey    = 'id';
    protected $returnType    = DocumentVersion::class;
    protected $allowedFields = ['thumbnail_s3_key'];
    protected $useTimestamps = false;

    public function current(int $documentId): ?DocumentVersion
    {
        return $this->where('document_id', $documentId)->where('is_current', 1)->first();
    }

    /** @return list<DocumentVersion> newest first */
    public function allForDocument(int $documentId): array
    {
        return $this->where('document_id', $documentId)->orderBy('version_no', 'desc')->findAll();
    }

    public function findForDocument(int $documentId, int $versionId): ?DocumentVersion
    {
        return $this->where('document_id', $documentId)->where('id', $versionId)->first();
    }

    /** §9.3 — written only by ProcessingService::handleCallback() once the Lambda's thumbnail lands in S3. */
    public function setThumbnail(int $versionId, string $thumbnailS3Key): void
    {
        $this->update($versionId, ['thumbnail_s3_key' => $thumbnailS3Key]);
    }

    /** §9.3 — the Lambda's callback only ever knows the S3 key, never the DB id (see ProcessingService). */
    public function findByS3Key(string $s3Key): ?DocumentVersion
    {
        return $this->where('s3_key', $s3Key)->first();
    }
}

