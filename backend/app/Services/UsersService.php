<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Services;

use App\Entities\User;
use App\Models\RefreshTokenModel;
use App\Models\UserModel;
use Config\Services;

/**
 * §15's user-management endpoints. No public self-registration — accounts
 * only come from an ADMIN invite, with a server-generated temporary
 * password the client never sees or chooses.
 */
class UsersService
{
    public function __construct(
        private readonly UserModel $users = new UserModel(),
        private readonly RefreshTokenModel $refreshTokens = new RefreshTokenModel(),
    ) {
    }

    public function me(): User
    {
        /** @var User $user */
        $user = $this->users->find(Services::authContext()->userId());

        return $user;
    }

    /** @return array{userId: int, temporaryPassword: string} */
    public function invite(string $name, string $email, string $role, int $invitedBy): array
    {
        $temporaryPassword = bin2hex(random_bytes(8));
        $userId = $this->users->invite($name, $email, $role, password_hash($temporaryPassword, PASSWORD_BCRYPT), $invitedBy);

        $this->sendInviteEmail($email, $name, $temporaryPassword);

        return ['userId' => $userId, 'temporaryPassword' => $temporaryPassword];
    }

    /**
     * @return array{items: list<User>, total: int}
     */
    public function list(int $page, int $limit): array
    {
        $total = $this->users->countAllResults();
        $items = $this->users->orderBy('created_at', 'desc')->findAll($limit, ($page - 1) * $limit);

        return ['items' => $items, 'total' => $total];
    }

    public function updateRoleStatus(int $userId, ?string $role, ?string $status): User
    {
        $fields = array_filter(['role' => $role, 'status' => $status], static fn ($v) => $v !== null);
        $this->users->update($userId, $fields);

        if ($status === 'DISABLED') {
            $this->refreshTokens->revokeAllForUser($userId);
        }

        /** @var User $user */
        $user = $this->users->find($userId);

        return $user;
    }

    private function sendInviteEmail(string $email, string $name, string $temporaryPassword): void
    {
        $emailService = Services::email();
        $emailService->setTo($email);
        $emailService->setSubject('Your Secure Cloud Document Manager account');
        $emailService->setMessage(
            "Hi {$name},\n\nAn account has been created for you.\n\n" .
            "Email: {$email}\nTemporary password: {$temporaryPassword}\n\n" .
            "Please log in and this is a demo — change it via a real reset flow in a production build."
        );

        // Delivery failure should not block the invite itself succeeding —
        // the ADMIN can still relay the temporary password out-of-band.
        try {
            $emailService->send();
        } catch (\Throwable $e) {
            log_message('error', 'Failed to send invite email: ' . $e->getMessage());
        }
    }
}

