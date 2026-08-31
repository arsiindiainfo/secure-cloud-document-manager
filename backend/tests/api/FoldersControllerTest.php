<?php

namespace Tests\Api;

use Tests\Support\ApiTestCase;

/**
 * @internal
 */
final class FoldersControllerTest extends ApiTestCase
{
    public function testAdminCanCreateRootFolder(): void
    {
        $result = $this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post('api/v1/folders', ['name' => 'Client Contracts', 'parentFolderId' => null]);

        $result->assertStatus(201);
        $this->seeInDatabase('folders', ['name' => 'Client Contracts', 'parent_folder_id' => null]);
        // creator is auto-granted OWNER (§6.3)
        $body = json_decode($result->getJSON(), true);
        $this->seeInDatabase('document_permissions', [
            'folder_id' => $body['data']['id'], 'user_id' => 1, 'permission' => 'OWNER',
        ]);
    }

    public function testNonAdminCannotCreateRootFolder(): void
    {
        $result = $this->withHeaders($this->employeeHeaders())->withBodyFormat('json')
            ->post('api/v1/folders', ['name' => 'Not Allowed', 'parentFolderId' => null]);

        $result->assertStatus(403);
        $this->dontSeeInDatabase('folders', ['name' => 'Not Allowed']);
    }

    public function testAdminCanCreateChildFolder(): void
    {
        $root = json_decode($this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post('api/v1/folders', ['name' => 'Root', 'parentFolderId' => null])->getJSON(), true);

        $child = $this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post('api/v1/folders', ['name' => 'Child', 'parentFolderId' => $root['data']['id']]);

        $child->assertStatus(201);
        $this->seeInDatabase('folders', ['name' => 'Child', 'parent_folder_id' => $root['data']['id']]);
    }

    public function testDuplicateSiblingNameIsRejected(): void
    {
        $this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post('api/v1/folders', ['name' => 'Dup', 'parentFolderId' => null]);

        $result = $this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post('api/v1/folders', ['name' => 'Dup', 'parentFolderId' => null]);

        $result->assertStatus(409);
        $body = json_decode($result->getJSON(), true);
        $this->assertSame('DUPLICATE_NAME', $body['error']['code']);
    }

    public function testNonParticipantGetsNotFoundNeverForbidden(): void
    {
        // §6.3 guardrail: a folder the caller has no grant on must 404, not 403 —
        // a non-participant should never learn the folder even exists.
        $root = json_decode($this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post('api/v1/folders', ['name' => 'Private', 'parentFolderId' => null])->getJSON(), true);

        $result = $this->withHeaders($this->employeeHeaders())->withBodyFormat('json')
            ->post('api/v1/folders', ['name' => 'Sneaky Child', 'parentFolderId' => $root['data']['id']]);

        $result->assertStatus(404);
    }

    public function testChildrenListingReturnsBreadcrumb(): void
    {
        $root = json_decode($this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post('api/v1/folders', ['name' => 'Breadcrumb Root', 'parentFolderId' => null])->getJSON(), true);
        $child = json_decode($this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post('api/v1/folders', ['name' => 'Breadcrumb Child', 'parentFolderId' => $root['data']['id']])->getJSON(), true);

        $result = $this->withHeaders($this->adminHeaders())->get("api/v1/folders/{$child['data']['id']}/children");

        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);
        $this->assertSame(['Breadcrumb Root', 'Breadcrumb Child'], array_column($body['data']['breadcrumb'], 'name'));
    }

    public function testRenameFolder(): void
    {
        $folder = json_decode($this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post('api/v1/folders', ['name' => 'Old Name', 'parentFolderId' => null])->getJSON(), true);

        $result = $this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->put("api/v1/folders/{$folder['data']['id']}", ['name' => 'New Name']);

        $result->assertStatus(200);
        $this->seeInDatabase('folders', ['id' => $folder['data']['id'], 'name' => 'New Name']);
    }

    public function testMovingFolderIntoOwnDescendantIsRejected(): void
    {
        $root = json_decode($this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post('api/v1/folders', ['name' => 'Parent', 'parentFolderId' => null])->getJSON(), true);
        $child = json_decode($this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post('api/v1/folders', ['name' => 'Nested', 'parentFolderId' => $root['data']['id']])->getJSON(), true);

        $result = $this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->put("api/v1/folders/{$root['data']['id']}", ['name' => 'Parent', 'parentFolderId' => $child['data']['id']]);

        $result->assertStatus(422);
        $body = json_decode($result->getJSON(), true);
        $this->assertSame('CYCLE_DETECTED', $body['error']['code']);
    }

    public function testDeleteCascadesToChildrenAndRestoreRequiresParentFirst(): void
    {
        $root = json_decode($this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post('api/v1/folders', ['name' => 'ToDelete', 'parentFolderId' => null])->getJSON(), true);
        $child = json_decode($this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post('api/v1/folders', ['name' => 'ToDeleteChild', 'parentFolderId' => $root['data']['id']])->getJSON(), true);

        $this->withHeaders($this->adminHeaders())->delete("api/v1/folders/{$root['data']['id']}")->assertStatus(200);
        $this->dontSeeInDatabase('folders', ['id' => $child['data']['id'], 'deleted_at' => null]);

        // restoring the child before the parent must fail (§8.4 PARENT_STILL_DELETED)
        $childRestore = $this->withHeaders($this->adminHeaders())->post("api/v1/folders/{$child['data']['id']}/restore");
        $childRestore->assertStatus(422);

        $this->withHeaders($this->adminHeaders())->post("api/v1/folders/{$root['data']['id']}/restore")->assertStatus(200);
        $this->withHeaders($this->adminHeaders())->post("api/v1/folders/{$child['data']['id']}/restore")->assertStatus(200);
        $this->seeInDatabase('folders', ['id' => $child['data']['id'], 'deleted_at' => null]);
    }
}
