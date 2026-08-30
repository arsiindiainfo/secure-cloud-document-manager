<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;

/**
 * Resolves a caller's effective VIEWER/EDITOR/OWNER permission on a folder
 * or document (§6.3): a direct grant wins over an inherited one, and among
 * inherited folder grants the *closest* ancestor wins — a grant lower in
 * the tree is a floor, never a ceiling, so it only ever strengthens what an
 * ancestor grant already provides.
 *
 * This is a read-only resolution helper, not a stored procedure — §5
 * reserves procedures for multi-table/race-sensitive *writes*; resolving
 * "what can this user do" is exactly the kind of check the plan calls out
 * as living in a Service (DocumentService::authorize()), not the database.
 */
class PermissionResolver
{
    private const RANK = ['VIEWER' => 1, 'EDITOR' => 2, 'OWNER' => 3];

    /** @param BaseConnection<\mysqli, \mysqli_result> $db */
    public function __construct(private readonly BaseConnection $db)
    {
    }

    public function folderPermission(int $userId, int $folderId): ?string
    {
        $row = $this->db->query(<<<'SQL'
            WITH RECURSIVE ancestors AS (
              SELECT id, parent_folder_id, 0 AS depth FROM folders WHERE id = ?
              UNION ALL
              SELECT f.id, f.parent_folder_id, a.depth + 1
              FROM folders f JOIN ancestors a ON f.id = a.parent_folder_id
            )
            SELECT dp.permission
            FROM ancestors a
            JOIN document_permissions dp ON dp.folder_id = a.id AND dp.user_id = ?
            ORDER BY a.depth ASC
            LIMIT 1
        SQL, [$folderId, $userId])->getRowArray();

        return $row['permission'] ?? null;
    }

    public function documentPermission(int $userId, int $documentId, int $folderId): ?string
    {
        $direct = $this->db->query(
            'SELECT permission FROM document_permissions WHERE document_id = ? AND user_id = ?',
            [$documentId, $userId],
        )->getRowArray();

        return $direct['permission'] ?? $this->folderPermission($userId, $folderId);
    }

    public function meets(?string $actual, string $required): bool
    {
        return $actual !== null && self::RANK[$actual] >= self::RANK[$required];
    }
}
