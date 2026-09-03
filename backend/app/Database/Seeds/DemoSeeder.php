<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Minimal seed — one user per role, so auth/role-gated routes are testable
 * immediately. Deliberately kept tiny and fast: this is also the seeder
 * ApiTestCase reseeds before every single test, so no S3/folder/document
 * data belongs here — see MeridianSeeder for the full portfolio dataset.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $password = password_hash('Passw0rd!', PASSWORD_BCRYPT);

        $this->db->table('users')->insertBatch([
            ['id' => 1, 'name' => 'Ava Admin', 'email' => 'admin@meridian.test', 'password_hash' => $password, 'role' => 'ADMIN', 'status' => 'ACTIVE'],
            ['id' => 2, 'name' => 'Mark Manager', 'email' => 'manager@meridian.test', 'password_hash' => $password, 'role' => 'MANAGER', 'status' => 'ACTIVE'],
            ['id' => 3, 'name' => 'Eve Employee', 'email' => 'employee@meridian.test', 'password_hash' => $password, 'role' => 'EMPLOYEE', 'status' => 'ACTIVE'],
        ]);

        $this->db->table('trash_retention_settings')->insert(['id' => 1, 'retention_days' => 30]);
    }
}
