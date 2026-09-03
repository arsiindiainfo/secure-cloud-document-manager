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
 * @property string      $name
 * @property string      $email
 * @property string      $password_hash
 * @property string      $role
 * @property string      $status
 * @property string|null $created_at
 * @property string|null $updated_at
 */
class User extends Entity
{
    protected $casts = [
        'id' => 'integer',
    ];

    /**
     * Field minimalism (§12) — the password hash never leaves this class.
     *
     * @return array{id: int, name: string, email: string, role: string, status: string}
     */
    public function toArray(bool $onlyChanged = false, bool $cast = true, bool $recursive = false): array
    {
        return [
            'id'     => $this->id,
            'name'   => $this->name,
            'email'  => $this->email,
            'role'   => $this->role,
            'status' => $this->status,
        ];
    }
}

