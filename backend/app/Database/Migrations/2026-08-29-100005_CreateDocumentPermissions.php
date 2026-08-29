<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDocumentPermissions extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
            CREATE TABLE document_permissions (
              id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              folder_id      BIGINT UNSIGNED NULL,
              document_id    BIGINT UNSIGNED NULL,
              user_id        BIGINT UNSIGNED NOT NULL,
              permission     ENUM('VIEWER','EDITOR','OWNER') NOT NULL,
              granted_by     BIGINT UNSIGNED NOT NULL,
              granted_at     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
              CONSTRAINT ck_permission_target CHECK (
                (folder_id IS NOT NULL AND document_id IS NULL) OR (folder_id IS NULL AND document_id IS NOT NULL)
              ),
              CONSTRAINT fk_perm_folder FOREIGN KEY (folder_id) REFERENCES folders(id) ON DELETE CASCADE,
              CONSTRAINT fk_perm_document FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
              CONSTRAINT fk_perm_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
              CONSTRAINT fk_perm_granter FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE RESTRICT,
              UNIQUE KEY uq_perm_folder_user (folder_id, user_id),
              UNIQUE KEY uq_perm_document_user (document_id, user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        SQL);
    }

    public function down(): void
    {
        $this->db->query('DROP TABLE IF EXISTS document_permissions;');
    }
}
