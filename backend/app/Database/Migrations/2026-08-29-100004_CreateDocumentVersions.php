<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDocumentVersions extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
            CREATE TABLE document_versions (
              id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              document_id       BIGINT UNSIGNED NOT NULL,
              version_no        INT UNSIGNED    NOT NULL,
              s3_bucket         VARCHAR(120)     NOT NULL,
              s3_key            VARCHAR(400)     NOT NULL,
              mime_type         VARCHAR(150)     NOT NULL,
              size_bytes        BIGINT UNSIGNED NOT NULL,
              checksum_sha256   CHAR(64)         NOT NULL,
              thumbnail_s3_key  VARCHAR(400)     NULL,
              is_current        TINYINT(1)       NOT NULL DEFAULT 0,
              uploaded_by       BIGINT UNSIGNED NOT NULL,
              uploaded_at       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
              CONSTRAINT fk_versions_document FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE RESTRICT,
              CONSTRAINT fk_versions_uploader FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE RESTRICT,
              UNIQUE KEY uq_document_version (document_id, version_no),
              KEY idx_versions_document_current (document_id, is_current)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        SQL);
    }

    public function down(): void
    {
        $this->db->query('DROP TABLE IF EXISTS document_versions;');
    }
}

