<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Models;

use App\Entities\Document;
use App\Exceptions\DocumentDeletedException;
use App\Exceptions\DocumentNotFoundException;
use App\Exceptions\DuplicateNameException;
use App\Exceptions\FolderNotFoundException;
use App\Exceptions\InternalErrorException;
use App\Exceptions\ParentFolderNotFoundException;
use App\Libraries\StoredProcedure;
use CodeIgniter\Model;

class DocumentModel extends Model
{
    protected $table          = 'documents';
    protected $primaryKey     = 'id';
    protected $returnType     = Document::class;
    protected $useSoftDeletes = true;
    protected $allowedFields  = ['folder_id', 'name', 'description', 'tags', 'current_version', 'created_by'];
    protected $useTimestamps  = true;

    /** @return array{documentId: int, versionId: int} */
    public function uploadCommit(
        int $folderId,
        string $name,
        ?string $description,
        ?string $tags,
        string $s3Bucket,
        string $s3Key,
        string $mimeType,
        int $sizeBytes,
        string $checksum,
        int $createdBy,
    ): array {
        $out = (new StoredProcedure($this->db))->call('sp_document_upload_commit', [
            $folderId, $name, $description, $tags, $s3Bucket, $s3Key, $mimeType, $sizeBytes, $checksum, $createdBy,
        ], ['p_document_id', 'p_version_id', 'p_status_code', 'p_message']);

        return match ($out['p_status_code']) {
            'OK'               => ['documentId' => (int) $out['p_document_id'], 'versionId' => (int) $out['p_version_id']],
            'FOLDER_NOT_FOUND' => throw new FolderNotFoundException('Target folder does not exist or is deleted.'),
            'DUPLICATE_NAME'   => throw new DuplicateNameException('A document with this name already exists in this folder.'),
            default            => throw new InternalErrorException($out['p_message'] ?? 'Failed to commit upload.'),
        };
    }

    /** @return array{versionId: int, versionNo: int} */
    public function newVersion(
        int $documentId,
        string $s3Bucket,
        string $s3Key,
        string $mimeType,
        int $sizeBytes,
        string $checksum,
        int $uploadedBy,
    ): array {
        $out = (new StoredProcedure($this->db))->call('sp_document_new_version', [
            $documentId, $s3Bucket, $s3Key, $mimeType, $sizeBytes, $checksum, $uploadedBy,
        ], ['p_version_id', 'p_version_no', 'p_status_code', 'p_message']);

        return match ($out['p_status_code']) {
            'OK'                 => ['versionId' => (int) $out['p_version_id'], 'versionNo' => (int) $out['p_version_no']],
            'DOCUMENT_NOT_FOUND' => throw new DocumentNotFoundException(),
            'DOCUMENT_DELETED'   => throw new DocumentDeletedException(),
            default              => throw new InternalErrorException($out['p_message'] ?? 'Failed to add version.'),
        };
    }

    public function updateMetadata(
        int $documentId,
        string $name,
        ?string $description,
        ?string $tags,
        int $folderId,
        int $updatedBy,
    ): void {
        $out = (new StoredProcedure($this->db))->call('sp_document_update', [
            $documentId, $name, $description, $tags, $folderId, $updatedBy,
        ], ['p_status_code', 'p_message']);

        match ($out['p_status_code']) {
            'OK'                 => null,
            'DOCUMENT_NOT_FOUND' => throw new DocumentNotFoundException(),
            'DOCUMENT_DELETED'   => throw new DocumentDeletedException(),
            'FOLDER_NOT_FOUND'   => throw new FolderNotFoundException('Destination folder does not exist or is deleted.'),
            'DUPLICATE_NAME'     => throw new DuplicateNameException('A document with this name already exists in this folder.'),
            default              => throw new InternalErrorException($out['p_message'] ?? 'Failed to update document.'),
        };
    }

    public function softDelete(int $documentId, int $deletedBy): void
    {
        $out = (new StoredProcedure($this->db))->call('sp_document_soft_delete', [
            $documentId, $deletedBy,
        ], ['p_status_code', 'p_message']);

        match ($out['p_status_code']) {
            'OK'                 => null,
            'DOCUMENT_NOT_FOUND' => throw new DocumentNotFoundException(),
            default              => throw new InternalErrorException($out['p_message'] ?? 'Failed to delete document.'),
        };
    }

    public function restore(int $documentId, int $restoredBy): void
    {
        $out = (new StoredProcedure($this->db))->call('sp_document_restore', [
            $documentId, $restoredBy,
        ], ['p_status_code', 'p_message']);

        match ($out['p_status_code']) {
            'OK'                   => null,
            'DOCUMENT_NOT_FOUND'   => throw new DocumentNotFoundException('Document does not exist or is not in trash.'),
            'PARENT_STILL_DELETED' => throw new ParentFolderNotFoundException('Restore the containing folder first.'),
            default                => throw new InternalErrorException($out['p_message'] ?? 'Failed to restore document.'),
        };
    }

