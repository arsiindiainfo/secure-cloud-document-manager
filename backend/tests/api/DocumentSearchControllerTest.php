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
 * §17/§24 — GET /documents, backed by sp_document_search (FULLTEXT).
 */
final class DocumentSearchControllerTest extends ApiTestCase
{
    /** @return array{documentId: int, folderId: int} */
    private function uploadDocument(string $name, string $content = 'x'): array
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

    public function testSearchMatchesByNameAndReturnsBreadcrumbFolderName(): void
    {
        $this->uploadDocument('NovaTrail-MSA.pdf');
        $this->uploadDocument('unrelated.pdf');

        $result = $this->withHeaders($this->adminHeaders())->get('api/v1/documents?search=NovaTrail');
        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);
        $this->assertCount(1, $body['data']);
        $this->assertSame('NovaTrail-MSA.pdf', $body['data'][0]['name']);
        $this->assertArrayHasKey('folderName', $body['data'][0]);
        $this->assertSame(1, $body['meta']['total']);
    }

    public function testSearchOnlyReturnsDocumentsTheCallerCanAccess(): void
    {
        $doc = $this->uploadDocument('OwnedByAdmin.pdf');
        $this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post("api/v1/documents/{$doc['documentId']}/permissions", ['email' => 'manager@meridian.test', 'permission' => 'VIEWER']);

        $managerResult = $this->withHeaders($this->managerHeaders())->get('api/v1/documents?search=OwnedByAdmin');
        $this->assertCount(1, json_decode($managerResult->getJSON(), true)['data']);

        $employeeResult = $this->withHeaders($this->employeeHeaders())->get('api/v1/documents?search=OwnedByAdmin');
        $this->assertCount(0, json_decode($employeeResult->getJSON(), true)['data']);
    }

    public function testFolderFilterScopesResults(): void
    {
        $docA = $this->uploadDocument('InFolderA.pdf');
        $this->uploadDocument('InFolderB.pdf');

        $result = $this->withHeaders($this->adminHeaders())->get("api/v1/documents?folderId={$docA['folderId']}");
        $body   = json_decode($result->getJSON(), true);
        $this->assertCount(1, $body['data']);
        $this->assertSame('InFolderA.pdf', $body['data'][0]['name']);
    }
}

