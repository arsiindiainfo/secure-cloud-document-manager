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
 * Self-registration needs a verified-email gate before login; admin-invited
 * accounts don't (the admin already vouched for them by inviting that exact
 * address) — so every existing row is backfilled as already verified here,
 * and sp_user_invite is updated to do the same for every future invite.
 * Only sp_user_register ever leaves email_verified_at NULL.
 */
class AddEmailVerificationToUsers extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
            ALTER TABLE users
              ADD COLUMN email_verified_at DATETIME NULL AFTER status,
              ADD COLUMN email_verification_token CHAR(43) NULL AFTER email_verified_at,
              ADD UNIQUE KEY uq_users_verification_token (email_verification_token)
        SQL);

        $this->db->query('UPDATE users SET email_verified_at = created_at WHERE email_verified_at IS NULL');
    }

    public function down(): void
    {
        $this->db->query(<<<'SQL'
            ALTER TABLE users
              DROP KEY uq_users_verification_token,
              DROP COLUMN email_verification_token,
              DROP COLUMN email_verified_at
        SQL);
    }
}
