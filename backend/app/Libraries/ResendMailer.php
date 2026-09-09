<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Libraries;

use Config\Email as EmailConfig;
use Config\Resend as ResendConfig;
use Config\Services;

/**
 * Sends transactional email via Resend's HTTP API in production, so mail
 * goes out as noreply@arsiindiainfo.com — a domain verified with SPF/DKIM
 * in Resend, with no per-recipient sandbox restriction like AWS SES and
 * none of the phishing-signal risk of relaying through a personal Gmail
 * account. Local/dev keeps using CI4's native SMTP service against
 * Mailhog (§ backend-setup.md) — it must never reach the real API.
 */
class ResendMailer
{
    public function __construct(
        private readonly EmailConfig $emailConfig = new EmailConfig(),
        private readonly ResendConfig $resendConfig = new ResendConfig(),
    ) {
    }

    public function send(string $to, string $subject, string $html): bool
    {
        if (ENVIRONMENT !== 'production') {
            return $this->sendViaMailhog($to, $subject, $html);
        }

        return $this->sendViaResend($to, $subject, $html);
    }

    private function sendViaMailhog(string $to, string $subject, string $html): bool
    {
        $emailService = Services::email();
        $emailService->setTo($to);
        $emailService->setSubject($subject);
        $emailService->setMailType('html');
        $emailService->setMessage($html);

        if (! $emailService->send()) {
            log_message('error', 'Local mail send failed: ' . $emailService->printDebugger(['headers']));

            return false;
        }

        return true;
    }

    private function sendViaResend(string $to, string $subject, string $html): bool
    {
        if ($this->resendConfig->apiKey === '') {
            log_message('error', 'Resend send skipped: resend.apiKey is not configured.');

            return false;
        }

        $payload = [
            'from'    => "{$this->emailConfig->fromName} <{$this->emailConfig->fromEmail}>",
            'to'      => [$to],
            'subject' => $subject,
            'html'    => $html,
        ];

        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->resendConfig->apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
        ]);

        $response  = curl_exec($ch);
        $status    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $status < 200 || $status >= 300) {
            $reason = $curlError !== '' ? $curlError : $response;
            log_message('error', "Resend send failed (HTTP {$status}): {$reason}");

            return false;
        }

        return true;
    }
}
