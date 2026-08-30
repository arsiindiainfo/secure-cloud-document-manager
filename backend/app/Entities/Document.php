<?php

namespace App\Entities;

use CodeIgniter\Entity\Entity;

/**
 * @property int         $id
 * @property int         $folder_id
 * @property string      $name
 * @property string|null $description
 * @property string|null $tags
 * @property int         $current_version
 * @property int         $created_by
 * @property string|null $created_at
 * @property string|null $updated_at
 * @property string|null $deleted_at
 */
class Document extends Entity
{
    protected $casts = [
        'id'              => 'integer',
        'folder_id'       => 'integer',
        'current_version' => 'integer',
        'created_by'      => 'integer',
    ];

    /**
     * @return list<string>
     */
    public function tagList(): array
    {
        return $this->tags === null || $this->tags === ''
            ? []
            : array_map('trim', explode(',', $this->tags));
    }

    /** Field minimalism (§12) — base fields only; §17's detail endpoint layers version/permission on top. */
    public function toArray(bool $onlyChanged = false, bool $cast = true, bool $recursive = false): array
    {
        return [
            'id'             => $this->id,
            'folderId'       => $this->folder_id,
            'name'           => $this->name,
            'description'    => $this->description,
            'tags'           => $this->tagList(),
            'currentVersion' => $this->current_version,
            'createdBy'      => $this->created_by,
            'createdAt'      => $this->created_at,
            'updatedAt'      => $this->updated_at,
            'deletedAt'      => $this->deleted_at,
        ];
    }
}
