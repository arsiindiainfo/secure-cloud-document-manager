<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDocuments extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
            CREATE TABLE documents (
              id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              folder_id       BIGINT UNSIGNED NOT NULL,
              name            VARCHAR(200)     NOT NULL,
              description     VARCHAR(500)     NULL,
              tags            VARCHAR(255)     NULL,
              current_version INT UNSIGNED    NOT NULL DEFAULT 0,
              created_by      BIGINT UNSIGNED NOT NULL,
              created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              deleted_at      DATETIME         NULL,
              CONSTRAINT fk_documents_folder FOREIGN KEY (folder_id) REFERENCES folders(id) ON DELETE RESTRICT,
              CONSTRAINT fk_documents_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
              KEY idx_documents_folder (folder_id),
              FULLTEXT KEY ftx_documents_search (name, description, tags)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        SQL);
    }

    public function down(): void
    {
        $this->db->query('DROP TABLE IF EXISTS documents;');
    }
}

