<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Controllers;

use App\Services\TrashService;
use CodeIgniter\HTTP\ResponseInterface;
use OpenApi\Attributes as OA;

/** §22.8 — GET /trash, scoped to the caller (ADMIN sees everything). */
class TrashController extends BaseController
{
    #[OA\Get(
        path: '/trash',
        tags: ['Trash'],
        summary: 'Soft-deleted folders/documents the caller owns, with days remaining before purge',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Trash contents', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'folders', type: 'array', items: new OA\Items(properties: [
                        new OA\Property(property: 'daysRemaining', type: 'integer'),
                    ], allOf: [new OA\Schema(ref: '#/components/schemas/Folder')])),
                    new OA\Property(property: 'documents', type: 'array', items: new OA\Items(properties: [
                        new OA\Property(property: 'daysRemaining', type: 'integer'),
                    ], allOf: [new OA\Schema(ref: '#/components/schemas/Document')])),
                ], type: 'object'),
            ])),
        ],
    )]
    public function index(): ResponseInterface
    {
        return $this->ok((new TrashService())->list());
    }

    #[OA\Delete(
        path: '/trash/documents/{id}',
        tags: ['Trash'],
        summary: 'Permanently delete a trashed document — S3 objects and DB rows, irreversible (OWNER)',
        security: [['bearerAuth' => []]],
        parameters: [new OA\PathParameter(name: 'id', schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Permanently deleted'),
            new OA\Response(response: 404, description: '404 DOCUMENT_NOT_FOUND', content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope')),
            new OA\Response(response: 409, description: '409 NOT_IN_TRASH', content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope')),
        ],
    )]
    public function purgeDocument(int $documentId): ResponseInterface
    {
        (new TrashService())->purgeDocument($documentId);

        return $this->ok(['message' => 'Document permanently deleted.']);
    }

    #[OA\Delete(
        path: '/trash/folders/{id}',
        tags: ['Trash'],
        summary: 'Permanently delete a trashed folder and everything under it, irreversible (OWNER)',
        security: [['bearerAuth' => []]],
        parameters: [new OA\PathParameter(name: 'id', schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Permanently deleted'),
            new OA\Response(response: 404, description: '404 FOLDER_NOT_FOUND', content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope')),
            new OA\Response(response: 409, description: '409 NOT_IN_TRASH', content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope')),
        ],
    )]
    public function purgeFolder(int $folderId): ResponseInterface
    {
        (new TrashService())->purgeFolder($folderId);

        return $this->ok(['message' => 'Folder permanently deleted.']);
    }
}

