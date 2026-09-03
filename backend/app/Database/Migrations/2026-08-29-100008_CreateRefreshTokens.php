<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateRefreshTokens extends Migration
{
    public function up(): void
    {
        // Stores a hash of the refresh token, never the token itself (§5) —
        // rotation replaces a row's hash and marks the prior one revoked.
        $this->db->query(<<<'SQL'
            CREATE TABLE refresh_tokens (
              id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              user_id       BIGINT UNSIGNED NOT NULL,
              token_hash    CHAR(64)         NOT NULL,
              expires_at    DATETIME         NOT NULL,
              revoked_at    DATETIME         NULL,
              created_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
              CONSTRAINT fk_refresh_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
              UNIQUE KEY uq_refresh_token_hash (token_hash),
              KEY idx_refresh_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        SQL);
    }

    public function down(): void
    {
        $this->db->query('DROP TABLE IF EXISTS refresh_tokens;');
    }
}

