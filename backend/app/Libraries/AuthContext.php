<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Libraries;

/**
 * Request-scoped holder for "who is making this call", populated by
 * JwtAuthFilter after verifying the access token. Controllers/Services read
 * the caller here instead of re-decoding the JWT — there is exactly one
 * place a request's identity is established (§6.3's "one code path" idea
 * applied to authentication, not just authorization).
 */
class AuthContext
{
    private ?int $userId    = null;
    private ?string $role   = null;
    private bool $authenticated = false;

    public function setUser(int $userId, string $role): void
    {
        $this->userId        = $userId;
        $this->role          = $role;
        $this->authenticated = true;
    }

    public function isAuthenticated(): bool
    {
        return $this->authenticated;
    }

    public function userId(): int
    {
        if ($this->userId === null) {
            throw new \RuntimeException('AuthContext accessed before a user was set.');
        }

        return $this->userId;
    }

    public function role(): string
    {
        if ($this->role === null) {
            throw new \RuntimeException('AuthContext accessed before a user was set.');
        }

        return $this->role;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'ADMIN';
    }
}

