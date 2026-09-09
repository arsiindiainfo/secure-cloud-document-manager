<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Services;

use App\Constants\SuperAdmin;
use App\Exceptions\RecaptchaFailedException;
use App\Libraries\EmailTemplate;
use App\Libraries\RecaptchaVerifier;
use App\Libraries\ResendMailer;
use App\Models\UserModel;

/**
 * Public self-registration (§15) — the second way to get an account,
 * alongside an ADMIN's invite (UsersService::invite). Every self-registered
 * account starts as role MANAGER and requires clicking the emailed
 * verification link before it can log in (AuthService checks this);
 * an ADMIN invite skips that step entirely since the admin already
 * vouched for the address.
 */
class RegistrationService
{
    public function __construct(
        private readonly UserModel $users = new UserModel(),
        private readonly RecaptchaVerifier $recaptcha = new RecaptchaVerifier(),
    ) {
    }

    public function register(string $name, string $email, string $password, ?string $recaptchaToken, ?string $ip): int
    {
        if (! $this->recaptcha->verify($recaptchaToken, $ip)) {
            throw new RecaptchaFailedException();
        }

        $token  = $this->urlSafeToken();
        $userId = $this->users->register($name, $email, password_hash($password, PASSWORD_BCRYPT), 'MANAGER', $token);

        $this->sendVerificationEmail($email, $name, $token);
        $this->sendAdminNotification($name, $email);

        return $userId;
    }

    public function verifyEmail(string $token): void
    {
        $this->users->verifyEmail($token);
    }

    /** 32 random bytes, base64url without padding — 43 chars, matching users.email_verification_token CHAR(43). */
    private function urlSafeToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function sendVerificationEmail(string $email, string $name, string $token): void
    {
        $verifyUrl = rtrim(config('App')->frontendUrl, '/') . '/verify-email/' . $token;
        $html      = EmailTemplate::renderVerification($name, $verifyUrl);

        try {
            if (! (new ResendMailer())->send($email, 'Verify your email — Secure Cloud Document Manager', $html)) {
                log_message('error', 'Failed to send verification email to ' . $email);
            }
        } catch (\Throwable $e) {
            log_message('error', 'Failed to send verification email: ' . $e->getMessage());
        }
    }

    private function sendAdminNotification(string $name, string $email): void
    {
        $body = '<p>A new account just registered on Secure Cloud Document Manager:</p>'
            . '<table role="presentation" cellpadding="0" cellspacing="0" style="margin: 16px 0; font-size: 14px;">'
            . '<tr><td style="padding: 4px 12px 4px 0; color: #64748b;">Name:&nbsp;</td>'
            . '<td style="padding: 4px 0; font-weight: 600; color: #0f172a;">' . esc($name, 'html') . '</td></tr>'
            . '<tr><td style="padding: 4px 12px 4px 0; color: #64748b;">Email:&nbsp;</td>'
            . '<td style="padding: 4px 0; font-weight: 600; color: #0f172a;">' . esc($email, 'html') . '</td></tr>'
            . '</table>';

        $html = EmailTemplate::render('New registration', $body);

        // Never blocks registration itself — this is a courtesy notice.
        try {
            if (! (new ResendMailer())->send(SuperAdmin::EMAIL, 'New registration — Secure Cloud Document Manager', $html)) {
                log_message('error', 'Failed to send registration notification for ' . $email);
            }
        } catch (\Throwable $e) {
            log_message('error', 'Failed to send registration notification: ' . $e->getMessage());
        }
    }
}
