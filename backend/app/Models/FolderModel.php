<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Models;

use App\Entities\Folder;
use App\Exceptions\CycleDetectedException;
use App\Exceptions\DuplicateNameException;
use App\Exceptions\FolderNotFoundException;
use App\Exceptions\InternalErrorException;
use App\Exceptions\NotInTrashException;
use App\Exceptions\ParentFolderNotFoundException;
use App\Libraries\StoredProcedure;
use CodeIgniter\Model;

/**
 * Reads use the query builder directly; every write goes through a stored
 * procedure (§8, §16) via StoredProcedure.
 */
class FolderModel extends Model
{
    protected $table          = 'folders';
    protected $primaryKey     = 'id';
    protected $returnType     = Folder::class;
    protected $useSoftDeletes = true;
    protected $allowedFields  = ['name', 'parent_folder_id', 'created_by'];
    protected $useTimestamps  = true;

    public function create(string $name, ?int $parentFolderId, int $createdBy): int
    {
        $out = (new StoredProcedure($this->db))->call('sp_folder_create', [
            $name, $parentFolderId, $createdBy,
        ], ['p_folder_id', 'p_status_code', 'p_message']);

        return match ($out['p_status_code']) {
            'OK'               => (int) $out['p_folder_id'],
            'PARENT_NOT_FOUND' => throw new ParentFolderNotFoundException('Parent folder does not exist or is deleted.'),
            'DUPLICATE_NAME'   => throw new DuplicateNameException('A folder with this name already exists here.'),
            default            => throw new InternalErrorException($out['p_message'] ?? 'Failed to create folder.'),
        };
    }

    public function renameMove(int $folderId, string $newName, ?int $newParentFolderId, int $updatedBy): void
    {
        $out = (new StoredProcedure($this->db))->call('sp_folder_rename_move', [
            $folderId, $newName, $newParentFolderId, $updatedBy,
        ], ['p_status_code', 'p_message']);

        match ($out['p_status_code']) {
            'OK'               => null,
            'FOLDER_NOT_FOUND' => throw new FolderNotFoundException(),
            'PARENT_NOT_FOUND' => throw new ParentFolderNotFoundException('Destination folder does not exist or is deleted.'),
            'CYCLE_DETECTED'   => throw new CycleDetectedException(),
            'DUPLICATE_NAME'   => throw new DuplicateNameException('A folder with this name already exists here.'),
            default            => throw new InternalErrorException($out['p_message'] ?? 'Failed to update folder.'),
        };
    }

    /** @return int total folders + documents moved to trash */
    public function softDelete(int $folderId, int $deletedBy): int
    {
        $out = (new StoredProcedure($this->db))->call('sp_folder_soft_delete', [
            $folderId, $deletedBy,
        ], ['p_affected_count', 'p_status_code', 'p_message']);

        return match ($out['p_status_code']) {
            'OK'               => (int) $out['p_affected_count'],
            'FOLDER_NOT_FOUND' => throw new FolderNotFoundException(),
            default            => throw new InternalErrorException($out['p_message'] ?? 'Failed to delete folder.'),
        };
    }

    /**
     * Caller must delete every S3 object in the subtree first — see
     * TrashService::purgeFolder().
     *
     * @return int total folders + documents permanently removed
     */
    public function hardDelete(int $folderId, int $deletedBy): int
    {
        $out = (new StoredProcedure($this->db))->call('sp_folder_hard_delete', [
            $folderId, $deletedBy,
        ], ['p_affected_count', 'p_status_code', 'p_message']);

        return match ($out['p_status_code']) {
            'OK'               => (int) $out['p_affected_count'],
            'FOLDER_NOT_FOUND' => throw new FolderNotFoundException(),
            'NOT_IN_TRASH'     => throw new NotInTrashException(),
            default            => throw new InternalErrorException($out['p_message'] ?? 'Failed to permanently delete folder.'),
        };
    }

    public function restore(int $folderId, int $restoredBy): void
    {
        $out = (new StoredProcedure($this->db))->call('sp_folder_restore', [
            $folderId, $restoredBy,
        ], ['p_status_code', 'p_message']);

        match ($out['p_status_code']) {
            'OK'                   => null,
            'FOLDER_NOT_FOUND'     => throw new FolderNotFoundException('Folder does not exist or is not in trash.'),
            'PARENT_STILL_DELETED' => throw new ParentFolderNotFoundException('Restore the parent folder first.'),
            default                => throw new InternalErrorException($out['p_message'] ?? 'Failed to restore folder.'),
        };
    }

    /**
     * Root-first ancestor chain, current folder last — exactly what the
     * Folder Browser's breadcrumb (§22.3) renders.
     *
     * @return list<array{id: int, name: string}>
     */
    public function ancestorChain(int $folderId): array
    {
        $rows = $this->db->query(<<<'SQL'
            WITH RECURSIVE chain AS (
              SELECT id, parent_folder_id, name, 0 AS depth FROM folders WHERE id = ?
              UNION ALL
              SELECT f.id, f.parent_folder_id, f.name, c.depth + 1
              FROM folders f JOIN chain c ON f.id = c.parent_folder_id
            )
            SELECT id, name FROM chain ORDER BY depth DESC
        SQL, [$folderId])->getResultArray();

        return array_map(static fn (array $row): array => ['id' => (int) $row['id'], 'name' => (string) $row['name']], $rows);
    }

    /** @return list<Folder> */
    public function childFolders(?int $parentFolderId): array
    {
        return $this->where('parent_folder_id', $parentFolderId)->orderBy('name', 'asc')->findAll();
    }

    /**
     * Root folders a non-admin user has a direct grant on — see §16: no
     * ancestor to inherit from at the top of the tree.
     *
     * @return list<Folder>
     */
    public function accessibleRootFolders(int $userId): array
    {
        return $this->select('folders.*')
            ->join('document_permissions', 'document_permissions.folder_id = folders.id')
            ->where('folders.parent_folder_id', null)
            ->where('folders.deleted_at', null)
            ->where('document_permissions.user_id', $userId)
            ->orderBy('folders.name', 'asc')
            ->findAll();
    }

    /**
     * §22.8 — every soft-deleted folder, ADMIN's trash view.
     *
     * @return list<Folder>
     */
    public function allTrashed(): array
    {
        return $this->onlyDeleted()->orderBy('deleted_at', 'desc')->findAll();
    }

    /**
     * §22.8 — soft-deleted folders the caller is OWNER of (direct grant only — trash is not inherited).
     *
     * @return list<Folder>
     */
    public function trashedOwnedBy(int $userId): array
    {
        return $this->onlyDeleted()->select('folders.*')
            ->join('document_permissions', 'document_permissions.folder_id = folders.id')
            ->where('document_permissions.user_id', $userId)
            ->where('document_permissions.permission', 'OWNER')
            ->orderBy('folders.deleted_at', 'desc')
            ->findAll();
    }

    /** Quota check (§Quotas) — how many currently-non-deleted folders this user has created. */
    public function activeCountForUser(int $userId): int
    {
        return $this->where('created_by', $userId)->countAllResults();
    }
}

