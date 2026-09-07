<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Services;

use App\Constants\SuperAdmin;
use App\Entities\User;
use App\Exceptions\ForbiddenActionException;
use App\Exceptions\IncorrectPasswordException;
use App\Exceptions\UserHasContentException;
use App\Exceptions\UserNotFoundException;
use App\Libraries\EmailTemplate;
use App\Libraries\SesMailer;
use App\Models\AuditLogModel;
use App\Models\RefreshTokenModel;
use App\Models\UserModel;
use CodeIgniter\Database\Exceptions\DatabaseException;
use Config\Services;

/**
 * §15's ADMIN-facing user-management endpoints (invite/list/update/delete/
 * change-own-password). Self-registration is a separate public flow — see
 * RegistrationService.
 */
class UsersService
{
    public function __construct(
        private readonly UserModel $users = new UserModel(),
        private readonly RefreshTokenModel $refreshTokens = new RefreshTokenModel(),
        private readonly AuditLogModel $auditLog = new AuditLogModel(),
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

    /**
     * §22.5 — lightweight lookup for the Share dialog's "invite a person"
     * autocomplete. Deliberately not the full ADMIN user-management read
     * (§15) — any authenticated user may call this, so it returns only
     * id/name/email, never role/status, and never the caller themselves.
     *
     * @return list<array{id: int, name: string, email: string}>
     */
    public function search(string $query, int $excludeUserId): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return [];
        }

        $rows = $this->users
            ->select('id, name, email')
            ->groupStart()
                ->like('name', $query)
                ->orLike('email', $query)
            ->groupEnd()
            ->where('status', 'ACTIVE')
            ->where('id !=', $excludeUserId)
            ->orderBy('name', 'asc')
            ->asArray()
            ->findAll(8);

        return array_map(static fn (array $row): array => [
            'id'    => (int) $row['id'],
            'name'  => $row['name'],
            'email' => $row['email'],
        ], $rows);
    }

    public function updateRoleStatus(int $userId, ?string $role, ?string $status): User
    {
        $user = $this->users->find($userId);
        if ($user !== null && strcasecmp($user->email, SuperAdmin::EMAIL) === 0) {
            throw new ForbiddenActionException('This account\'s role and status cannot be changed.');
        }

        $fields = array_filter(['role' => $role, 'status' => $status], static fn ($v) => $v !== null);
        $this->users->update($userId, $fields);

        if ($status === 'DISABLED') {
            $this->refreshTokens->revokeAllForUser($userId);
        }

        /** @var User $user */
        $user = $this->users->find($userId);

        return $user;
    }

    /**
     * A real, permanent delete — not a soft delete. folders.created_by,
     * documents.created_by, document_versions.uploaded_by, and
     * share_links.created_by are all ON DELETE RESTRICT, so this fails
     * with UserHasContentException if the user actually created anything
     * that still exists; there's no ownership to reassign, so that's
     * surfaced rather than silently downgraded to a soft delete.
     */
    public function delete(int $userId, int $requestedBy): void
    {
        if ($userId === $requestedBy) {
            throw new ForbiddenActionException('You cannot delete your own account.');
        }

        $user = $this->users->find($userId);
        if ($user === null) {
            throw new UserNotFoundException('No user was found with this id.');
        }

        if (strcasecmp($user->email, SuperAdmin::EMAIL) === 0) {
            throw new ForbiddenActionException('This account cannot be deleted.');
        }

        try {
            // refresh_tokens.user_id is ON DELETE CASCADE — every session
            // is gone the instant this succeeds, no separate revoke needed.
            $this->users->delete($userId, true);
        } catch (DatabaseException $e) {
            throw new UserHasContentException();
        }

        // entity_id, not a real FK — this row outlives the user it names.
        $this->auditLog->record($requestedBy, 'USER_DELETED', 'USER', $userId, ['email' => $user->email]);
    }

    public function changePassword(int $userId, string $currentPassword, string $newPassword): void
    {
        /** @var User $user */
        $user = $this->users->find($userId);

        if (! password_verify($currentPassword, $user->password_hash)) {
            throw new IncorrectPasswordException();
        }

        $this->users->update($userId, ['password_hash' => password_hash($newPassword, PASSWORD_BCRYPT)]);
        // Every other session (this device's included) needs to re-authenticate
        // with the new password — same as a DISABLE, minus the status flip.
        $this->refreshTokens->revokeAllForUser($userId);
    }

    private function sendInviteEmail(string $email, string $name, string $temporaryPassword): void
    {
        $safeName = esc($name, 'html');
        $body     = "<p>Hi {$safeName},</p>"
            . '<p>An account has been created for you on Secure Cloud Document Manager.</p>'
            . '<table role="presentation" cellpadding="0" cellspacing="0" style="margin: 16px 0; font-size: 14px;">'
            . '<tr><td style="padding: 4px 12px 4px 0; color: #64748b;">Email</td>'
            . '<td style="padding: 4px 0; font-weight: 600; color: #0f172a;">' . esc($email, 'html') . '</td></tr>'
            . '<tr><td style="padding: 4px 12px 4px 0; color: #64748b;">Temporary password</td>'
            . '<td style="padding: 4px 0; font-weight: 600; color: #0f172a;">' . esc($temporaryPassword, 'html') . '</td></tr>'
            . '</table>'
            . '<p style="color: #94a3b8;">This is a demo account — change this password via a real reset flow in a production build.</p>';

        $html = EmailTemplate::render('Welcome to Secure Cloud Document Manager', $body, [
            'label' => 'Sign in',
            'url'   => rtrim(config('App')->frontendUrl, '/') . '/login',
        ]);

        // Delivery failure should not block the invite itself succeeding —
        // the ADMIN can still relay the temporary password out-of-band.
        try {
            if (! (new SesMailer())->send($email, 'Your Secure Cloud Document Manager account', $html)) {
                log_message('error', 'Failed to send invite email to ' . $email);
            }
        } catch (\Throwable $e) {
            log_message('error', 'Failed to send invite email: ' . $e->getMessage());
        }
    }
}

