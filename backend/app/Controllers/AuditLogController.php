<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Controllers;

use App\DTOs\PaginationRequestDTO;
use App\Models\AuditLogModel;
use CodeIgniter\HTTP\ResponseInterface;
use OpenApi\Attributes as OA;

/**
 * §19 — GET /audit-logs. ADMIN only (enforced by the route's role:ADMIN
 * filter, see app/Config/Routes.php) — no per-resource ACL applies here.
 */
class AuditLogController extends BaseController
{
    #[OA\Get(
        path: '/audit-logs',
        tags: ['Audit'],
        summary: 'Paginated audit trail (ADMIN)',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'entityType', in: 'query', schema: new OA\Schema(type: 'string', enum: ['DOCUMENT', 'FOLDER', 'USER', 'SHARE_LINK'])),
            new OA\Parameter(name: 'entityId', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'userId', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'action', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'dateFrom', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'dateTo', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 20)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Audit log entries', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(properties: [
                    new OA\Property(property: 'id', type: 'integer'),
                    new OA\Property(property: 'userId', type: 'integer', nullable: true),
                    new OA\Property(property: 'action', type: 'string'),
                    new OA\Property(property: 'entityType', type: 'string', enum: ['DOCUMENT', 'FOLDER', 'USER', 'SHARE_LINK']),
                    new OA\Property(property: 'entityId', type: 'integer'),
                    new OA\Property(property: 'details', type: 'object', nullable: true),
                    new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
                ], type: 'object')),
                new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
            ])),
            new OA\Response(response: 403, ref: '#/components/responses/ForbiddenRole'),
        ],
    )]
    public function index(): ResponseInterface
    {
        $pagination = PaginationRequestDTO::fromRequest($this->request, ['createdAt'], 'createdAt');

        $entityId = $this->request->getGet('entityId');
        $userId   = $this->request->getGet('userId');

        $result = (new AuditLogModel())->paginatedList(
            $this->request->getGet('entityType') ?: null,
            $entityId !== null ? (int) $entityId : null,
            $userId !== null ? (int) $userId : null,
            $this->request->getGet('action') ?: null,
            $this->request->getGet('dateFrom') ?: null,
            $this->request->getGet('dateTo') ?: null,
            $pagination->page,
            $pagination->limit,
        );

        return $this->paginated($result['items'], $pagination->meta($result['total']));
    }
}

