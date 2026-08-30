<?php

namespace App\Services;

use App\Constants\FileTypes;
use App\Entities\Document;
use App\Exceptions\DocumentNotFoundException;
use App\Exceptions\FileTooLargeException;
use App\Exceptions\UnsupportedFileTypeException;
use App\Exceptions\ValidationException;
use App\Libraries\PermissionResolver;
use App\Libraries\S3Service;
use App\Models\AuditLogModel;
use App\Models\DocumentModel;
use App\Models\DocumentVersionModel;
use Config\Services;

/**
 * §17 — the three-step direct-to-S3 upload flow, versioning, and metadata.
 * authorize() mirrors FolderService::authorize() — the one code path that
 * can grant access to a document (§6.3).
 */
class DocumentService
{
    private readonly PermissionResolver $permissions;

    public function __construct(
        private readonly DocumentModel $documents = new DocumentModel(),
        private readonly DocumentVersionModel $versions = new DocumentVersionModel(),
        private readonly FolderService $folderService = new FolderService(),
        private readonly AuditLogModel $auditLog = new AuditLogModel(),
        private readonly S3Service $s3 = new S3Service(),
        ?PermissionResolver $permissions = null,
    ) {
        $this->permissions = $permissions ?? new PermissionResolver(db_connect());
    }

    public function authorize(int $documentId, string $required): Document
    {
        $document = $this->documents->find($documentId);
        if ($document === null) {
            throw new DocumentNotFoundException();
        }

        $auth = Services::authContext();

        if ($auth->isAdmin()) {
            $this->auditLog->record($auth->userId(), 'ADMIN_ACCESS_BYPASS', 'DOCUMENT', $documentId, ['requiredPermission' => $required]);

            return $document;
        }

        $permission = $this->permissions->documentPermission($auth->userId(), $documentId, $document->folder_id);
        if (! $this->permissions->meets($permission, $required)) {
            throw new DocumentNotFoundException();
        }

        return $document;
    }

    /**
     * §17's full detail response: base fields + current version summary +
     * the caller's own effective permission, so the frontend can decide
     * which actions to even show (§23 — hidden, not just disabled).
     *
     * @return array<string, mixed>
     */
    public function getDetail(int $documentId): array
    {
        $document = $this->authorize($documentId, 'VIEWER');
        $auth     = Services::authContext();

        $permission = $auth->isAdmin()
            ? 'OWNER'
            : ($this->permissions->documentPermission($auth->userId(), $documentId, $document->folder_id) ?? 'VIEWER');

        return [
            ...$document->toArray(),
            // Named distinctly from the plain `currentVersion` version
            // *number* already in toArray() — this is the version's own
            // detail (mime/size/thumbnail), not just its ordinal.
            'currentVersionDetail' => $this->versions->current($documentId),
            'effectivePermission'  => $permission,
        ];
    }

    /** @return array{uploadUrl: string, s3Key: string, expiresIn: int} */
    public function initiateUpload(int $folderId, string $fileName, string $mimeType, int $sizeBytes): array
    {
        $this->folderService->authorize($folderId, 'EDITOR');
        $safeName = $this->assertValidFile($fileName, $mimeType, $sizeBytes);

        $uploadToken = bin2hex(random_bytes(16));
        $s3Key       = "documents/{$folderId}/{$uploadToken}/{$safeName}";

        return $this->presignedUploadResult($s3Key, $mimeType);
    }

    /** @return array{uploadUrl: string, s3Key: string, expiresIn: int} */
    public function initiateVersion(int $documentId, string $fileName, string $mimeType, int $sizeBytes): array
    {
        $document = $this->authorize($documentId, 'EDITOR');
        $safeName = $this->assertValidFile($fileName, $mimeType, $sizeBytes);

        $uploadToken = bin2hex(random_bytes(16));
        $s3Key       = "documents/{$document->folder_id}/{$documentId}/{$uploadToken}/{$safeName}";

        return $this->presignedUploadResult($s3Key, $mimeType);
    }

    public function completeUpload(
        int $folderId,
        string $s3Key,
        string $name,
        ?string $description,
        ?string $tags,
        string $checksumSha256,
    ): Document {
        $this->folderService->authorize($folderId, 'EDITOR');
        $this->assertKeyBelongsToFolder($s3Key, $folderId);
        $head = $this->assertObjectUploaded($s3Key);

        $result = $this->documents->uploadCommit(
            $folderId,
            $name,
            $description,
            $tags,
            config('Aws')->documentsBucket,
            $s3Key,
            $this->mimeTypeFromKey($s3Key),
            $head['sizeBytes'],
            $checksumSha256,
            Services::authContext()->userId(),
        );

        /** @var Document $document */
        $document = $this->documents->find($result['documentId']);

        return $document;
    }

