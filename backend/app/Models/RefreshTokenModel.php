<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Plain single-table model — refresh token rotation has no cross-table
 * business rules, so it doesn't need a stored procedure (§8 is for
 * multi-table/race-sensitive writes; this isn't one).
 */
class RefreshTokenModel extends Model
{
    protected $table         = 'refresh_tokens';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['user_id', 'token_hash', 'expires_at', 'revoked_at'];
    protected $useTimestamps = false;

    public function issue(int $userId, string $token, int $ttlSeconds): void
    {
        $this->insert([
            'user_id'    => $userId,
            'token_hash' => hash('sha256', $token),
            'expires_at' => date('Y-m-d H:i:s', time() + $ttlSeconds),
        ]);
    }

    /** @return array{id: int, userId: int}|null valid + unrevoked + unexpired */
    public function findActiveByToken(string $token): ?array
    {
        $row = $this->builder()
            ->where('token_hash', hash('sha256', $token))
            ->where('revoked_at', null)
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->get()
            ->getRowArray();

        return $row === null ? null : ['id' => (int) $row['id'], 'userId' => (int) $row['user_id']];
    }

    public function revoke(int $id): void
    {
        $this->update($id, ['revoked_at' => date('Y-m-d H:i:s')]);
    }

    public function revokeAllForUser(int $userId): void
    {
        $this->where('user_id', $userId)->where('revoked_at', null)
            ->set('revoked_at', date('Y-m-d H:i:s'))->update();
    }
}
