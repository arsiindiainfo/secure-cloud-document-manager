<?php

namespace App\Entities;

use CodeIgniter\Entity\Entity;

/**
 * @property int         $id
 * @property int         $document_id
 * @property string      $token
 * @property string      $permission
 * @property int|null    $max_downloads
 * @property int         $download_count
 * @property string      $expires_at
 * @property string|null $revoked_at
 * @property int         $created_by
 * @property string|null $created_at
 */
class ShareLink extends Entity
{
    protected $casts = [
        'id'             => 'integer',
        'document_id'    => 'integer',
        'max_downloads'  => '?integer',
        'download_count' => 'integer',
        'created_by'     => 'integer',
    ];

    /** The raw token is never re-exposed once created — see SharingService::createShareLink(). */
    public function toArray(bool $onlyChanged = false, bool $cast = true, bool $recursive = false): array
    {
        return [
            'id'            => $this->id,
            'documentId'    => $this->document_id,
            'permission'    => $this->permission,
            'maxDownloads'  => $this->max_downloads,
            'downloadCount' => $this->download_count,
            'expiresAt'     => $this->expires_at,
            'revokedAt'     => $this->revoked_at,
            'createdBy'     => $this->created_by,
            'createdAt'     => $this->created_at,
        ];
    }
}