    /** @return array{versionNo: int} */
    public function completeVersion(int $documentId, string $s3Key, string $checksumSha256): array
    {
        $document = $this->authorize($documentId, 'EDITOR');
        $this->assertKeyBelongsToDocument($s3Key, $document->folder_id, $documentId);
        $head = $this->assertObjectUploaded($s3Key);

        $result = $this->documents->newVersion(
            $documentId,
            config('Aws')->documentsBucket,
            $s3Key,
            $this->mimeTypeFromKey($s3Key),
            $head['sizeBytes'],
            $checksumSha256,
            Services::authContext()->userId(),
        );

        return ['versionNo' => $result['versionNo']];
    }

    public function update(int $documentId, ?string $name, ?string $description, ?string $tags, ?int $folderId): Document
    {
        $document = $this->authorize($documentId, 'EDITOR');
        $targetFolderId = $folderId ?? $document->folder_id;

        if ($targetFolderId !== $document->folder_id) {
            $this->folderService->authorize($targetFolderId, 'EDITOR');
        }

        $this->documents->updateMetadata(
            $documentId,
            $name ?? $document->name,
            $description ?? $document->description,
            $tags ?? $document->tags,
            $targetFolderId,
            Services::authContext()->userId(),
        );

        /** @var Document $updated */
        $updated = $this->documents->find($documentId);

        return $updated;
    }

    public function softDelete(int $documentId): void
    {
        $this->authorize($documentId, 'OWNER');
        $this->documents->softDelete($documentId, Services::authContext()->userId());
    }

    public function restore(int $documentId): void
    {
        $this->authorize($documentId, 'OWNER');
        $this->documents->restore($documentId, Services::authContext()->userId());
    }

    /** @return list<\App\Entities\DocumentVersion> newest first */
    public function listVersions(int $documentId): array
    {
        $this->authorize($documentId, 'VIEWER');

        return $this->versions->allForDocument($documentId);
    }

    /** @return array{uploadUrl: string, s3Key: string, expiresIn: int} */
    private function presignedUploadResult(string $s3Key, string $mimeType): array
    {
        $config = config('Aws');

        return [
            'uploadUrl' => $this->s3->presignPut($s3Key, $mimeType),
            's3Key'     => $s3Key,
            'expiresIn' => $config->presignTtlSeconds,
        ];
    }

    /** @return string the sanitized file name (path-traversal characters stripped) */
    private function assertValidFile(string $fileName, string $mimeType, int $sizeBytes): string
    {
        if (! in_array($mimeType, FileTypes::ALLOWED_MIME_TYPES, true)) {
            throw new UnsupportedFileTypeException();
        }

        if ($sizeBytes > FileTypes::MAX_UPLOAD_SIZE_BYTES) {
            throw new FileTooLargeException();
        }

        // Strip path-traversal characters (§5) — basename() alone drops any
        // directory component; this further removes anything that isn't a
        // safe filename character.
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($fileName));
        $ext  = FileTypes::EXTENSION_BY_MIME_TYPE[$mimeType];

        return str_ends_with(strtolower($safe), '.' . $ext) ? $safe : "{$safe}.{$ext}";
    }

    private function assertKeyBelongsToFolder(string $s3Key, int $folderId): void
    {
        if (! str_starts_with($s3Key, "documents/{$folderId}/")) {
            throw new ValidationException([['field' => 's3Key', 'message' => 's3Key does not match an upload issued for this folder.']]);
        }
    }

    private function assertKeyBelongsToDocument(string $s3Key, int $folderId, int $documentId): void
    {
        if (! str_starts_with($s3Key, "documents/{$folderId}/{$documentId}/")) {
            throw new ValidationException([['field' => 's3Key', 'message' => 's3Key does not match an upload issued for this document.']]);
        }
    }

    /** @return array{sizeBytes: int, etag: string} */
    private function assertObjectUploaded(string $s3Key): array
    {
        $head = $this->s3->headObject($s3Key);
        if ($head === null) {
            throw new ValidationException([['field' => 's3Key', 'message' => 'No object was found at this key — did the upload complete?']]);
        }

        return $head;
    }

    private function mimeTypeFromKey(string $s3Key): string
    {
        $ext = strtolower((string) pathinfo($s3Key, PATHINFO_EXTENSION));
        $flipped = array_flip(FileTypes::EXTENSION_BY_MIME_TYPE);

        return $flipped[$ext] ?? 'application/octet-stream';
    }
}
