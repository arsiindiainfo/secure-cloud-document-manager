<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Loads every CREATE PROCEDURE statement from app/Database/Procedures/*.sql
 * (§8). Each file holds exactly one statement — no DELIMITER directives,
 * since those are a mysql-CLI-only convention; sent over the mysqli
 * protocol a CREATE PROCEDURE...BEGIN...END body is already one statement.
 */
class LoadStoredProcedures extends Migration
{
    public function up(): void
    {
        foreach ($this->procedureFiles() as $path) {
            $name = pathinfo($path, PATHINFO_FILENAME);
            $this->db->query("DROP PROCEDURE IF EXISTS `{$name}`");
            $this->db->query(file_get_contents($path));
        }
    }

    public function down(): void
    {
        foreach ($this->procedureFiles() as $path) {
            $name = pathinfo($path, PATHINFO_FILENAME);
            $this->db->query("DROP PROCEDURE IF EXISTS `{$name}`");
        }
    }

    /** @return list<string> */
    private function procedureFiles(): array
    {
        $files = glob(APPPATH . 'Database/Procedures/*.sql') ?: [];
        sort($files);

        return $files;
    }
}

