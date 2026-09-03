<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Controllers;

use App\Services\SharingService;
use CodeIgniter\HTTP\ResponseInterface;
use OpenApi\Attributes as OA;

/**
 * §18 — internal permission grants and external share links, both scoped to
 * one document. Thin per §5; every authorization decision lives in
 * SharingService (via DocumentService::authorize()).
 */
class SharingController extends BaseController
{
    #[OA\Post(
        path: '/documents/{id}/permissions',
        tags: ['Sharing'],
        summary: 'Grant VIEWER/EDITOR/OWNER to another user by email (OWNER)',
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
            new OA\Response(response: 404, description: '404 DOCUMENT_NOT_FOUND or USER_NOT_FOUND', content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope')),
        ],
    )]
    public function grant(int $documentId): ResponseInterface
    {
        $data = $this->validated('permissionGrant');

        $result = (new SharingService())->grant($documentId, $data['email'], $data['permission']);

        return $this->created($result);
    }

    #[OA\Get(
        path: '/documents/{id}/permissions',
        tags: ['Sharing'],
        summary: 'List everyone with explicit (direct) access (OWNER)',
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
    public function listGrants(int $documentId): ResponseInterface
    {
        $result = (new SharingService())->listGrants($documentId);

        return $this->ok($result);
    }

    #[OA\Delete(
        path: '/documents/{id}/permissions/{userId}',
        tags: ['Sharing'],
        summary: 'Revoke a direct grant (OWNER); cannot revoke the last remaining OWNER',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\PathParameter(name: 'id', schema: new OA\Schema(type: 'integer')),
            new OA\PathParameter(name: 'userId', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Revoked'),
            new OA\Response(response: 404, description: '404 DOCUMENT_NOT_FOUND or PERMISSION_GRANT_NOT_FOUND', content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope')),
            new OA\Response(response: 409, description: '409 LAST_OWNER', content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope')),
        ],
    )]
    public function revoke(int $documentId, int $userId): ResponseInterface
    {
        (new SharingService())->revoke($documentId, $userId);

        return $this->ok(['revoked' => true]);
    }

    #[OA\Post(
        path: '/documents/{id}/share-links',
        tags: ['Sharing'],
        summary: 'Create an expiring external link (OWNER/EDITOR)',
        security: [['bearerAuth' => []]],
        parameters: [new OA\PathParameter(name: 'id', schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['permission', 'expiresInHours'],
            properties: [
                new OA\Property(property: 'permission', type: 'string', enum: ['VIEW', 'DOWNLOAD']),
                new OA\Property(property: 'expiresInHours', type: 'integer', minimum: 1, maximum: 168),
                new OA\Property(property: 'maxDownloads', type: 'integer', nullable: true, minimum: 1, maximum: 1000),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'id', type: 'integer'),
                    new OA\Property(property: 'url', type: 'string', example: 'https://.../s/<token>'),
                    new OA\Property(property: 'expiresAt', type: 'string', format: 'date-time'),
                ], type: 'object'),
            ])),
            new OA\Response(response: 400, ref: '#/components/responses/ValidationError'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
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

    #[OA\Get(
        path: '/documents/{id}/share-links',
        tags: ['Sharing'],
        summary: 'List active/expired/revoked links with download counts (OWNER/EDITOR)',
        security: [['bearerAuth' => []]],
        parameters: [new OA\PathParameter(name: 'id', schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Links', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/ShareLink')),
            ])),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function listShareLinks(int $documentId): ResponseInterface
    {
        $result = (new SharingService())->listShareLinks($documentId);

        return $this->ok($result);
    }

    #[OA\Delete(
        path: '/share-links/{id}',
        tags: ['Sharing'],
        summary: 'Revoke a link immediately (OWNER/EDITOR)',
        security: [['bearerAuth' => []]],
        parameters: [new OA\PathParameter(name: 'id', schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Revoked'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function revokeShareLink(int $id): ResponseInterface
    {
        (new SharingService())->revokeShareLink($id);

        return $this->ok(['revoked' => true]);
    }
}

