<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Entities;

use CodeIgniter\Entity\Entity;

/**
 * @property int         $id
 * @property int|null    $parent_folder_id
 * @property string      $name
 * @property int         $created_by
 * @property string|null $created_at
 * @property string|null $updated_at
 * @property string|null $deleted_at
 */
class Folder extends Entity
{
    protected $casts = [
        'id'                => 'integer',
        'parent_folder_id'  => '?integer',
        'created_by'        => 'integer',
    ];

    /** Field minimalism (§12) — only what the Folder Browser screen renders. */
    public function toArray(bool $onlyChanged = false, bool $cast = true, bool $recursive = false): array
    {
        return [
            'id'             => $this->id,
            'parentFolderId' => $this->parent_folder_id,
            'name'           => $this->name,
            'createdBy'      => $this->created_by,
            'createdAt'      => $this->created_at,
            'updatedAt'      => $this->updated_at,
            'deletedAt'      => $this->deleted_at,
        ];
    }
}

