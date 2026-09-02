<?php

namespace App\Controllers;

use App\Services\ProcessingService;
use CodeIgniter\HTTP\ResponseInterface;
use OpenApi\Attributes as OA;

/**
 * §9.3 — internal, HMAC-signed (InternalHmacFilter), never a user JWT. The
 * Lambda worker calls this once after thumbnail/metadata/scan processing.
 */
class ProcessingCallbackController extends BaseController
{
    #[OA\Post(
        path: '/internal/processing-callback',
        tags: ['Internal'],
        summary: 'Lambda thumbnail/metadata/scan callback — internal, HMAC-signed',
        security: [['internalHmac' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['s3Key', 'status'],
            properties: [
                new OA\Property(property: 's3Key', type: 'string', description: "The version's s3_key — an S3 event carries no application id, so this is the only identifier the Lambda has"),
                new OA\Property(property: 'status', type: 'string', enum: ['COMPLETED', 'FAILED']),
                new OA\Property(property: 'scanResult', type: 'string', enum: ['CLEAN', 'FLAGGED'], nullable: true),
                new OA\Property(property: 'metadata', type: 'object', nullable: true),
                new OA\Property(property: 'thumbnailS3Key', type: 'string', nullable: true),
                new OA\Property(property: 'errorMessage', type: 'string', nullable: true),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Recorded'),
            new OA\Response(response: 401, description: '401 INVALID_SIGNATURE', content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope')),
            new OA\Response(response: 404, description: '404 DOCUMENT_VERSION_NOT_FOUND — the S3 event fired before "complete" committed the version row; the Lambda retries with backoff', content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope')),
        ],
    )]
    public function callback(): ResponseInterface
    {
        $data = $this->validated('processingCallback');

        (new ProcessingService())->handleCallback(
            $data['s3Key'],
            $data['status'],
            $data['scanResult'] ?? null,
            $data['metadata'] ?? null,
            $data['thumbnailS3Key'] ?? null,
            $data['errorMessage'] ?? null,
        );

        return $this->ok(['received' => true]);
    }
}
