<?php

namespace App\Services;

use App\Entities\Folder;
use App\Exceptions\FolderNotFoundException;
use App\Exceptions\ForbiddenActionException;
use App\Libraries\PermissionResolver;
use App\Models\AuditLogModel;
use App\Models\DocumentModel;
use App\Models\FolderModel;
use Config\Services;

/**
 * §16 — folder CRUD. authorize() is the one code path that can grant
 * access to a folder (§6.3) — every Controller action that touches a
 * folder goes through it first.
 */
class FolderService
{
    private readonly PermissionResolver $permissions;

    public function __construct(
        private readonly FolderModel $folders = new FolderModel(),
        private readonly DocumentModel $documents = new DocumentModel(),
        private readonly AuditLogModel $auditLog = new AuditLogModel(),
        ?PermissionResolver $permissions = null,
    ) {
        $this->permissions = $permissions ?? new PermissionResolver(db_connect());
    }

    /**
     * $includeDeleted is for restore() only — every other caller must go
     * through the normal soft-delete-filtered lookup, or a soft-deleted
     * folder would stay reachable (and actionable) via any other endpoint.
     */
    public function authorize(int $folderId, string $required, bool $includeDeleted = false): Folder
    {
        $folder = $includeDeleted ? $this->folders->withDeleted()->find($folderId) : $this->folders->find($folderId);
        if ($folder === null) {
            throw new FolderNotFoundException();
        }

        $auth = Services::authContext();

        if ($auth->isAdmin()) {
            // Every ADMIN ACL bypass is itself audited (§6.3) so it's never invisible.
            $this->auditLog->record($auth->userId(), 'ADMIN_ACCESS_BYPASS', 'FOLDER', $folderId, ['requiredPermission' => $required]);

            return $folder;
        }

        $permission = $this->permissions->folderPermission($auth->userId(), $folderId);
        if (! $this->permissions->meets($permission, $required)) {
            // Never distinguish "doesn't exist" from "no grant" (§6.3 guardrail).
            throw new FolderNotFoundException();
        }

        return $folder;
    }

    public function create(string $name, ?int $parentFolderId): Folder
    {
        $auth = Services::authContext();

        if ($parentFolderId === null) {
            if (! $auth->isAdmin()) {
                throw new ForbiddenActionException('Only administrators can create root folders.');
            }
        } else {
            $this->authorize($parentFolderId, 'EDITOR');
        }

        $id = $this->folders->create($name, $parentFolderId, $auth->userId());

        /** @var Folder $folder */
        $folder = $this->folders->find($id);

        return $folder;
    }

    /**
     * $moveRequested distinguishes "the client didn't send parentFolderId at
     * all" (rename only, $newParentFolderId is meaningless) from "the client
     * explicitly sent parentFolderId: null" (move to root) — both look like
     * `null` to a plain nullable parameter, but they mean very different
     * things for a value that legitimately allows null (§16).
     */
    public function update(int $folderId, string $newName, ?int $newParentFolderId, bool $moveRequested): Folder
    {
        $auth   = Services::authContext();
        $folder = $this->authorize($folderId, 'EDITOR');

        $effectiveParentId = $moveRequested ? $newParentFolderId : $folder->parent_folder_id;

        if ($effectiveParentId !== $folder->parent_folder_id) {
            if ($effectiveParentId === null) {
                if (! $auth->isAdmin()) {
                    throw new ForbiddenActionException('Only administrators can move a folder to the root.');
                }
            } else {
                $this->authorize($effectiveParentId, 'EDITOR');
            }
        }

        $this->folders->renameMove($folderId, $newName, $effectiveParentId, $auth->userId());

        /** @var Folder $updated */
        $updated = $this->folders->find($folderId);

        return $updated;
    }

    public function softDelete(int $folderId): int
    {
        $this->authorize($folderId, 'OWNER');

        return $this->folders->softDelete($folderId, Services::authContext()->userId());
    }

    public function restore(int $folderId): void
    {
        $this->authorize($folderId, 'OWNER', includeDeleted: true);
        $this->folders->restore($folderId, Services::authContext()->userId());
    }

    /**
     * @return array{folders: list<Folder>, documents: list<\App\Entities\Document>, breadcrumb: list<array{id: int, name: string}>}
     */
    public function listChildren(?int $folderId): array
    {
        $auth = Services::authContext();

        if ($folderId === null) {
            $folders = $auth->isAdmin()
                ? $this->folders->childFolders(null)
                : $this->folders->accessibleRootFolders($auth->userId());

            return ['folders' => $folders, 'documents' => [], 'breadcrumb' => []];
        }

        $this->authorize($folderId, 'VIEWER');

        return [
            'folders'    => $this->folders->childFolders($folderId),
            'documents'  => $this->documents->childDocuments($folderId),
            'breadcrumb' => $this->folders->ancestorChain($folderId),
        ];
    }
}
