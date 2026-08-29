<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAuditLogs extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
            CREATE TABLE audit_logs (
              id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              user_id       BIGINT UNSIGNED NULL,
              action        VARCHAR(60)      NOT NULL,
              entity_type   ENUM('DOCUMENT','FOLDER','USER','SHARE_LINK') NOT NULL,
              entity_id     BIGINT UNSIGNED NOT NULL,
              details       JSON            NULL,
              ip_address    VARCHAR(45)      NULL,
              created_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
              CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
              KEY idx_audit_entity (entity_type, entity_id),
              KEY idx_audit_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        SQL);
    }

    public function down(): void
    {
        $this->db->query('DROP TABLE IF EXISTS audit_logs;');
    }
}
