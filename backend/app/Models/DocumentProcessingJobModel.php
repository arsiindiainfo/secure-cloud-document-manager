<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * §9.3 — one row per uploaded version, created PENDING by sp_document_new_version
 * / sp_document_upload_commit. Plain single-table model: the only writer
 * after that initial INSERT is ProcessingService::handleCallback(), a
 * single-row update with no cross-row invariant, so no stored procedure is
 * needed here (§5).
 */
class DocumentProcessingJobModel extends Model
{
    protected $table         = 'document_processing_jobs';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['document_version_id', 'status', 'scan_result', 'metadata', 'error_message'];
    // updated_at is DB-managed (ON UPDATE CURRENT_TIMESTAMP) — every write here
    // goes through where()->set()->update(), which bypasses Model's own
    // insert()/update($id,$data) timestamp injection anyway.
    protected $useTimestamps = false;

    public function statusForVersion(int $documentVersionId): ?string
    {
        $row = $this->where('document_version_id', $documentVersionId)->first();

        return $row['status'] ?? null;
    }

    /** @param array<string, mixed>|null $metadata */
    public function markCompleted(int $documentVersionId, ?string $scanResult, ?array $metadata): void
    {
        $this->where('document_version_id', $documentVersionId)->set([
            'status'      => 'COMPLETED',
            'scan_result' => $scanResult,
            'metadata'    => $metadata !== null ? json_encode($metadata) : null,
        ])->update();
    }

    public function markFailed(int $documentVersionId, ?string $errorMessage): void
    {
        $this->where('document_version_id', $documentVersionId)->set([
            'status'        => 'FAILED',
            'error_message' => $errorMessage,
        ])->update();
    }
}
