<?php

namespace Tests\Api;

use Tests\Support\ApiTestCase;

/**
 * @internal
 *
 * §18 — internal permission grants and external share links, both scoped to
 * a document. Uses direct document-level grants (not folder inheritance) so
 * these tests are independent of the folder ACL surface (§20 exposes no
 * folder-permission endpoint — only document-level sharing).
 */
final class SharingControllerTest extends ApiTestCase
{
    /** @return array{documentId: int, folderId: int} */
    private function uploadDocument(string $name = 'shared.pdf', string $content = 'shared content'): array
    {
        $folder = json_decode($this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post('api/v1/folders', ['name' => uniqid('folder-', true), 'parentFolderId' => null])->getJSON(), true);
        $folderId = $folder['data']['id'];

        $initiated = json_decode($this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post('api/v1/documents/uploads/initiate', [
                'folderId' => $folderId, 'fileName' => $name, 'mimeType' => 'application/pdf', 'sizeBytes' => strlen($content),
            ])->getJSON(), true)['data'];

        (new \GuzzleHttp\Client())->put($initiated['uploadUrl'], ['body' => $content, 'headers' => ['Content-Type' => 'application/pdf']]);

        $document = json_decode($this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post('api/v1/documents/uploads/complete', [
                'folderId' => $folderId, 's3Key' => $initiated['s3Key'], 'name' => $name, 'checksumSha256' => hash('sha256', $content),
            ])->getJSON(), true)['data'];

        return ['documentId' => $document['id'], 'folderId' => $folderId];
    }

    public function testOwnerCanGrantAndListDirectAccess(): void
    {
        $doc = $this->uploadDocument();

        $grant = $this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post("api/v1/documents/{$doc['documentId']}/permissions", ['email' => 'manager@meridian.test', 'permission' => 'EDITOR']);
        $grant->assertStatus(201);
        $this->seeInDatabase('document_permissions', ['document_id' => $doc['documentId'], 'user_id' => 2, 'permission' => 'EDITOR']);

        $list = $this->withHeaders($this->adminHeaders())->get("api/v1/documents/{$doc['documentId']}/permissions");
        $list->assertStatus(200);
        $body = json_decode($list->getJSON(), true)['data'];
        // admin (creator, auto-OWNER) + the manager grant just created
        $this->assertCount(2, $body);
        $this->assertContains('manager@meridian.test', array_column($body, 'email'));
    }

    public function testGrantingAnUnknownEmailIs404(): void
    {
        $doc = $this->uploadDocument();

        $result = $this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post("api/v1/documents/{$doc['documentId']}/permissions", ['email' => 'nobody@meridian.test', 'permission' => 'VIEWER']);

        $result->assertStatus(404);
        $body = json_decode($result->getJSON(), true);
        $this->assertSame('USER_NOT_FOUND', $body['error']['code']);
    }

    public function testGrantedEditorCanThenActWithoutFolderAccess(): void
    {
        $doc = $this->uploadDocument();
        $this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post("api/v1/documents/{$doc['documentId']}/permissions", ['email' => 'employee@meridian.test', 'permission' => 'EDITOR']);

        // employee has no folder access at all, only this direct document grant
        $this->withHeaders($this->employeeHeaders())->get("api/v1/documents/{$doc['documentId']}")->assertStatus(200);
        $this->withHeaders($this->employeeHeaders())->withBodyFormat('json')
            ->put("api/v1/documents/{$doc['documentId']}", ['name' => 'renamed-by-editor.pdf'])->assertStatus(200);
    }

    public function testNonParticipantGetsNotFoundNeverForbiddenForPermissionEndpoints(): void
    {
        $doc = $this->uploadDocument();

        $this->withHeaders($this->employeeHeaders())->get("api/v1/documents/{$doc['documentId']}/permissions")->assertStatus(404);
        $this->withHeaders($this->employeeHeaders())->withBodyFormat('json')
            ->post("api/v1/documents/{$doc['documentId']}/permissions", ['email' => 'employee@meridian.test', 'permission' => 'VIEWER'])
            ->assertStatus(404);
    }

    public function testViewerCannotGrantOrRevokeOnlyOwnerCan(): void
    {
        $doc = $this->uploadDocument();
        $this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post("api/v1/documents/{$doc['documentId']}/permissions", ['email' => 'employee@meridian.test', 'permission' => 'VIEWER']);

        // employee is a VIEWER (a participant) but not OWNER — still 404, not 403 (§6.3)
        $result = $this->withHeaders($this->employeeHeaders())->withBodyFormat('json')
            ->post("api/v1/documents/{$doc['documentId']}/permissions", ['email' => 'manager@meridian.test', 'permission' => 'VIEWER']);
        $result->assertStatus(404);
    }

