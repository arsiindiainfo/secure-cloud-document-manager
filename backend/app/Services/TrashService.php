<?php

namespace App\Services;

use App\Entities\Document;
use App\Entities\Folder;
use App\Models\DocumentModel;
use App\Models\FolderModel;
use Config\Services;

/**
 * §22.8 — soft-deleted folders/documents the caller owns, with days
 * remaining before the (never-run-by-this-app) scheduled purge Lambda would
 * remove the S3 object (§9.2, §28). Read-only — restore/delete themselves
 * stay on FolderService/DocumentService, which already own that authorization.
 */
class TrashService
{
    public function __construct(
        private readonly FolderModel $folders = new FolderModel(),
        private readonly DocumentModel $documents = new DocumentModel(),
    ) {
    }

    /** @return array{folders: list<array<string, mixed>>, documents: list<array<string, mixed>>} */
    public function list(): array
    {
        $auth           = Services::authContext();
        $retentionDays  = $this->retentionDays();

        $folders   = $auth->isAdmin() ? $this->folders->allTrashed() : $this->folders->trashedOwnedBy($auth->userId());
        $documents = $auth->isAdmin() ? $this->documents->allTrashed() : $this->documents->trashedOwnedBy($auth->userId());

        return [
            'folders'   => array_map(fn (Folder $folder) => $this->withDaysRemaining($folder->toArray(), $folder->deleted_at, $retentionDays), $folders),
            'documents' => array_map(fn (Document $document) => $this->withDaysRemaining($document->toArray(), $document->deleted_at, $retentionDays), $documents),
        ];
    }

    private function retentionDays(): int
    {
        $row = db_connect()->table('trash_retention_settings')->select('retention_days')->get()->getRowArray();

        return $row !== null ? (int) $row['retention_days'] : 30;
    }

    /**
     * @param array<string, mixed> $item
     *
     * @return array<string, mixed>
     */
    private function withDaysRemaining(array $item, ?string $deletedAt, int $retentionDays): array
    {
        $daysSinceDeleted = $deletedAt !== null ? (int) floor((time() - strtotime($deletedAt)) / 86400) : 0;

        return [...$item, 'daysRemaining' => max(0, $retentionDays - $daysSinceDeleted)];
    }
}
