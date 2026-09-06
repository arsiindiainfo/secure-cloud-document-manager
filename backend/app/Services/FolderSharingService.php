<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Services;

use App\Exceptions\UserNotFoundException;
use App\Models\DocumentPermissionModel;
use App\Models\UserModel;
use Config\Services;

/**
 * §18 (folder variant) — internal permission grants scoped to a folder.
 * Same shape as SharingService's document grants; folders don't get
 * external share links (those stay document-only), so this only covers
 * grant/list/revoke.
 */
class FolderSharingService
{
    public function __construct(
        private readonly DocumentPermissionModel $permissions = new DocumentPermissionModel(),
        private readonly UserModel $users = new UserModel(),
        private readonly FolderService $folderService = new FolderService(),
    ) {
    }

    /** @return array{userId: int, name: string, email: string, permission: string, grantedAt: string} */
    public function grant(int $folderId, string $granteeEmail, string $permission): array
    {
        $this->folderService->authorize($folderId, 'OWNER');

        $grantee = $this->users->where('email', $granteeEmail)->first();
        if ($grantee === null) {
            throw new UserNotFoundException();
        }

        $this->permissions->grantFolder($folderId, $grantee->id, $permission, Services::authContext()->userId());

        return [
            'userId'     => $grantee->id,
            'name'       => $grantee->name,
            'email'      => $grantee->email,
            'permission' => $permission,
            'grantedAt'  => date('Y-m-d H:i:s'),
        ];
    }

    /** @return list<array{userId: int, name: string, email: string, permission: string, grantedAt: string}> */
    public function listGrants(int $folderId): array
    {
        $this->folderService->authorize($folderId, 'OWNER');

        return $this->permissions->listForFolder($folderId);
    }

    public function revoke(int $folderId, int $granteeUserId): void
    {
        $this->folderService->authorize($folderId, 'OWNER');
        $this->permissions->revokeFolder($folderId, $granteeUserId, Services::authContext()->userId());
    }
}
