<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Controllers;

use App\Services\DashboardService;
use CodeIgniter\HTTP\ResponseInterface;
use OpenApi\Attributes as OA;

/** §22.2 — GET /dashboard, scoped to the caller. */
class DashboardController extends BaseController
{
    #[OA\Get(
        path: '/dashboard',
        tags: ['Dashboard'],
        summary: 'Recently accessed documents, storage-used summary, and folders shared with the caller',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Dashboard summary', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'recentDocuments', type: 'array', items: new OA\Items(properties: [
                        new OA\Property(property: 'documentId', type: 'integer'),
                        new OA\Property(property: 'name', type: 'string'),
                        new OA\Property(property: 'folderId', type: 'integer'),
                        new OA\Property(property: 'action', type: 'string', enum: ['DOCUMENT_UPLOADED', 'DOCUMENT_DOWNLOADED', 'DOCUMENT_VERSION_UPLOADED']),
                        new OA\Property(property: 'at', type: 'string', format: 'date-time'),
                        new OA\Property(property: 'mimeType', type: 'string', nullable: true),
                        new OA\Property(property: 'hasThumbnail', type: 'boolean'),
                    ], type: 'object')),
                    new OA\Property(property: 'storageUsedBytes', type: 'integer'),
                    new OA\Property(property: 'sharedFolders', type: 'array', items: new OA\Items(properties: [
                        new OA\Property(property: 'id', type: 'integer'),
                        new OA\Property(property: 'name', type: 'string'),
                    ], type: 'object')),
                ], type: 'object'),
            ])),
        ],
    )]
    public function index(): ResponseInterface
    {
        return $this->ok((new DashboardService())->summary());
    }
}

