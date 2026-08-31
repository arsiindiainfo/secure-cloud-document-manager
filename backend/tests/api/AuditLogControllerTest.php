<?php

namespace Tests\Api;

use Tests\Support\ApiTestCase;

/**
 * @internal
 *
 * §19 — GET /audit-logs, ADMIN only.
 */
final class AuditLogControllerTest extends ApiTestCase
{
    public function testAdminSeesAuditTrailFilterableByAction(): void
    {
        $this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post('api/v1/folders', ['name' => 'Audited Folder', 'parentFolderId' => null]);

        $result = $this->withHeaders($this->adminHeaders())->get('api/v1/audit-logs?action=FOLDER_CREATED');
        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);
        $this->assertGreaterThanOrEqual(1, count($body['data']));
        $this->assertSame('FOLDER_CREATED', $body['data'][0]['action']);
        $this->assertArrayHasKey('total', $body['meta']);
    }

    public function testNonAdminCannotViewAuditLogs(): void
    {
        $this->withHeaders($this->managerHeaders())->get('api/v1/audit-logs')->assertStatus(403);
        $this->withHeaders($this->employeeHeaders())->get('api/v1/audit-logs')->assertStatus(403);
    }
}
