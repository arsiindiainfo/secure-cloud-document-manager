<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Services;

use App\Constants\FileTypes;
use App\Entities\ShareLink;
use App\Exceptions\ShareLinkExpiredException;
use App\Exceptions\ShareLinkLimitReachedException;
use App\Exceptions\ShareLinkNotFoundException;
use App\Exceptions\ShareLinkRevokedException;
use App\Exceptions\UserNotFoundException;
use App\Libraries\S3Service;
use App\Libraries\StoredProcedure;
use App\Models\AuditLogModel;
use App\Models\DocumentModel;
use App\Models\DocumentPermissionModel;
use App\Models\DocumentVersionModel;
use App\Models\ShareLinkModel;
use App\Models\UserModel;
use Config\Services;

/**
 * §18/§19 — internal permission grants and external share links. Every
 * document-scoped operation re-authorizes through DocumentService::authorize()
 * so the §6.3 guardrail (non-participant -> 404, never 403) also covers
 * sharing endpoints, not just CRUD.
 */
class SharingService
{
    public function __construct(
        private readonly DocumentModel $documents = new DocumentModel(),
        private readonly DocumentVersionModel $versions = new DocumentVersionModel(),
        private readonly DocumentPermissionModel $permissions = new DocumentPermissionModel(),
        private readonly ShareLinkModel $shareLinks = new ShareLinkModel(),
        private readonly UserModel $users = new UserModel(),
        private readonly DocumentService $documentService = new DocumentService(),
        private readonly AuditLogModel $auditLog = new AuditLogModel(),
        private readonly S3Service $s3 = new S3Service(),
    ) {
    }

    /** @return array{userId: int, name: string, email: string, permission: string, grantedAt: string} */
    public function grant(int $documentId, string $granteeEmail, string $permission): array
    {
        $this->documentService->authorize($documentId, 'OWNER');

        $grantee = $this->users->where('email', $granteeEmail)->first();
        if ($grantee === null) {
            throw new UserNotFoundException();
        }

        $this->permissions->grant($documentId, $grantee->id, $permission, Services::authContext()->userId());

        return [
            'userId'     => $grantee->id,
            'name'       => $grantee->name,
            'email'      => $grantee->email,
            'permission' => $permission,
            'grantedAt'  => date('Y-m-d H:i:s'),
        ];
    }

    /** @return list<array{userId: int, name: string, email: string, permission: string, grantedAt: string}> */
    public function listGrants(int $documentId): array
    {
        $this->documentService->authorize($documentId, 'OWNER');

        return $this->permissions->listForDocument($documentId);
    }

    public function revoke(int $documentId, int $granteeUserId): void
    {
        $this->documentService->authorize($documentId, 'OWNER');
        $this->permissions->revoke($documentId, $granteeUserId, Services::authContext()->userId());
    }

    /** @return array{id: int, url: string, expiresAt: string} */
    public function createShareLink(int $documentId, string $permission, int $expiresInHours, ?int $maxDownloads): array
    {
        // OWNER/EDITOR per §18 — 'EDITOR' as the required floor lets an
        // OWNER through too (PermissionResolver::meets() is a >= check).
        $this->documentService->authorize($documentId, 'EDITOR');

        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiresInHours} hours"));
        $result    = $this->shareLinks->create($documentId, $permission, $expiresAt, $maxDownloads, Services::authContext()->userId());

        $frontendUrl = rtrim(config('App')->frontendUrl, '/');

        return [
            'id'        => $result['id'],
            'url'       => "{$frontendUrl}/s/{$result['token']}",
            'expiresAt' => $expiresAt,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function listShareLinks(int $documentId): array
    {
        $this->documentService->authorize($documentId, 'EDITOR');

        return array_map(static fn (ShareLink $link): array => $link->toArray(), $this->shareLinks->listForDocument($documentId));
    }

    public function revokeShareLink(int $shareLinkId): void
    {
        /** @var ShareLink|null $link */
        $link = $this->shareLinks->find($shareLinkId);
        if ($link === null) {
            throw new ShareLinkNotFoundException();
        }

        $this->documentService->authorize($link->document_id, 'EDITOR');
        $this->shareLinks->revoke($shareLinkId);

        $auth = Services::authContext();
        $this->auditLog->record($auth->userId(), 'SHARE_LINK_REVOKED', 'SHARE_LINK', $shareLinkId, ['documentId' => $link->document_id]);
    }

    /**
     * §19 — the public, unauthenticated resolve. Trust is the 43-character
     * token itself; there is no caller identity to run through authorize().
     *
     * @return array{documentName: string, sizeBytes: int, permission: string, downloadUrl: string|null}
     */
    public function resolvePublicLink(string $token): array
    {
        $out = (new StoredProcedure(db_connect()))->call('sp_share_link_consume', [$token], [
            'p_document_id', 'p_permission', 'p_status_code', 'p_message',
        ]);

        match ($out['p_status_code']) {
            'OK'             => null,
            'NOT_FOUND'      => throw new ShareLinkNotFoundException(),
            'REVOKED'        => throw new ShareLinkRevokedException(),
            'EXPIRED'        => throw new ShareLinkExpiredException(),
            'LIMIT_REACHED'  => throw new ShareLinkLimitReachedException(),
            default          => throw new ShareLinkNotFoundException(),
        };

        $documentId = (int) $out['p_document_id'];
        $permission = (string) $out['p_permission'];

        $document = $this->documents->find($documentId);
        $version  = $this->versions->current($documentId);
        if ($document === null || $version === null) {
            // The document/version was soft-deleted after this link was
            // issued — treat it the same as an invalid link (§6.3 spirit:
            // never distinguish "revoked" from "the thing behind it is gone").
            throw new ShareLinkNotFoundException();
        }

        $inlineable = in_array($version->mime_type, FileTypes::INLINE_PREVIEWABLE_MIME_TYPES, true);

        $downloadUrl = null;
        if ($permission === 'DOWNLOAD') {
            $disposition = 'attachment; filename="' . str_replace('"', '', $document->name) . '"';
            $downloadUrl = $this->s3->presignGet($version->s3_key, $disposition);
        } elseif ($inlineable) {
            $downloadUrl = $this->s3->presignGet($version->s3_key, 'inline');
        }

        return [
            'documentName' => $document->name,
            'sizeBytes'    => $version->size_bytes,
            'permission'   => $permission,
            'downloadUrl'  => $downloadUrl,
        ];
    }
}

