<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace Tests\Api;

use GuzzleHttp\Client;
use Tests\Support\ApiTestCase;

/**
 * @internal
 *
 * §9.3 — the Lambda worker's callback. HMAC-signed, never a user JWT.
 * Identified by s3Key, not a DB id — see ProcessingService for why.
 */
final class ProcessingCallbackControllerTest extends ApiTestCase
{
    /** @return array{documentId: int, versionId: int, s3Key: string} */
    private function uploadDocument(string $name = 'proc.pdf', string $content = 'proc content'): array
    {
        $folder = json_decode($this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post('api/v1/folders', ['name' => uniqid('folder-', true), 'parentFolderId' => null])->getJSON(), true);
        $folderId = $folder['data']['id'];

        $initiated = json_decode($this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post('api/v1/documents/uploads/initiate', [
                'folderId' => $folderId, 'fileName' => $name, 'mimeType' => 'application/pdf', 'sizeBytes' => strlen($content),
            ])->getJSON(), true)['data'];

        (new Client())->put($initiated['uploadUrl'], ['body' => $content, 'headers' => ['Content-Type' => 'application/pdf']]);

        $document = json_decode($this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post('api/v1/documents/uploads/complete', [
                'folderId' => $folderId, 's3Key' => $initiated['s3Key'], 'name' => $name, 'checksumSha256' => hash('sha256', $content),
            ])->getJSON(), true)['data'];

        $detail = json_decode($this->withHeaders($this->adminHeaders())->get("api/v1/documents/{$document['id']}")->getJSON(), true)['data'];

        return ['documentId' => $document['id'], 'versionId' => $detail['currentVersionDetail']['id'], 's3Key' => $initiated['s3Key']];
    }

    private function postCallback(array $body): \CodeIgniter\Test\TestResponse
    {
        $raw       = json_encode($body);
        $signature = hash_hmac('sha256', $raw, 'local-dev-hmac-secret-do-not-use-in-production');

        return $this->withHeaders(['X-Signature' => $signature])->withBody($raw)->withBodyFormat('json')
            ->post('api/v1/internal/processing-callback');
    }

    public function testNewUploadStartsPending(): void
    {
        $doc = $this->uploadDocument();

        $this->seeInDatabase('document_processing_jobs', ['document_version_id' => $doc['versionId'], 'status' => 'PENDING']);
    }

    public function testCompletedCallbackSetsThumbnailAndStatus(): void
    {
        $doc = $this->uploadDocument();

        $result = $this->postCallback([
            's3Key'          => $doc['s3Key'],
            'status'         => 'COMPLETED',
            'scanResult'     => 'CLEAN',
            'metadata'       => ['width' => 800, 'height' => 600],
            'thumbnailS3Key' => "thumbnails/{$doc['versionId']}.png",
        ]);

        $result->assertStatus(200);
        $this->seeInDatabase('document_processing_jobs', ['document_version_id' => $doc['versionId'], 'status' => 'COMPLETED', 'scan_result' => 'CLEAN']);
        $this->seeInDatabase('document_versions', ['id' => $doc['versionId'], 'thumbnail_s3_key' => "thumbnails/{$doc['versionId']}.png"]);

        $detail = json_decode($this->withHeaders($this->adminHeaders())->get("api/v1/documents/{$doc['documentId']}")->getJSON(), true)['data'];
        $this->assertSame('COMPLETED', $detail['processingStatus']);

        $thumbnail = $this->withHeaders($this->adminHeaders())->get("api/v1/documents/{$doc['documentId']}/thumbnail");
        $thumbnail->assertStatus(200);
    }

    public function testFailedCallbackRecordsErrorMessage(): void
    {
        $doc = $this->uploadDocument();

        $this->postCallback(['s3Key' => $doc['s3Key'], 'status' => 'FAILED', 'errorMessage' => 'Unsupported codec'])->assertStatus(200);

        $this->seeInDatabase('document_processing_jobs', ['document_version_id' => $doc['versionId'], 'status' => 'FAILED', 'error_message' => 'Unsupported codec']);
    }

    public function testUnknownS3KeyIs404SoTheLambdaKnowsToRetry(): void
    {
        // Simulates the S3 event firing before "complete" has run — the
        // Lambda is expected to treat this as retryable, not fatal.
        $result = $this->postCallback(['s3Key' => 'documents/999/nonexistent/ghost.pdf', 'status' => 'COMPLETED']);

        $result->assertStatus(404);
        $this->assertSame('DOCUMENT_VERSION_NOT_FOUND', json_decode($result->getJSON(), true)['error']['code']);
    }

    public function testMissingOrWrongSignatureIsRejected(): void
    {
        $body = json_encode(['s3Key' => 'documents/1/x/f.pdf', 'status' => 'COMPLETED']);

        $noSignature = $this->withBody($body)->withBodyFormat('json')->post('api/v1/internal/processing-callback');
        $noSignature->assertStatus(401);
        $this->assertSame('INVALID_SIGNATURE', json_decode($noSignature->getJSON(), true)['error']['code']);

        $wrongSignature = $this->withHeaders(['X-Signature' => 'not-the-right-signature'])
            ->withBody($body)->withBodyFormat('json')->post('api/v1/internal/processing-callback');
        $wrongSignature->assertStatus(401);
    }
}