    public function testRevokeRemovesAccess(): void
    {
        $doc = $this->uploadDocument();
        $this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post("api/v1/documents/{$doc['documentId']}/permissions", ['email' => 'manager@meridian.test', 'permission' => 'EDITOR']);

        $result = $this->withHeaders($this->adminHeaders())->delete("api/v1/documents/{$doc['documentId']}/permissions/2");
        $result->assertStatus(200);
        $this->dontSeeInDatabase('document_permissions', ['document_id' => $doc['documentId'], 'user_id' => 2]);

        $this->withHeaders($this->managerHeaders())->get("api/v1/documents/{$doc['documentId']}")->assertStatus(404);
    }

    public function testRevokingAGrantThatDoesNotExistIs404(): void
    {
        $doc = $this->uploadDocument();

        $result = $this->withHeaders($this->adminHeaders())->delete("api/v1/documents/{$doc['documentId']}/permissions/2");
        $result->assertStatus(404);
        $body = json_decode($result->getJSON(), true);
        $this->assertSame('PERMISSION_GRANT_NOT_FOUND', $body['error']['code']);
    }

    public function testCannotRevokeTheLastRemainingOwner(): void
    {
        $doc = $this->uploadDocument();
        // admin (userId 1) is auto-OWNER from upload; grant manager OWNER too.
        $this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post("api/v1/documents/{$doc['documentId']}/permissions", ['email' => 'manager@meridian.test', 'permission' => 'OWNER']);

        // two owners now — revoking one is fine.
        $this->withHeaders($this->adminHeaders())->delete("api/v1/documents/{$doc['documentId']}/permissions/2")->assertStatus(200);

        // only admin remains — revoking them must fail even though admin is calling it themself.
        $result = $this->withHeaders($this->adminHeaders())->delete("api/v1/documents/{$doc['documentId']}/permissions/1");
        $result->assertStatus(409);
        $body = json_decode($result->getJSON(), true);
        $this->assertSame('LAST_OWNER', $body['error']['code']);
        $this->seeInDatabase('document_permissions', ['document_id' => $doc['documentId'], 'user_id' => 1, 'permission' => 'OWNER']);
    }

    public function testEditorCanCreateAndListShareLinksButNotAViewer(): void
    {
        $doc = $this->uploadDocument();
        $this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post("api/v1/documents/{$doc['documentId']}/permissions", ['email' => 'manager@meridian.test', 'permission' => 'EDITOR']);
        $this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post("api/v1/documents/{$doc['documentId']}/permissions", ['email' => 'employee@meridian.test', 'permission' => 'VIEWER']);

        $create = $this->withHeaders($this->managerHeaders())->withBodyFormat('json')
            ->post("api/v1/documents/{$doc['documentId']}/share-links", ['permission' => 'DOWNLOAD', 'expiresInHours' => 24]);
        $create->assertStatus(201);
        $body = json_decode($create->getJSON(), true)['data'];
        $this->assertArrayHasKey('url', $body);
        $this->assertStringContainsString('/s/', $body['url']);

        $this->withHeaders($this->managerHeaders())->get("api/v1/documents/{$doc['documentId']}/share-links")->assertStatus(200);

        $viewerAttempt = $this->withHeaders($this->employeeHeaders())->withBodyFormat('json')
            ->post("api/v1/documents/{$doc['documentId']}/share-links", ['permission' => 'DOWNLOAD', 'expiresInHours' => 24]);
        $viewerAttempt->assertStatus(404);
    }

    public function testRevokingAShareLinkStopsItWorking(): void
    {
        $doc = $this->uploadDocument();
        $create = json_decode($this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post("api/v1/documents/{$doc['documentId']}/share-links", ['permission' => 'DOWNLOAD', 'expiresInHours' => 24])->getJSON(), true)['data'];

        $this->withHeaders($this->adminHeaders())->delete("api/v1/share-links/{$create['id']}")->assertStatus(200);
        $this->seeInDatabase('share_links', ['id' => $create['id']]);

        $token = explode('/s/', $create['url'])[1];
        $this->get("s/{$token}")->assertStatus(409);
    }
}
