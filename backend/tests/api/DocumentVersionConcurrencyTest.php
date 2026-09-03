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
 * §25 — the concurrency test called out in the plan's Definition of Done:
 * two parallel connections call sp_document_new_version for the same
 * document, asserting no duplicate version_no is ever produced (proves the
 * `SELECT ... FOR UPDATE` row lock in §8.2 actually works, not just that the
 * happy path looks right under sequential calls).
 */
final class DocumentVersionConcurrencyTest extends ApiTestCase
{
    public function testConcurrentNewVersionCallsNeverProduceADuplicateVersionNumber(): void
    {
        $folder = json_decode($this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post('api/v1/folders', ['name' => uniqid('folder-', true), 'parentFolderId' => null])->getJSON(), true);
        $folderId = $folder['data']['id'];

        $content   = 'v1';
        $initiated = json_decode($this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post('api/v1/documents/uploads/initiate', [
                'folderId' => $folderId, 'fileName' => 'race.pdf', 'mimeType' => 'application/pdf', 'sizeBytes' => strlen($content),
            ])->getJSON(), true)['data'];
        (new Client())->put($initiated['uploadUrl'], ['body' => $content, 'headers' => ['Content-Type' => 'application/pdf']]);
        $document = json_decode($this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post('api/v1/documents/uploads/complete', [
                'folderId' => $folderId, 's3Key' => $initiated['s3Key'], 'name' => 'race.pdf', 'checksumSha256' => hash('sha256', $content),
            ])->getJSON(), true)['data'];

        $scriptPath = __DIR__ . '/../_support/scripts/call_new_version_worker.php';
        $spec       = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];

        // Launch both before reading either's output — reading synchronously
        // in between would serialize them and defeat the point of the test.
        $processA = proc_open(['php', $scriptPath, (string) $document['id'], '1'], $spec, $pipesA);
        $processB = proc_open(['php', $scriptPath, (string) $document['id'], '1'], $spec, $pipesB);

        $outputA = stream_get_contents($pipesA[1]);
        $errorA  = stream_get_contents($pipesA[2]);
        fclose($pipesA[1]);
        fclose($pipesA[2]);
        proc_close($processA);

        $outputB = stream_get_contents($pipesB[1]);
        $errorB  = stream_get_contents($pipesB[2]);
        fclose($pipesB[1]);
        fclose($pipesB[2]);
        proc_close($processB);

        $resultA = json_decode(trim($outputA), true);
        $resultB = json_decode(trim($outputB), true);

        $this->assertNotNull($resultA, "worker A produced no valid JSON. stderr: {$errorA}");
        $this->assertNotNull($resultB, "worker B produced no valid JSON. stderr: {$errorB}");
        $this->assertSame('OK', $resultA['statusCode']);
        $this->assertSame('OK', $resultB['statusCode']);

        $this->assertNotSame(
            $resultA['versionNo'],
            $resultB['versionNo'],
            'both concurrent calls produced the same version_no — the row lock did not serialize them',
        );
        $this->assertSame([2, 3], [min($resultA['versionNo'], $resultB['versionNo']), max($resultA['versionNo'], $resultB['versionNo'])]);

        $this->seeInDatabase('document_versions', ['document_id' => $document['id'], 'version_no' => 2]);
        $this->seeInDatabase('document_versions', ['document_id' => $document['id'], 'version_no' => 3]);
    }
}

