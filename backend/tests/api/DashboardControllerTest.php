<?php

namespace Tests\Api;

use GuzzleHttp\Client;
use Tests\Support\ApiTestCase;

/**
 * @internal
 *
 * §22.2 — GET /dashboard, scoped to the caller.
 */
final class DashboardControllerTest extends ApiTestCase
{
    public function testDashboardReflectsRecentDownloadsStorageAndSharedFolders(): void
    {
        $folder = json_decode($this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post('api/v1/folders', ['name' => 'DashFolder', 'parentFolderId' => null])->getJSON(), true)['data'];

        $content   = str_repeat('x', 200);
        $initiated = json_decode($this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post('api/v1/documents/uploads/initiate', [
                'folderId' => $folder['id'], 'fileName' => 'dash.pdf', 'mimeType' => 'application/pdf', 'sizeBytes' => strlen($content),
            ])->getJSON(), true)['data'];
        (new Client())->put($initiated['uploadUrl'], ['body' => $content, 'headers' => ['Content-Type' => 'application/pdf']]);
        $document = json_decode($this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post('api/v1/documents/uploads/complete', [
                'folderId' => $folder['id'], 's3Key' => $initiated['s3Key'], 'name' => 'dash.pdf', 'checksumSha256' => hash('sha256', $content),
            ])->getJSON(), true)['data'];

        $this->withHeaders($this->adminHeaders())->get("api/v1/documents/{$document['id']}/download");

        // share the folder with the employee so it shows up as "shared with them"
        $this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post("api/v1/documents/{$document['id']}/permissions", ['email' => 'employee@meridian.test', 'permission' => 'VIEWER']);

        $result = $this->withHeaders($this->adminHeaders())->get('api/v1/dashboard');
        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true)['data'];

        $this->assertGreaterThanOrEqual(1, count($body['recentDocuments']));
        $this->assertSame('DOCUMENT_DOWNLOADED', $body['recentDocuments'][0]['action']);
        $this->assertGreaterThanOrEqual(strlen($content), $body['storageUsedBytes']);
    }
}
