<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Services;

use App\Exceptions\DocumentVersionNotFoundException;
use App\Models\AuditLogModel;
use App\Models\DocumentProcessingJobModel;
use App\Models\DocumentVersionModel;

/**
 * §9.3 — the receiving end of the Lambda's callback. Trust here is the
 * InternalHmacFilter that already ran (never a user JWT, never
 * DocumentService::authorize() — there is no caller identity to check).
 */
class ProcessingService
{
    public function __construct(
        private readonly DocumentProcessingJobModel $jobs = new DocumentProcessingJobModel(),
        private readonly DocumentVersionModel $versions = new DocumentVersionModel(),
        private readonly AuditLogModel $auditLog = new AuditLogModel(),
    ) {
    }

    /**
     * Identified by s3Key, not a DB id — an S3 ObjectCreated event (what
     * ultimately drives the Lambda) carries no application id, only the
     * key. If sp_document_upload_commit/new_version hasn't run yet, this
     * throws DocumentVersionNotFoundException and the Lambda is expected to
     * retry with backoff (see infrastructure/aws/lambda/processing-worker).
     *
     * @param array<string, mixed>|null $metadata
     */
    public function handleCallback(
        string $s3Key,
        string $status,
        ?string $scanResult,
        ?array $metadata,
        ?string $thumbnailS3Key,
        ?string $errorMessage,
    ): void {
        $version = $this->versions->findByS3Key($s3Key);
        if ($version === null) {
            throw new DocumentVersionNotFoundException();
        }

        if ($status === 'COMPLETED') {
            $this->jobs->markCompleted($version->id, $scanResult, $metadata);
            if ($thumbnailS3Key !== null) {
                $this->versions->setThumbnail($version->id, $thumbnailS3Key);
            }
            $this->auditLog->record(null, 'DOCUMENT_PROCESSING_COMPLETED', 'DOCUMENT', $version->document_id, [
                'documentVersionId' => $version->id,
                'scanResult'        => $scanResult,
                'hasThumbnail'      => $thumbnailS3Key !== null,
            ]);

            return;
        }

        $this->jobs->markFailed($version->id, $errorMessage);
        $this->auditLog->record(null, 'DOCUMENT_PROCESSING_FAILED', 'DOCUMENT', $version->document_id, [
            'documentVersionId' => $version->id,
            'errorMessage'      => $errorMessage,
        ]);
    }
}

