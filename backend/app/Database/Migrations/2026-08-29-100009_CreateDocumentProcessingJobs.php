<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDocumentProcessingJobs extends Migration
{
    public function up(): void
    {
        // One row per uploaded version — the async pipeline's status board (§9.3).
        // scan_result is the documented virus-scan stub (§2): it logs a result,
        // it does not integrate a real scanning engine.
        $this->db->query(<<<'SQL'
            CREATE TABLE document_processing_jobs (
              id                    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              document_version_id   BIGINT UNSIGNED NOT NULL,
              status                ENUM('PENDING','PROCESSING','COMPLETED','FAILED') NOT NULL DEFAULT 'PENDING',
              scan_result           ENUM('CLEAN','FLAGGED') NULL,
              metadata              JSON            NULL,
              error_message         VARCHAR(500)     NULL,
              created_at            DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at            DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              CONSTRAINT fk_processing_version FOREIGN KEY (document_version_id) REFERENCES document_versions(id) ON DELETE CASCADE,
              UNIQUE KEY uq_processing_version (document_version_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        SQL);
    }

    public function down(): void
    {
        $this->db->query('DROP TABLE IF EXISTS document_processing_jobs;');
    }
}

