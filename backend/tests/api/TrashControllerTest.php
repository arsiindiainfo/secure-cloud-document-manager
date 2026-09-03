<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace Tests\Api;

use Tests\Support\ApiTestCase;

/**
 * @internal
 *
 * §22.8 — GET /trash, scoped to the caller.
 */
final class TrashControllerTest extends ApiTestCase
{
    public function testDeletedFolderAppearsInTrashWithDaysRemaining(): void
    {
        $folder = json_decode($this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post('api/v1/folders', ['name' => 'ToTrash', 'parentFolderId' => null])->getJSON(), true)['data'];

        $this->withHeaders($this->adminHeaders())->delete("api/v1/folders/{$folder['id']}");

        $result = $this->withHeaders($this->adminHeaders())->get('api/v1/trash');
        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true)['data'];

        $trashedFolder = current(array_filter($body['folders'], static fn ($f) => $f['id'] === $folder['id']));
        $this->assertNotFalse($trashedFolder);
        $this->assertSame(30, $trashedFolder['daysRemaining']);
    }

    public function testTrashIsScopedToOwnership(): void
    {
        $folder = json_decode($this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post('api/v1/folders', ['name' => 'AdminOnly', 'parentFolderId' => null])->getJSON(), true)['data'];
        $this->withHeaders($this->adminHeaders())->delete("api/v1/folders/{$folder['id']}");

        // employee has no grant on this folder at all — it must not appear in their trash.
        $result = $this->withHeaders($this->employeeHeaders())->get('api/v1/trash');
        $body   = json_decode($result->getJSON(), true)['data'];
        $this->assertEmpty(array_filter($body['folders'], static fn ($f) => $f['id'] === $folder['id']));
    }
}

