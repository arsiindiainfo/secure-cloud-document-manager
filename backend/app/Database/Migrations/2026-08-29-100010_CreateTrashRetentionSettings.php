<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTrashRetentionSettings extends Migration
{
    public function up(): void
    {
        // Single-row config table — how many days a soft-deleted item is
        // recoverable before the scheduled purge Lambda removes its S3
        // object (§7.1, §9.2, §28). The row is seeded by DemoSeeder.
        $this->db->query(<<<'SQL'
            CREATE TABLE trash_retention_settings (
              id              TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
              retention_days  INT UNSIGNED    NOT NULL DEFAULT 30,
              updated_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              CONSTRAINT ck_trash_retention_singleton CHECK (id = 1)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        SQL);
    }

    public function down(): void
    {
        $this->db->query('DROP TABLE IF EXISTS trash_retention_settings;');
    }
}
