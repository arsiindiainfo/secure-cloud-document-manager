<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateShareLinks extends Migration
{
    public function up(): void
    {
        // `token` (43 chars) is the base64url encoding of 32 random bytes —
        // see sp_share_link_consume's `IN p_token CHAR(43)` signature (§8.3).
        $this->db->query(<<<'SQL'
            CREATE TABLE share_links (
              id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              document_id     BIGINT UNSIGNED NOT NULL,
              token           CHAR(43)         NOT NULL,
              permission      ENUM('VIEW','DOWNLOAD') NOT NULL DEFAULT 'DOWNLOAD',
              max_downloads   INT UNSIGNED    NULL,
              download_count  INT UNSIGNED    NOT NULL DEFAULT 0,
              expires_at      DATETIME         NOT NULL,
              revoked_at      DATETIME         NULL,
              created_by      BIGINT UNSIGNED NOT NULL,
              created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
              CONSTRAINT fk_sharelink_document FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
              CONSTRAINT fk_sharelink_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
              UNIQUE KEY uq_sharelink_token (token),
              KEY idx_sharelink_document (document_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        SQL);
    }

    public function down(): void
    {
        $this->db->query('DROP TABLE IF EXISTS share_links;');
    }
}

