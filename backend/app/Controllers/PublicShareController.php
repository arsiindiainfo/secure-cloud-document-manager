<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Controllers;

use App\Exceptions\ShareLinkNotFoundException;
use App\Services\SharingService;
use CodeIgniter\HTTP\ResponseInterface;
use OpenApi\Attributes as OA;

/**
 * §19 — the one unauthenticated, unversioned route in the whole API. No
 * jwtAuth filter, no /api/v1 prefix (see app/Config/Routes.php).
 */
class PublicShareController extends BaseController
{
    #[OA\Get(
        path: '/s/{token}',
        tags: ['Public'],
        summary: 'Resolve an external share link — no auth, trust is the token itself',
        parameters: [new OA\PathParameter(name: 'token', schema: new OA\Schema(type: 'string'), description: '43-character URL-safe base64 token')],
        responses: [
            new OA\Response(response: 200, description: 'Resolved', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'documentName', type: 'string'),
                    new OA\Property(property: 'sizeBytes', type: 'integer'),
                    new OA\Property(property: 'permission', type: 'string', enum: ['VIEW', 'DOWNLOAD']),
                    new OA\Property(property: 'downloadUrl', type: 'string', nullable: true, description: 'Omitted if VIEW-only and the type is not inline-previewable'),
                ], type: 'object'),
            ])),
            new OA\Response(response: 404, description: '404 SHARE_LINK_NOT_FOUND', content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope')),
            new OA\Response(response: 409, description: '409 SHARE_LINK_EXPIRED, SHARE_LINK_REVOKED, or SHARE_LINK_LIMIT_REACHED', content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope')),
        ],
    )]
    public function resolve(string $token): ResponseInterface
    {
        // §19: a malformed token is treated identically to "doesn't exist" —
        // never distinguish the two for an unauthenticated caller.
        if (! preg_match('/^[A-Za-z0-9_-]{43}$/', $token)) {
            throw new ShareLinkNotFoundException();
        }

        $result = (new SharingService())->resolvePublicLink($token);

        return $this->ok($result);
    }
}