    /** @return list<Document> */
    public function childDocuments(int $folderId): array
    {
        return $this->where('folder_id', $folderId)->orderBy('name', 'asc')->findAll();
    }

    /**
     * §17/§24 — backs GET /documents. Visibility (which documents the
     * caller may even see) is resolved inside sp_document_search itself
     * (§6.3: any qualifying grant path, direct or inherited) — this only
     * shapes params in and rows out.
     *
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function search(
        int $userId,
        bool $isAdmin,
        ?string $search,
        ?int $folderId,
        ?string $mimeType,
        string $sort,
        string $direction,
        int $page,
        int $limit,
    ): array {
        [$rows, $out] = (new StoredProcedure($this->db))->callWithResultSet('sp_document_search', [
            $userId, $isAdmin ? 1 : 0, $search, $folderId, $mimeType, $sort, $direction, $page, $limit,
        ], ['p_total_count']);

        $items = array_map(static fn (array $row): array => [
            'id'             => (int) $row['id'],
            'name'           => $row['name'],
            'description'    => $row['description'],
            'tags'           => $row['tags'] === null || $row['tags'] === '' ? [] : array_map('trim', explode(',', $row['tags'])),
            'folderId'       => (int) $row['folder_id'],
            'folderName'     => $row['folder_name'],
            'currentVersion' => (int) $row['current_version'],
            'mimeType'       => $row['mime_type'],
            'sizeBytes'      => (int) $row['size_bytes'],
            'hasThumbnail'   => $row['thumbnail_s3_key'] !== null,
            'createdBy'      => (int) $row['created_by'],
            'createdAt'      => $row['created_at'],
            'updatedAt'      => $row['updated_at'],
        ], $rows);

        return ['items' => $items, 'total' => (int) $out['p_total_count']];
    }

    /**
     * §22.8 — every soft-deleted document, ADMIN's trash view.
     *
     * @return list<Document>
     */
    public function allTrashed(): array
    {
        return $this->onlyDeleted()->orderBy('deleted_at', 'desc')->findAll();
    }

    /**
     * §22.8 — soft-deleted documents the caller is OWNER of (direct grant only — trash is not inherited).
     *
     * @return list<Document>
     */
    public function trashedOwnedBy(int $userId): array
    {
        return $this->onlyDeleted()->select('documents.*')
            ->join('document_permissions', 'document_permissions.document_id = documents.id')
            ->where('document_permissions.user_id', $userId)
            ->where('document_permissions.permission', 'OWNER')
            ->orderBy('documents.deleted_at', 'desc')
            ->findAll();
    }

    /**
     * §22.2 dashboard — total bytes across every accessible document's
     * current version. Mirrors sp_document_search's visibility resolution
     * (§6.3: direct grant or any ancestor-folder grant) as a plain read;
     * this is an aggregate query, not a business-rule write, so no
     * stored procedure is warranted here (§5).
     */
    public function accessibleStorageBytes(int $userId, bool $isAdmin): int
    {
        if ($isAdmin) {
            $row = $this->db->query(<<<'SQL'
                SELECT COALESCE(SUM(v.size_bytes), 0) AS total
                FROM documents d
                JOIN document_versions v ON v.document_id = d.id AND v.is_current = 1
                WHERE d.deleted_at IS NULL
            SQL)->getRowArray();

            return (int) $row['total'];
        }

        $row = $this->db->query(<<<'SQL'
            WITH RECURSIVE granted_folders AS (
              SELECT folder_id AS id FROM document_permissions
                WHERE user_id = ? AND folder_id IS NOT NULL
              UNION ALL
              SELECT f.id FROM folders f
              JOIN granted_folders gf ON f.parent_folder_id = gf.id
              WHERE f.deleted_at IS NULL
            ),
            accessible AS (
              SELECT document_id AS id FROM document_permissions WHERE user_id = ? AND document_id IS NOT NULL
              UNION
              SELECT doc.id FROM documents doc JOIN granted_folders gf ON gf.id = doc.folder_id
            )
            SELECT COALESCE(SUM(v.size_bytes), 0) AS total
            FROM accessible a
            JOIN documents d ON d.id = a.id AND d.deleted_at IS NULL
            JOIN document_versions v ON v.document_id = d.id AND v.is_current = 1
        SQL, [$userId, $userId])->getRowArray();

        return (int) $row['total'];
    }
}

