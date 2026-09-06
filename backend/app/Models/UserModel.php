<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Models;

use App\Entities\User;
use App\Exceptions\DuplicateNameException;
use App\Exceptions\InternalErrorException;
use App\Exceptions\InvalidVerificationTokenException;
use App\Libraries\StoredProcedure;
use CodeIgniter\Model;

/**
 * Reads use the query builder directly (simple, single-table lookups);
 * writes that need business-rule validation go through stored procedures
 * (§5, §8.4) via StoredProcedure.
 */
class UserModel extends Model
{
    protected $table         = 'users';
    protected $primaryKey    = 'id';
    protected $returnType    = User::class;
    protected $useSoftDeletes = true;
    protected $allowedFields = ['name', 'email', 'password_hash', 'role', 'status'];
    protected $useTimestamps = true;

    /** @return array{userId: int, passwordHash: string, role: string, status: string, emailVerified: bool}|null */
    public function findForAuthentication(string $email): ?array
    {
        $out = (new StoredProcedure($this->db))->call('sp_user_authenticate', [$email], [
            'p_user_id', 'p_password_hash', 'p_role', 'p_status', 'p_email_verified', 'p_status_code', 'p_message',
        ]);

        if ($out['p_status_code'] !== 'OK') {
            return null;
        }

        return [
            'userId'       => (int) $out['p_user_id'],
            'passwordHash' => (string) $out['p_password_hash'],
            'role'         => (string) $out['p_role'],
            'status'       => (string) $out['p_status'],
            'emailVerified' => (bool) $out['p_email_verified'],
        ];
    }

    public function invite(string $name, string $email, string $role, string $passwordHash, int $invitedBy): int
    {
        $out = (new StoredProcedure($this->db))->call('sp_user_invite', [
            $name, $email, $role, $passwordHash, $invitedBy,
        ], ['p_user_id', 'p_status_code', 'p_message']);

        return match ($out['p_status_code']) {
            'OK'             => (int) $out['p_user_id'],
            'DUPLICATE_NAME' => throw new DuplicateNameException('This email is already registered.'),
            default          => throw new InternalErrorException($out['p_message'] ?? 'Failed to invite user.'),
        };
    }

    /** Self-service registration — email_verified_at stays NULL until verifyEmail(). */
    public function register(string $name, string $email, string $passwordHash, string $role, string $verificationToken): int
    {
        $out = (new StoredProcedure($this->db))->call('sp_user_register', [
            $name, $email, $passwordHash, $role, $verificationToken,
        ], ['p_user_id', 'p_status_code', 'p_message']);

        return match ($out['p_status_code']) {
            'OK'             => (int) $out['p_user_id'],
            'DUPLICATE_NAME' => throw new DuplicateNameException('This email is already registered.'),
            default          => throw new InternalErrorException($out['p_message'] ?? 'Failed to register.'),
        };
    }

    /** @return int the newly-verified user's id */
    public function verifyEmail(string $token): int
    {
        $out = (new StoredProcedure($this->db))->call('sp_user_verify_email', [$token], [
            'p_user_id', 'p_status_code', 'p_message',
        ]);

        return match ($out['p_status_code']) {
            'OK'             => (int) $out['p_user_id'],
            'INVALID_TOKEN'  => throw new InvalidVerificationTokenException(),
            default          => throw new InternalErrorException($out['p_message'] ?? 'Failed to verify email.'),
        };
    }
}

