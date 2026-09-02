<?php

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
}
