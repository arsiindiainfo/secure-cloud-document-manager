<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUsers extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
            CREATE TABLE users (
              id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              name            VARCHAR(120)     NOT NULL,
              email           VARCHAR(190)     NOT NULL,
              password_hash   VARCHAR(255)     NOT NULL,
              role            ENUM('ADMIN','MANAGER','EMPLOYEE') NOT NULL DEFAULT 'EMPLOYEE',
              status          ENUM('ACTIVE','DISABLED') NOT NULL DEFAULT 'ACTIVE',
              created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              deleted_at      DATETIME         NULL,
              UNIQUE KEY uq_users_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        SQL);
    }

    public function down(): void
    {
        $this->db->query('DROP TABLE IF EXISTS users;');
    }
}

