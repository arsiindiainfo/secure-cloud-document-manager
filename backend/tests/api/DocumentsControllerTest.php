<?php

namespace Tests\Api;

use GuzzleHttp\Client;
use Tests\Support\ApiTestCase;

/**
 * @internal
 *
 * Exercises the real three-step upload flow (§17, §21.2) against LocalStack —
 * not mocked, per §25's "hits real stored procedures" philosophy extended to
 * "hits a real S3-compatible endpoint" for the parts that touch storage.
 */
final class DocumentsControllerTest extends ApiTestCase
{
    private function createFolder(string $name): int
    {
        $result = json_decode($this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post('api/v1/folders', ['name' => $name, 'parentFolderId' => null])->getJSON(), true);

        return $result['data']['id'];
    }

    /** @return array{uploadUrl: string, s3Key: string} */
    private function initiateUpload(int $folderId, string $fileName, string $mimeType, int $sizeBytes): array
    {
        $result = json_decode($this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post('api/v1/documents/uploads/initiate', [
                'folderId' => $folderId, 'fileName' => $fileName, 'mimeType' => $mimeType, 'sizeBytes' => $sizeBytes,
            ])->getJSON(), true);

        return $result['data'];
    }

    private function putToS3(string $uploadUrl, string $content, string $mimeType): void
    {
        (new Client())->put($uploadUrl, ['body' => $content, 'headers' => ['Content-Type' => $mimeType]]);
    }

    public function testFullUploadFlowCreatesDocumentWithCurrentVersion(): void
    {
        $folderId = $this->createFolder('Uploads');
        $content  = str_repeat('A', 1024);
        $checksum = hash('sha256', $content);

        $initiated = $this->initiateUpload($folderId, 'contract.pdf', 'application/pdf', strlen($content));
        $this->assertStringStartsWith("documents/{$folderId}/", $initiated['s3Key']);

        $this->putToS3($initiated['uploadUrl'], $content, 'application/pdf');

        $complete = $this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post('api/v1/documents/uploads/complete', [
                'folderId' => $folderId, 's3Key' => $initiated['s3Key'], 'name' => 'contract.pdf', 'checksumSha256' => $checksum,
            ]);

        $complete->assertStatus(201);
        $body = json_decode($complete->getJSON(), true);
        $this->assertSame('contract.pdf', $body['data']['name']);
        $this->assertSame(1, $body['data']['currentVersion']);

        $this->seeInDatabase('documents', ['name' => 'contract.pdf', 'folder_id' => $folderId]);
        $this->seeInDatabase('document_versions', ['document_id' => $body['data']['id'], 'version_no' => 1, 'size_bytes' => strlen($content)]);
        // uploader auto-granted OWNER directly on the document (§6.3)
        $this->seeInDatabase('document_permissions', ['document_id' => $body['data']['id'], 'user_id' => 1, 'permission' => 'OWNER']);
    }

    public function testUnsupportedFileTypeIsRejectedAtInitiate(): void
    {
        $folderId = $this->createFolder('BadTypes');

        $result = $this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post('api/v1/documents/uploads/initiate', [
                'folderId' => $folderId, 'fileName' => 'virus.exe', 'mimeType' => 'application/x-msdownload', 'sizeBytes' => 100,
            ]);

        $result->assertStatus(400);
        $body = json_decode($result->getJSON(), true);
        $this->assertSame('UNSUPPORTED_FILE_TYPE', $body['error']['code']);
    }

    public function testOversizedFileIsRejectedAtInitiate(): void
    {
        $folderId = $this->createFolder('TooBig');

        $result = $this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post('api/v1/documents/uploads/initiate', [
                'folderId' => $folderId, 'fileName' => 'huge.pdf', 'mimeType' => 'application/pdf', 'sizeBytes' => 26 * 1024 * 1024,
            ]);

        $result->assertStatus(400);
        $body = json_decode($result->getJSON(), true);
        $this->assertSame('FILE_TOO_LARGE', $body['error']['code']);
    }

    public function testCompleteFailsIfObjectWasNeverUploaded(): void
    {
        $folderId  = $this->createFolder('NeverUploaded');
        $initiated = $this->initiateUpload($folderId, 'ghost.pdf', 'application/pdf', 100);

        // deliberately skip the S3 PUT
        $result = $this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post('api/v1/documents/uploads/complete', [
                'folderId' => $folderId, 's3Key' => $initiated['s3Key'], 'name' => 'ghost.pdf', 'checksumSha256' => hash('sha256', ''),
            ]);

        $result->assertStatus(400);
    }

