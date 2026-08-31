<?php

namespace App\Models;

use App\Exceptions\DocumentNotFoundException;
use App\Exceptions\InternalErrorException;
use App\Exceptions\LastOwnerException;
use App\Exceptions\PermissionGrantNotFoundException;
use App\Exceptions\UserNotFoundException;
use App\Libraries\StoredProcedure;
use CodeIgniter\Model;

/**
 * §18 — direct document_permissions grants. Reads are plain joins (simple,
 * single-purpose); grant/revoke go through stored procedures because both
 * enforce a business rule that must not race (§5): grant validates the
 * document/user exist, revoke refuses to remove the last remaining OWNER.
 */
class DocumentPermissionModel extends Model
{
    protected $table = 'document_permissions';

    public function grant(int $documentId, int $userId, string $permission, int $grantedBy): void
    {
        $out = (new StoredProcedure($this->db))->call('sp_document_permission_grant', [
            $documentId, $userId, $permission, $grantedBy,
        ], ['p_status_code', 'p_message']);

        match ($out['p_status_code']) {
            'OK'                 => null,
            'DOCUMENT_NOT_FOUND' => throw new DocumentNotFoundException(),
            'USER_NOT_FOUND'     => throw new UserNotFoundException(),
            default              => throw new InternalErrorException($out['p_message'] ?? 'Failed to grant access.'),
        };
    }

    public function revoke(int $documentId, int $userId, int $revokedBy): void
    {
        $out = (new StoredProcedure($this->db))->call('sp_document_permission_revoke', [
            $documentId, $userId, $revokedBy,
        ], ['p_status_code', 'p_message']);

        match ($out['p_status_code']) {
            'OK'               => null,
            'GRANT_NOT_FOUND'  => throw new PermissionGrantNotFoundException(),
            'LAST_OWNER'       => throw new LastOwnerException(),
            default            => throw new InternalErrorException($out['p_message'] ?? 'Failed to revoke access.'),
        };
    }

    /** @return list<array{userId: int, name: string, email: string, permission: string, grantedAt: string}> */
    public function listForDocument(int $documentId): array
    {
        $rows = $this->db->table('document_permissions')
            ->select('document_permissions.user_id, users.name, users.email, document_permissions.permission, document_permissions.granted_at')
            ->join('users', 'users.id = document_permissions.user_id')
            ->where('document_permissions.document_id', $documentId)
            ->orderBy('document_permissions.granted_at', 'asc')
            ->get()->getResultArray();

        return array_map(static fn (array $row): array => [
            'userId'     => (int) $row['user_id'],
            'name'       => $row['name'],
            'email'      => $row['email'],
            'permission' => $row['permission'],
            'grantedAt'  => $row['granted_at'],
        ], $rows);
    }
}
