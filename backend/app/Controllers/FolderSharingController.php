<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Controllers;

use App\Services\FolderSharingService;
use CodeIgniter\HTTP\ResponseInterface;
use OpenApi\Attributes as OA;

/**
 * §18 (folder variant) — internal permission grants scoped to one folder.
 * Thin per §5; every authorization decision lives in FolderSharingService
 * (via FolderService::authorize()).
 */
class FolderSharingController extends BaseController
{
    #[OA\Post(
        path: '/folders/{id}/permissions',
        tags: ['Sharing'],
        summary: 'Grant VIEWER/EDITOR/OWNER on a folder to another user by email (OWNER)',
        security: [['bearerAuth' => []]],
        parameters: [new OA\PathParameter(name: 'id', schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['email', 'permission'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email'),
                new OA\Property(property: 'permission', type: 'string', enum: ['VIEWER', 'EDITOR', 'OWNER']),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'Granted', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', ref: '#/components/schemas/PermissionGrant'),
            ])),
            new OA\Response(response: 404, description: '404 FOLDER_NOT_FOUND or USER_NOT_FOUND', content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope')),
        ],
    )]
    public function grant(int $folderId): ResponseInterface
    {
        $data = $this->validated('permissionGrant');

        $result = (new FolderSharingService())->grant($folderId, $data['email'], $data['permission']);

        return $this->created($result);
    }

    #[OA\Get(
        path: '/folders/{id}/permissions',
        tags: ['Sharing'],
        summary: 'List everyone with explicit (direct) access to a folder (OWNER)',
        security: [['bearerAuth' => []]],
        parameters: [new OA\PathParameter(name: 'id', schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Grants', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/PermissionGrant')),
            ])),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function listGrants(int $folderId): ResponseInterface
    {
        $result = (new FolderSharingService())->listGrants($folderId);

        return $this->ok($result);
    }

    #[OA\Delete(
        path: '/folders/{id}/permissions/{userId}',
        tags: ['Sharing'],
        summary: 'Revoke a direct folder grant (OWNER); cannot revoke the last remaining OWNER',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\PathParameter(name: 'id', schema: new OA\Schema(type: 'integer')),
            new OA\PathParameter(name: 'userId', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Revoked'),
            new OA\Response(response: 404, description: '404 FOLDER_NOT_FOUND or PERMISSION_GRANT_NOT_FOUND', content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope')),
            new OA\Response(response: 409, description: '409 LAST_OWNER', content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope')),
        ],
    )]
    public function revoke(int $folderId, int $userId): ResponseInterface
    {
        (new FolderSharingService())->revoke($folderId, $userId);

        return $this->ok(['revoked' => true]);
    }
}
