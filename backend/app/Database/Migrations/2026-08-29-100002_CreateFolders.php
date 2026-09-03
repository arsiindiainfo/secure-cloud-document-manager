<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateFolders extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
            CREATE TABLE folders (
              id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              parent_folder_id  BIGINT UNSIGNED NULL,
              name              VARCHAR(180)     NOT NULL,
              created_by        BIGINT UNSIGNED NOT NULL,
              created_at        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              deleted_at        DATETIME         NULL,
              CONSTRAINT fk_folders_parent FOREIGN KEY (parent_folder_id) REFERENCES folders(id) ON DELETE RESTRICT,
              CONSTRAINT fk_folders_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
              UNIQUE KEY uq_folder_sibling_name (parent_folder_id, name),
              KEY idx_folders_parent (parent_folder_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        SQL);
    }

    public function down(): void
    {
        $this->db->query('DROP TABLE IF EXISTS folders;');
    }
}

