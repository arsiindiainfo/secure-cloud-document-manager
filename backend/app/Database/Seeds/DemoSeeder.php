<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Minimal seed for Phase 0 — one user per role, so auth/role-gated routes
 * are testable immediately. The full "Meridian Consulting Group" dataset
 * (folders, synthetic documents) is added in Phase 6 (§27, §32).
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $password = password_hash('Passw0rd!', PASSWORD_BCRYPT);

        $this->db->table('users')->insertBatch([
            ['name' => 'Ava Admin', 'email' => 'admin@meridian.test', 'password_hash' => $password, 'role' => 'ADMIN', 'status' => 'ACTIVE'],
            ['name' => 'Mark Manager', 'email' => 'manager@meridian.test', 'password_hash' => $password, 'role' => 'MANAGER', 'status' => 'ACTIVE'],
            ['name' => 'Eve Employee', 'email' => 'employee@meridian.test', 'password_hash' => $password, 'role' => 'EMPLOYEE', 'status' => 'ACTIVE'],
        ]);

        $this->db->table('trash_retention_settings')->insert(['id' => 1, 'retention_days' => 30]);
    }
}
