<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Services;

use App\Models\DocumentModel;
use Config\Services;

/**
 * §22.2 — recently accessed documents, a storage-used summary, and folders
 * shared with the caller (not created by them). All three are plain
 * read-only aggregates (§5) — nothing here writes or enforces a business rule.
 */
class DashboardService
{
    public function __construct(private readonly DocumentModel $documents = new DocumentModel())
    {
    }

    /** @return array{recentDocuments: list<array<string, mixed>>, storageUsedBytes: int, sharedFolders: list<array<string, mixed>>} */
    public function summary(): array
    {
        $auth = Services::authContext();
        $db   = db_connect();

        $recentRows = $db->query(<<<'SQL'
            SELECT al.action, al.created_at, d.id AS document_id, d.name AS document_name, d.folder_id
            FROM audit_logs al
            JOIN documents d ON d.id = al.entity_id AND al.entity_type = 'DOCUMENT'
            WHERE al.user_id = ? AND al.action IN ('DOCUMENT_DOWNLOADED', 'DOCUMENT_VERSION_UPLOADED')
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

        return [
            'recentDocuments' => array_map(static fn (array $row): array => [
                'documentId' => (int) $row['document_id'],
                'name'       => $row['document_name'],
                'folderId'   => (int) $row['folder_id'],
                'action'     => $row['action'],
                'at'         => $row['created_at'],
            ], $recentRows),
            'storageUsedBytes' => $this->documents->accessibleStorageBytes($auth->userId(), $auth->isAdmin()),
            'sharedFolders'    => array_map(static fn (array $row): array => [
                'id'   => (int) $row['id'],
                'name' => $row['name'],
            ], $sharedRows),
        ];
    }
}

