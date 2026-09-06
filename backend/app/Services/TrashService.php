<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Services;

use App\Entities\Document;
use App\Entities\Folder;
use App\Libraries\S3Service;
use App\Models\DocumentModel;
use App\Models\DocumentVersionModel;
use App\Models\FolderModel;
use Config\Services;

/**
 * §22.8 — soft-deleted folders/documents the caller owns, with days
 * remaining before permanent deletion, plus the "delete forever" action
 * itself (purgeDocument/purgeFolder — OWNER only, item must already be in
 * trash). Listing is read-only; the actual soft-delete/restore stay on
 * FolderService/DocumentService, which already own that authorization.
 */
class TrashService
{
    public function __construct(
        private readonly FolderModel $folders = new FolderModel(),
        private readonly DocumentModel $documents = new DocumentModel(),
        private readonly DocumentVersionModel $versions = new DocumentVersionModel(),
        private readonly FolderService $folderService = new FolderService(),
        private readonly DocumentService $documentService = new DocumentService(),
        private readonly S3Service $s3 = new S3Service(),
    ) {
    }

    /** @return array{folders: list<array<string, mixed>>, documents: list<array<string, mixed>>} */
    public function list(): array
    {
        $auth           = Services::authContext();
        $retentionDays  = $this->retentionDays();

        $folders   = $auth->isAdmin() ? $this->folders->allTrashed() : $this->folders->trashedOwnedBy($auth->userId());
        $documents = $auth->isAdmin() ? $this->documents->allTrashed() : $this->documents->trashedOwnedBy($auth->userId());

        return [
            'folders'   => array_map(fn (Folder $folder) => $this->withDaysRemaining($folder->toArray(), $folder->deleted_at, $retentionDays), $folders),
            'documents' => array_map(fn (Document $document) => $this->withDaysRemaining($document->toArray(), $document->deleted_at, $retentionDays), $documents),
        ];
    }

    /** Permanently deletes an already-trashed document — its S3 objects, then the DB rows. OWNER only. */
    public function purgeDocument(int $documentId): void
    {
        $this->documentService->authorize($documentId, 'OWNER', includeDeleted: true);

        $keys = [];
        foreach ($this->versions->allForDocument($documentId) as $version) {
            $keys[] = $version->s3_key;
            if ($version->thumbnail_s3_key !== null) {
                $keys[] = $version->thumbnail_s3_key;
            }
        }
        if ($keys !== []) {
            $this->s3->deleteObjects($keys);
        }

        $this->documents->hardDelete($documentId, Services::authContext()->userId());
    }

    /** Permanently deletes an already-trashed folder and everything under it. OWNER only. */
    public function purgeFolder(int $folderId): void
    {
        $this->folderService->authorize($folderId, 'OWNER', includeDeleted: true);

        $rows = db_connect()->query(<<<'SQL'
            WITH RECURSIVE subtree AS (
              SELECT id FROM folders WHERE id = ?
              UNION ALL
              SELECT f.id FROM folders f JOIN subtree s ON f.parent_folder_id = s.id
            )
            SELECT v.s3_key, v.thumbnail_s3_key
            FROM document_versions v
            JOIN documents d ON d.id = v.document_id
            WHERE d.folder_id IN (SELECT id FROM subtree)
        SQL, [$folderId])->getResultArray();

        $keys = [];
        foreach ($rows as $row) {
            $keys[] = $row['s3_key'];
            if ($row['thumbnail_s3_key'] !== null) {
                $keys[] = $row['thumbnail_s3_key'];
            }
        }
        if ($keys !== []) {
            $this->s3->deleteObjects($keys);
        }

        $this->folders->hardDelete($folderId, Services::authContext()->userId());
    }

    private function retentionDays(): int
    {
        $row = db_connect()->table('trash_retention_settings')->select('retention_days')->get()->getRowArray();

        return $row !== null ? (int) $row['retention_days'] : 30;
    }

    /**
     * @param array<string, mixed> $item
     *
     * @return array<string, mixed>
     */
    private function withDaysRemaining(array $item, ?string $deletedAt, int $retentionDays): array
    {
        $daysSinceDeleted = $deletedAt !== null ? (int) floor((time() - strtotime($deletedAt)) / 86400) : 0;

        return [...$item, 'daysRemaining' => max(0, $retentionDays - $daysSinceDeleted)];
    }
}