    public function testNewVersionFlowIncrementsVersionNumber(): void
    {
        $folderId = $this->createFolder('Versioned');
        $v1       = str_repeat('A', 500);
        $initiated = $this->initiateUpload($folderId, 'report.pdf', 'application/pdf', strlen($v1));
        $this->putToS3($initiated['uploadUrl'], $v1, 'application/pdf');
        $document = json_decode($this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post('api/v1/documents/uploads/complete', [
                'folderId' => $folderId, 's3Key' => $initiated['s3Key'], 'name' => 'report.pdf', 'checksumSha256' => hash('sha256', $v1),
            ])->getJSON(), true)['data'];

        $v2 = str_repeat('B', 700);
        $versionInitiated = json_decode($this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post("api/v1/documents/{$document['id']}/versions/initiate", [
                'fileName' => 'report.pdf', 'mimeType' => 'application/pdf', 'sizeBytes' => strlen($v2),
            ])->getJSON(), true)['data'];
        $this->assertStringStartsWith("documents/{$folderId}/{$document['id']}/", $versionInitiated['s3Key']);
        $this->putToS3($versionInitiated['uploadUrl'], $v2, 'application/pdf');

        $versionComplete = $this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post("api/v1/documents/{$document['id']}/versions/complete", [
                's3Key' => $versionInitiated['s3Key'], 'checksumSha256' => hash('sha256', $v2),
            ]);
        $versionComplete->assertStatus(201);
        $versionBody = json_decode($versionComplete->getJSON(), true);
        $this->assertSame(2, $versionBody['data']['versionNo']);

        $versions = $this->withHeaders($this->adminHeaders())->get("api/v1/documents/{$document['id']}/versions");
        $versions->assertStatus(200);
        $this->assertCount(2, json_decode($versions->getJSON(), true)['data']);
    }

    public function testShowReturnsEffectivePermissionAndCurrentVersionDetail(): void
    {
        $folderId  = $this->createFolder('DetailView');
        $content   = 'hello world';
        $initiated = $this->initiateUpload($folderId, 'note.pdf', 'application/pdf', strlen($content));
        $this->putToS3($initiated['uploadUrl'], $content, 'application/pdf');
        $document = json_decode($this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post('api/v1/documents/uploads/complete', [
                'folderId' => $folderId, 's3Key' => $initiated['s3Key'], 'name' => 'note.pdf', 'checksumSha256' => hash('sha256', $content),
            ])->getJSON(), true)['data'];

        $result = $this->withHeaders($this->adminHeaders())->get("api/v1/documents/{$document['id']}");

        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true)['data'];
        $this->assertSame('OWNER', $body['effectivePermission']);
        $this->assertSame(strlen($content), $body['currentVersionDetail']['sizeBytes']);
    }

    public function testUpdateMetadataAndSoftDeleteRestore(): void
    {
        $folderId  = $this->createFolder('MetaOps');
        $content   = 'x';
        $initiated = $this->initiateUpload($folderId, 'draft.pdf', 'application/pdf', 1);
        $this->putToS3($initiated['uploadUrl'], $content, 'application/pdf');
        $document = json_decode($this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post('api/v1/documents/uploads/complete', [
                'folderId' => $folderId, 's3Key' => $initiated['s3Key'], 'name' => 'draft.pdf', 'checksumSha256' => hash('sha256', $content),
            ])->getJSON(), true)['data'];

        $update = $this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->put("api/v1/documents/{$document['id']}", ['name' => 'final.pdf', 'description' => 'Final version']);
        $update->assertStatus(200);
        $this->seeInDatabase('documents', ['id' => $document['id'], 'name' => 'final.pdf']);

        $this->withHeaders($this->adminHeaders())->delete("api/v1/documents/{$document['id']}")->assertStatus(200);
        $this->dontSeeInDatabase('documents', ['id' => $document['id'], 'deleted_at' => null]);

        $this->withHeaders($this->adminHeaders())->post("api/v1/documents/{$document['id']}/restore")->assertStatus(200);
        $this->seeInDatabase('documents', ['id' => $document['id'], 'deleted_at' => null]);
    }

    public function testNonParticipantGetsNotFoundOnDocument(): void
    {
        $folderId  = $this->createFolder('Secret');
        $initiated = $this->initiateUpload($folderId, 'secret.pdf', 'application/pdf', 1);
        $this->putToS3($initiated['uploadUrl'], 'x', 'application/pdf');
        $document = json_decode($this->withHeaders($this->adminHeaders())->withBodyFormat('json')
            ->post('api/v1/documents/uploads/complete', [
                'folderId' => $folderId, 's3Key' => $initiated['s3Key'], 'name' => 'secret.pdf', 'checksumSha256' => hash('sha256', 'x'),
            ])->getJSON(), true)['data'];

        $this->withHeaders($this->employeeHeaders())->get("api/v1/documents/{$document['id']}")->assertStatus(404);
    }
}
