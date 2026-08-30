<?php

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
}
