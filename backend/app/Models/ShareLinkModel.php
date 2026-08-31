<?php

namespace App\Models;

use App\Entities\ShareLink;
use App\Exceptions\DocumentNotFoundException;
use App\Exceptions\InternalErrorException;
use App\Libraries\StoredProcedure;
use CodeIgniter\Model;

class ShareLinkModel extends Model
{
    protected $table         = 'share_links';
    protected $primaryKey    = 'id';
    protected $returnType    = ShareLink::class;
    protected $allowedFields = ['document_id', 'token', 'permission', 'max_downloads', 'expires_at', 'created_by', 'revoked_at'];
    protected $useTimestamps = false;

    /** @return array{id: int, token: string} */
    public function create(int $documentId, string $permission, string $expiresAt, ?int $maxDownloads, int $createdBy): array
    {
        $out = (new StoredProcedure($this->db))->call('sp_share_link_create', [
            $documentId, $permission, $expiresAt, $maxDownloads, $createdBy,
        ], ['p_share_link_id', 'p_token', 'p_status_code', 'p_message']);

        return match ($out['p_status_code']) {
            'OK'                 => ['id' => (int) $out['p_share_link_id'], 'token' => (string) $out['p_token']],
            'DOCUMENT_NOT_FOUND' => throw new DocumentNotFoundException(),
            default              => throw new InternalErrorException($out['p_message'] ?? 'Failed to create share link.'),
        };
    }

    /** @return list<ShareLink> newest first */
    public function listForDocument(int $documentId): array
    {
        return $this->where('document_id', $documentId)->orderBy('created_at', 'desc')->findAll();
    }

    public function revoke(int $id): void
    {
        $this->update($id, ['revoked_at' => date('Y-m-d H:i:s')]);
    }
}
