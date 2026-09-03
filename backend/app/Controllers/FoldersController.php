<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Controllers;

use App\Services\FolderService;
use CodeIgniter\HTTP\ResponseInterface;
use OpenApi\Attributes as OA;

/**
 * §16 — folder CRUD. Thin per §5: validate, call one Service method, map
 * the result to the envelope.
 */
class FoldersController extends BaseController
{
    #[OA\Post(
        path: '/folders',
        tags: ['Folders'],
        summary: 'Create a folder (EDITOR+ on parent, or ADMIN for a root folder)',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['name'],
            properties: [
                new OA\Property(property: 'name', type: 'string', maxLength: 180),
                new OA\Property(property: 'parentFolderId', type: 'integer', nullable: true),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', ref: '#/components/schemas/Folder'),
            ])),
            new OA\Response(response: 400, ref: '#/components/responses/ValidationError'),
            new OA\Response(response: 403, ref: '#/components/responses/ForbiddenRole'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound', description: '404 FOLDER_NOT_FOUND — bad parent'),
            new OA\Response(response: 409, ref: '#/components/responses/Conflict'),
        ],
    )]
    public function create(): ResponseInterface
    {
        $data = $this->validated('folderCreate');

        $folder = (new FolderService())->create($data['name'], isset($data['parentFolderId']) ? (int) $data['parentFolderId'] : null);

        return $this->created($folder);
    }

    #[OA\Get(
        path: '/folders/{id}/children',
        tags: ['Folders'],
        summary: "Browse a folder's contents (VIEWER+); \"root\" lists the caller's accessible top-level folders",
        security: [['bearerAuth' => []]],
        parameters: [new OA\PathParameter(name: 'id', schema: new OA\Schema(type: 'string'), description: 'A numeric folder id, or the literal "root"')],
        responses: [
            new OA\Response(response: 200, description: 'Folders, documents, and the breadcrumb chain', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'folders', type: 'array', items: new OA\Items(ref: '#/components/schemas/Folder')),
                    new OA\Property(property: 'documents', type: 'array', items: new OA\Items(ref: '#/components/schemas/Document')),
                    new OA\Property(property: 'breadcrumb', type: 'array', items: new OA\Items(properties: [
                        new OA\Property(property: 'id', type: 'integer'),
                        new OA\Property(property: 'name', type: 'string'),
                    ], type: 'object')),
                ], type: 'object'),
            ])),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function children(string $idParam = 'root'): ResponseInterface
    {
        $folderId = $idParam === 'root' ? null : (int) $idParam;

        $result = (new FolderService())->listChildren($folderId);

        return $this->ok([
            'folders'    => $result['folders'],
            'documents'  => $result['documents'],
            'breadcrumb' => $result['breadcrumb'],
        ]);
    }

    #[OA\Put(
        path: '/folders/{id}',
        tags: ['Folders'],
        summary: 'Rename and/or move a folder (EDITOR+ on the folder and destination parent)',
        security: [['bearerAuth' => []]],
        parameters: [new OA\PathParameter(name: 'id', schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['name'],
            properties: [
                new OA\Property(property: 'name', type: 'string', maxLength: 180),
                new OA\Property(property: 'parentFolderId', type: 'integer', nullable: true, description: 'Omit to rename only; null moves to root (ADMIN only)'),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', ref: '#/components/schemas/Folder'),
            ])),
            new OA\Response(response: 400, ref: '#/components/responses/ValidationError'),
            new OA\Response(response: 403, ref: '#/components/responses/ForbiddenRole'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 409, ref: '#/components/responses/Conflict'),
            new OA\Response(response: 422, description: '422 CYCLE_DETECTED — cannot move a folder into its own descendant', content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope')),
        ],
    )]
    public function update(int $id): ResponseInterface
    {
        $data = $this->validated('folderUpdate');

        $moveRequested  = array_key_exists('parentFolderId', $data);
        $parentFolderId = $moveRequested && $data['parentFolderId'] !== null ? (int) $data['parentFolderId'] : null;

        $folder = (new FolderService())->update($id, $data['name'], $parentFolderId, $moveRequested);

        return $this->ok($folder);
    }

    #[OA\Delete(
        path: '/folders/{id}',
        tags: ['Folders'],
        summary: 'Soft-delete a folder, cascading to every descendant (OWNER)',
        security: [['bearerAuth' => []]],
        parameters: [new OA\PathParameter(name: 'id', schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Deleted'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function delete(int $id): ResponseInterface
    {
        (new FolderService())->softDelete($id);

        return $this->ok(['deleted' => true]);
    }

    #[OA\Post(
        path: '/folders/{id}/restore',
        tags: ['Folders'],
        summary: 'Restore a folder from trash (OWNER)',
        security: [['bearerAuth' => []]],
        parameters: [new OA\PathParameter(name: 'id', schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Restored'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, description: '422 PARENT_FOLDER_NOT_FOUND — restore the parent first', content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope')),
        ],
    )]
    public function restore(int $id): ResponseInterface
    {
        (new FolderService())->restore($id);

        return $this->ok(['restored' => true]);
    }
}

