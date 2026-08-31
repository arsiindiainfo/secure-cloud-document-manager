<?php

namespace App\Controllers;

use App\Services\SharingService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * §18 — internal permission grants and external share links, both scoped to
 * one document. Thin per §5; every authorization decision lives in
 * SharingService (via DocumentService::authorize()).
 */
class SharingController extends BaseController
{
    public function grant(int $documentId): ResponseInterface
    {
        $data = $this->validated('permissionGrant');

        $result = (new SharingService())->grant($documentId, $data['email'], $data['permission']);

        return $this->created($result);
    }

    public function listGrants(int $documentId): ResponseInterface
    {
        $result = (new SharingService())->listGrants($documentId);

        return $this->ok($result);
    }

    public function revoke(int $documentId, int $userId): ResponseInterface
    {
        (new SharingService())->revoke($documentId, $userId);

        return $this->ok(['revoked' => true]);
    }

    public function createShareLink(int $documentId): ResponseInterface
    {
        $data = $this->validated('shareLinkCreate');

        $result = (new SharingService())->createShareLink(
            $documentId,
            $data['permission'],
            (int) $data['expiresInHours'],
            isset($data['maxDownloads']) ? (int) $data['maxDownloads'] : null,
        );

        return $this->created($result);
    }

    public function listShareLinks(int $documentId): ResponseInterface
    {
        $result = (new SharingService())->listShareLinks($documentId);

        return $this->ok($result);
    }

    public function revokeShareLink(int $id): ResponseInterface
    {
        (new SharingService())->revokeShareLink($id);

        return $this->ok(['revoked' => true]);
    }
}
