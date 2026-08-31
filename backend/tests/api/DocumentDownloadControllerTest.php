<?php

namespace Tests\Api;

use GuzzleHttp\Client;
use Tests\Support\ApiTestCase;

/**
 * @internal
 *
 * §18 — download/preview/thumbnail. Split from DocumentsControllerTest to
 * keep each file focused on one API surface.
 */
final class DocumentDownloadControllerTest extends ApiTestCase
{
    /** @return array{documentId: int, folderId: int} */
    private function uploadDocument(string $name, string $mimeType, string $content): array
    {
        $folder = json_decode($this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post('api/v1/folders', ['name' => uniqid('folder-', true), 'parentFolderId' => null])->getJSON(), true);
        $folderId = $folder['data']['id'];

        $initiated = json_decode($this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post('api/v1/documents/uploads/initiate', [
                'folderId' => $folderId, 'fileName' => $name, 'mimeType' => $mimeType, 'sizeBytes' => strlen($content),
            ])->getJSON(), true)['data'];

        (new Client())->put($initiated['uploadUrl'], ['body' => $content, 'headers' => ['Content-Type' => $mimeType]]);

        $document = json_decode($this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post('api/v1/documents/uploads/complete', [
                'folderId' => $folderId, 's3Key' => $initiated['s3Key'], 'name' => $name, 'checksumSha256' => hash('sha256', $content),
            ])->getJSON(), true)['data'];

        return ['documentId' => $document['id'], 'folderId' => $folderId];
    }

    public function testDownloadReturnsAWorkingPresignedUrlAndAudits(): void
    {
        $content = 'downloadable content';
        $doc      = $this->uploadDocument('dl.pdf', 'application/pdf', $content);

        $result = $this->withHeaders($this->adminHeaders())->get("api/v1/documents/{$doc['documentId']}/download");
        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true)['data'];
        $this->assertArrayHasKey('url', $body);

        $fetched = (new Client())->get($body['url']);
        $this->assertSame($content, (string) $fetched->getBody());

        $this->seeInDatabase('audit_logs', ['action' => 'DOCUMENT_DOWNLOADED', 'entity_id' => $doc['documentId']]);
    }

    public function testPreviewIsInlineForPdfAndFallsBackToThumbnailOtherwise(): void
    {
        $pdf = $this->uploadDocument('view.pdf', 'application/pdf', 'pdf bytes');
        $this->withHeaders($this->adminHeaders())->get("api/v1/documents/{$pdf['documentId']}/preview")->assertStatus(200);

        // a zip has no inline preview and no thumbnail yet (Phase 4 generates those) — 404
        $zip = $this->uploadDocument('archive.zip', 'application/zip', 'zip bytes');
        $result = $this->withHeaders($this->adminHeaders())->get("api/v1/documents/{$zip['documentId']}/preview");
        $result->assertStatus(404);
        $body = json_decode($result->getJSON(), true);
        $this->assertSame('THUMBNAIL_NOT_READY', $body['error']['code']);
    }

    public function testNonParticipantCannotDownload(): void
    {
        $doc = $this->uploadDocument('secret.pdf', 'application/pdf', 'x');

        $this->withHeaders($this->employeeHeaders())->get("api/v1/documents/{$doc['documentId']}/download")->assertStatus(404);
    }
}
