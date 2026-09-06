<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Services;

use App\Constants\Quotas;
use App\Models\DocumentModel;
use App\Models\FolderModel;
use Config\Services;

/**
 * §22.2 — recently accessed documents, a storage-used summary, folders
 * shared with the caller (not created by them), and their own quota usage.
 * All plain read-only aggregates (§5) — nothing here writes or enforces a
 * business rule (the actual quota enforcement lives in FolderService and
 * DocumentService, at create/upload time).
 */
class DashboardService
{
    public function __construct(
        private readonly DocumentModel $documents = new DocumentModel(),
        private readonly FolderModel $folders = new FolderModel(),
    ) {
    }

    /**
     * @return array{
     *     recentDocuments: list<array<string, mixed>>,
     *     storageUsedBytes: int,
     *     sharedFolders: list<array<string, mixed>>,
     *     quotas: array{
     *         folders: array{used: int, limit: int},
     *         files: array{used: int, limit: int},
     *         storageBytes: array{used: int, limit: int}
     *     }
     * }
     */
    public function summary(): array
    {
        $auth = Services::authContext();
        $db   = db_connect();

        $recentRows = $db->query(<<<'SQL'
            SELECT al.action, al.created_at, d.id AS document_id, d.name AS document_name, d.folder_id,
              dv.mime_type, dv.thumbnail_s3_key
            FROM audit_logs al
            JOIN documents d ON d.id = al.entity_id AND al.entity_type = 'DOCUMENT'
            LEFT JOIN document_versions dv ON dv.document_id = d.id AND dv.is_current = 1
            WHERE al.user_id = ? AND al.action IN ('DOCUMENT_UPLOADED', 'DOCUMENT_DOWNLOADED', 'DOCUMENT_VERSION_UPLOADED')
            ORDER BY al.created_at DESC
            LIMIT 10
        SQL, [$auth->userId()])->getResultArray();

        $sharedRows = $db->query(<<<'SQL'
            SELECT f.id, f.name
            FROM document_permissions dp
            JOIN folders f ON f.id = dp.folder_id AND f.deleted_at IS NULL
            WHERE dp.user_id = ? AND dp.folder_id IS NOT NULL AND dp.granted_by != ?
            ORDER BY dp.granted_at DESC
            LIMIT 10
        SQL, [$auth->userId(), $auth->userId()])->getResultArray();

        $ownUsage = $this->documents->ownUsage($auth->userId());

        return [
            'recentDocuments' => array_map(static fn (array $row): array => [
                'documentId'   => (int) $row['document_id'],
                'name'         => $row['document_name'],
                'folderId'     => (int) $row['folder_id'],
                'action'       => $row['action'],
                'at'           => $row['created_at'],
                'mimeType'     => $row['mime_type'],
                'hasThumbnail' => $row['thumbnail_s3_key'] !== null,
            ], $recentRows),
            'storageUsedBytes' => $this->documents->accessibleStorageBytes($auth->userId(), $auth->isAdmin()),
            'sharedFolders'    => array_map(static fn (array $row): array => [
                'id'   => (int) $row['id'],
                'name' => $row['name'],
            ], $sharedRows),
            'quotas' => [
                'folders'      => ['used' => $this->folders->activeCountForUser($auth->userId()), 'limit' => Quotas::MAX_FOLDERS_PER_USER],
                'files'        => ['used' => $ownUsage['count'], 'limit' => Quotas::MAX_FILES_PER_USER],
                'storageBytes' => ['used' => $ownUsage['totalBytes'], 'limit' => Quotas::MAX_TOTAL_STORAGE_BYTES],
            ],
        ];
    }
}

