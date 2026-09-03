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
 * §19 — GET /s/:token, the one public unauthenticated route in the API.
 */
final class PublicShareControllerTest extends ApiTestCase
{
    /** @return array{documentId: int, folderId: int} */
    private function uploadDocument(string $name = 'public.pdf', string $content = 'public content'): array
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

        return ['documentId' => $document['id'], 'folderId' => $folderId];
    }

    /** @return array{id: int, token: string} */
    private function createShareLink(int $documentId, string $permission = 'DOWNLOAD', int $expiresInHours = 24, ?int $maxDownloads = null): array
    {
        $body = ['permission' => $permission, 'expiresInHours' => $expiresInHours];
        if ($maxDownloads !== null) {
            $body['maxDownloads'] = $maxDownloads;
        }

        $result = json_decode($this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post("api/v1/documents/{$documentId}/share-links", $body)->getJSON(), true)['data'];

        return ['id' => $result['id'], 'token' => explode('/s/', $result['url'])[1]];
    }

    public function testResolvingAValidDownloadLinkWorks(): void
    {
        $content = 'public content';
        $doc      = $this->uploadDocument('public.pdf', $content);
        $link     = $this->createShareLink($doc['documentId']);

        $result = $this->get("s/{$link['token']}");
        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true)['data'];
        $this->assertSame('public.pdf', $body['documentName']);
        $this->assertSame('DOWNLOAD', $body['permission']);
        $this->assertNotNull($body['downloadUrl']);

        $fetched = (new Client())->get($body['downloadUrl']);
        $this->assertSame($content, (string) $fetched->getBody());
    }

    public function testViewOnlyLinkOmitsDownloadUrlForNonInlineableTypes(): void
    {
        $folder = json_decode($this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post('api/v1/folders', ['name' => uniqid('folder-', true), 'parentFolderId' => null])->getJSON(), true);
        $folderId = $folder['data']['id'];
        $content  = 'zip bytes';
        $initiated = json_decode($this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post('api/v1/documents/uploads/initiate', [
                'folderId' => $folderId, 'fileName' => 'archive.zip', 'mimeType' => 'application/zip', 'sizeBytes' => strlen($content),
            ])->getJSON(), true)['data'];
        (new Client())->put($initiated['uploadUrl'], ['body' => $content, 'headers' => ['Content-Type' => 'application/zip']]);
        $document = json_decode($this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post('api/v1/documents/uploads/complete', [
                'folderId' => $folderId, 's3Key' => $initiated['s3Key'], 'name' => 'archive.zip', 'checksumSha256' => hash('sha256', $content),
            ])->getJSON(), true)['data'];

        $link = $this->createShareLink($document['id'], 'VIEW');

        $result = $this->get("s/{$link['token']}");
        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true)['data'];
        $this->assertSame('VIEW', $body['permission']);
        $this->assertNull($body['downloadUrl']);
    }

    public function testUnknownTokenIs404(): void
    {
        $result = $this->get('s/' . str_repeat('x', 43));
        $result->assertStatus(404);
        $body = json_decode($result->getJSON(), true);
        $this->assertSame('SHARE_LINK_NOT_FOUND', $body['error']['code']);
    }

    public function testMalformedTokenIs404NotAValidationError(): void
    {
        $result = $this->get('s/not-even-close-to-43-chars');
        $result->assertStatus(404);
        $body = json_decode($result->getJSON(), true);
        $this->assertSame('SHARE_LINK_NOT_FOUND', $body['error']['code']);
    }

    public function testRevokedLinkIs409(): void
    {
        $doc  = $this->uploadDocument();
        $link = $this->createShareLink($doc['documentId']);
        $this->withHeaders($this->adminHeaders())->delete("api/v1/share-links/{$link['id']}");

        $result = $this->get("s/{$link['token']}");
        $result->assertStatus(409);
        $this->assertSame('SHARE_LINK_REVOKED', json_decode($result->getJSON(), true)['error']['code']);
    }

    public function testExpiredLinkIs409(): void
    {
        $doc  = $this->uploadDocument();
        $link = $this->createShareLink($doc['documentId']);
        $this->db->table('share_links')->where('id', $link['id'])->update(['expires_at' => date('Y-m-d H:i:s', time() - 3600)]);

        $result = $this->get("s/{$link['token']}");
        $result->assertStatus(409);
        $this->assertSame('SHARE_LINK_EXPIRED', json_decode($result->getJSON(), true)['error']['code']);
    }

    public function testDownloadLimitReachedIs409OnTheNextAttempt(): void
    {
        $doc  = $this->uploadDocument();
        $link = $this->createShareLink($doc['documentId'], 'DOWNLOAD', 24, 1);

        $this->get("s/{$link['token']}")->assertStatus(200);

        $result = $this->get("s/{$link['token']}");
        $result->assertStatus(409);
        $this->assertSame('SHARE_LINK_LIMIT_REACHED', json_decode($result->getJSON(), true)['error']['code']);
    }
}

