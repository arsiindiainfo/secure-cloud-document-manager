<?php

namespace App\Controllers;

use App\DTOs\PaginationRequestDTO;
use App\Services\DocumentService;
use CodeIgniter\HTTP\ResponseInterface;
use OpenApi\Attributes as OA;

/**
 * §17 — documents, the three-step upload flow, and versions. Thin per §5.
 */
class DocumentsController extends BaseController
{
    #[OA\Get(
        path: '/documents',
        tags: ['Documents'],
        summary: 'Paginated/search list (VIEWER+ on each result — §6.3 visibility)',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string'), description: 'FULLTEXT match against name/description/tags'),
            new OA\Parameter(name: 'folderId', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'mimeType', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'sort', in: 'query', schema: new OA\Schema(type: 'string', enum: ['name', 'size', 'updatedAt'])),
            new OA\Parameter(name: 'direction', in: 'query', schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'])),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 20)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Search results', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(properties: [
                    new OA\Property(property: 'folderName', type: 'string'),
                    new OA\Property(property: 'hasThumbnail', type: 'boolean'),
                ], allOf: [new OA\Schema(ref: '#/components/schemas/Document')])),
                new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
            ])),
        ],
    )]
    public function index(): ResponseInterface
    {
        $pagination = PaginationRequestDTO::fromRequest($this->request, ['name', 'size', 'updatedAt'], 'updatedAt');
        $folderId   = $this->request->getGet('folderId');
        $mimeType   = $this->request->getGet('mimeType');

        $result = (new DocumentService())->search($pagination, $folderId !== null ? (int) $folderId : null, $mimeType ?: null);

        return $this->paginated($result['items'], $result['meta']);
    }

    #[OA\Post(
        path: '/documents/uploads/initiate',
        tags: ['Documents'],
        summary: 'Step 1 of the direct-to-S3 upload flow: get a presigned PUT URL (EDITOR+ on folder)',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['folderId', 'fileName', 'mimeType', 'sizeBytes'],
            properties: [
                new OA\Property(property: 'folderId', type: 'integer'),
                new OA\Property(property: 'fileName', type: 'string'),
                new OA\Property(property: 'mimeType', type: 'string'),
                new OA\Property(property: 'sizeBytes', type: 'integer'),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Presigned PUT URL', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'uploadUrl', type: 'string'),
                    new OA\Property(property: 's3Key', type: 'string'),
                    new OA\Property(property: 'expiresIn', type: 'integer', example: 300),
                ], type: 'object'),
            ])),
            new OA\Response(response: 400, ref: '#/components/responses/ValidationError', description: '400 VALIDATION_ERROR, UNSUPPORTED_FILE_TYPE, or FILE_TOO_LARGE'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function initiateUpload(): ResponseInterface
    {
        $data = $this->validated('uploadInitiate');

        $result = (new DocumentService())->initiateUpload(
            (int) $data['folderId'],
            $data['fileName'],
            $data['mimeType'],
            (int) $data['sizeBytes'],
        );

        return $this->ok($result);
    }

    #[OA\Post(
        path: '/documents/uploads/complete',
        tags: ['Documents'],
        summary: 'Step 3: confirm the S3 object and commit the document + first version (EDITOR+ on folder)',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['folderId', 's3Key', 'name', 'checksumSha256'],
            properties: [
                new OA\Property(property: 'folderId', type: 'integer'),
                new OA\Property(property: 's3Key', type: 'string'),
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'description', type: 'string', nullable: true),
                new OA\Property(property: 'tags', type: 'string', nullable: true, description: 'Comma-separated'),
                new OA\Property(property: 'checksumSha256', type: 'string'),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'Document created', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', ref: '#/components/schemas/Document'),
            ])),
            new OA\Response(response: 400, ref: '#/components/responses/ValidationError', description: '400 VALIDATION_ERROR — includes "S3 object not found" if the PUT never happened'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 409, ref: '#/components/responses/Conflict'),
        ],
    )]
    public function completeUpload(): ResponseInterface
    {
        $data = $this->validated('uploadComplete');

        $document = (new DocumentService())->completeUpload(
            (int) $data['folderId'],
            $data['s3Key'],
            $data['name'],
            $data['description'] ?? null,
            $data['tags'] ?? null,
            $data['checksumSha256'],
        );

        return $this->created($document);
    }

    #[OA\Get(
        path: '/documents/{id}',
        tags: ['Documents'],
        summary: 'Full detail including current version, processing status, and effective permission (VIEWER+)',
        security: [['bearerAuth' => []]],
        parameters: [new OA\PathParameter(name: 'id', schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Document detail', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', ref: '#/components/schemas/DocumentDetail'),
            ])),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function show(int $id): ResponseInterface
    {
        $detail = (new DocumentService())->getDetail($id);

        return $this->ok($detail);
    }

    #[OA\Put(
        path: '/documents/{id}',
        tags: ['Documents'],
        summary: 'Metadata-only update: name, description, tags, folder move (EDITOR+)',
        security: [['bearerAuth' => []]],
        parameters: [new OA\PathParameter(name: 'id', schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'description', type: 'string', nullable: true),
            new OA\Property(property: 'tags', type: 'string', nullable: true),
            new OA\Property(property: 'folderId', type: 'integer'),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', ref: '#/components/schemas/Document'),
            ])),
            new OA\Response(response: 400, ref: '#/components/responses/ValidationError'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 409, ref: '#/components/responses/Conflict'),
        ],
    )]
    public function update(int $id): ResponseInterface
    {
        $data = $this->validated('documentUpdate');

        $document = (new DocumentService())->update(
            $id,
            $data['name'] ?? null,
            $data['description'] ?? null,
            $data['tags'] ?? null,
            isset($data['folderId']) ? (int) $data['folderId'] : null,
        );

        return $this->ok($document);
    }

    #[OA\Delete(
        path: '/documents/{id}',
        tags: ['Documents'],
        summary: 'Soft delete (OWNER)',
        security: [['bearerAuth' => []]],
        parameters: [new OA\PathParameter(name: 'id', schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Deleted'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function delete(int $id): ResponseInterface
    {
        (new DocumentService())->softDelete($id);

        return $this->ok(['deleted' => true]);
    }

    #[OA\Post(
        path: '/documents/{id}/restore',
        tags: ['Documents'],
        summary: 'Restore from trash (OWNER)',
        security: [['bearerAuth' => []]],
        parameters: [new OA\PathParameter(name: 'id', schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Restored'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function restore(int $id): ResponseInterface
    {
        (new DocumentService())->restore($id);

        return $this->ok(['restored' => true]);
    }

    #[OA\Post(
        path: '/documents/{id}/versions/initiate',
        tags: ['Documents'],
        summary: 'Get a presigned PUT URL for a new version (EDITOR+)',
        security: [['bearerAuth' => []]],
        parameters: [new OA\PathParameter(name: 'id', schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['fileName', 'mimeType', 'sizeBytes'],
            properties: [
                new OA\Property(property: 'fileName', type: 'string'),
                new OA\Property(property: 'mimeType', type: 'string'),
                new OA\Property(property: 'sizeBytes', type: 'integer'),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Presigned PUT URL', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'uploadUrl', type: 'string'),
                    new OA\Property(property: 's3Key', type: 'string'),
                    new OA\Property(property: 'expiresIn', type: 'integer'),
                ], type: 'object'),
            ])),
            new OA\Response(response: 400, ref: '#/components/responses/ValidationError'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 409, ref: '#/components/responses/Conflict', description: '409 DOCUMENT_DELETED'),
        ],
    )]
    public function initiateVersion(int $id): ResponseInterface
    {
        $data = $this->validated('versionInitiate');

        $result = (new DocumentService())->initiateVersion($id, $data['fileName'], $data['mimeType'], (int) $data['sizeBytes']);

        return $this->ok($result);
    }

    #[OA\Post(
        path: '/documents/{id}/versions/complete',
        tags: ['Documents'],
        summary: 'Commit a new version (EDITOR+)',
        security: [['bearerAuth' => []]],
        parameters: [new OA\PathParameter(name: 'id', schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['s3Key', 'checksumSha256'],
            properties: [
                new OA\Property(property: 's3Key', type: 'string'),
                new OA\Property(property: 'checksumSha256', type: 'string'),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'Version committed', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', properties: [new OA\Property(property: 'versionNo', type: 'integer')], type: 'object'),
            ])),
            new OA\Response(response: 400, ref: '#/components/responses/ValidationError'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 409, ref: '#/components/responses/Conflict', description: '409 DOCUMENT_DELETED'),
        ],
    )]
    public function completeVersion(int $id): ResponseInterface
    {
        $data = $this->validated('versionComplete');

        $result = (new DocumentService())->completeVersion($id, $data['s3Key'], $data['checksumSha256']);

        return $this->created($result);
    }

    #[OA\Get(
        path: '/documents/{id}/versions',
        tags: ['Documents'],
        summary: 'Version history, newest first (VIEWER+)',
        security: [['bearerAuth' => []]],
        parameters: [new OA\PathParameter(name: 'id', schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Versions', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/DocumentVersion')),
            ])),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function versions(int $id): ResponseInterface
    {
        $versions = (new DocumentService())->listVersions($id);

        return $this->ok($versions);
    }

    #[OA\Get(
        path: '/documents/{id}/download',
        tags: ['Documents'],
        summary: 'Presigned download URL, response-content-disposition: attachment (VIEWER+)',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\PathParameter(name: 'id', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'versionId', in: 'query', schema: new OA\Schema(type: 'integer'), description: 'Defaults to the current version'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Presigned GET URL', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'url', type: 'string'),
                    new OA\Property(property: 'expiresIn', type: 'integer', example: 300),
                ], type: 'object'),
            ])),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function download(int $id): ResponseInterface
    {
        $versionId = $this->request->getGet('versionId');

        $result = (new DocumentService())->getDownloadUrl($id, $versionId !== null ? (int) $versionId : null);

        return $this->ok($result);
    }

    #[OA\Get(
        path: '/documents/{id}/preview',
        tags: ['Documents'],
        summary: 'Presigned inline-preview URL for PDF/PNG/JPG; other types fall back to the thumbnail (VIEWER+)',
        security: [['bearerAuth' => []]],
        parameters: [new OA\PathParameter(name: 'id', schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Presigned GET URL', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'url', type: 'string'),
                    new OA\Property(property: 'expiresIn', type: 'integer'),
                ], type: 'object'),
            ])),
            new OA\Response(response: 404, description: '404 DOCUMENT_NOT_FOUND, or THUMBNAIL_NOT_READY while processing is pending', content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope')),
        ],
    )]
    public function preview(int $id): ResponseInterface
    {
        $result = (new DocumentService())->getPreviewUrl($id);

        return $this->ok($result);
    }

    #[OA\Get(
        path: '/documents/{id}/thumbnail',
        tags: ['Documents'],
        summary: 'Presigned thumbnail URL (VIEWER+)',
        security: [['bearerAuth' => []]],
        parameters: [new OA\PathParameter(name: 'id', schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Presigned GET URL', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'url', type: 'string'),
                    new OA\Property(property: 'expiresIn', type: 'integer'),
                ], type: 'object'),
            ])),
            new OA\Response(response: 404, description: '404 THUMBNAIL_NOT_READY while processing is pending', content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope')),
        ],
    )]
    public function thumbnail(int $id): ResponseInterface
    {
        $result = (new DocumentService())->getThumbnailUrl($id);

        return $this->ok($result);
    }
}
