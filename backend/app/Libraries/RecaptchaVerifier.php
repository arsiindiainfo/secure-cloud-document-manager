<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Libraries;

use Config\Recaptcha as RecaptchaConfig;
use Throwable;

/**
 * Verifies a Google reCAPTCHA v2 response token against Google's
 * siteverify endpoint (§15 — bot mitigation on /auth/login, on top of the
 * existing per-IP rate limit). Skipped entirely in the `testing`
 * environment: PHPUnit can't solve a real captcha, and there is no value
 * in this making a real network call to Google on every test run.
 *
 * Uses PHP's curl extension directly rather than CodeIgniter's
 * `Services::curlrequest()` wrapper: on demo2, every request sent through
 * that wrapper was rejected by Google with `invalid-input-response` for an
 * otherwise-valid, freshly-solved token, while an identical request built
 * with raw curl_exec() against the same secret/token succeeded every time.
 * The exact option this framework wrapper sets that Google's siteverify
 * endpoint objects to was never pinned down; raw curl sidesteps it.
 */
class RecaptchaVerifier
{
    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    public function __construct(private readonly RecaptchaConfig $config = new RecaptchaConfig())
    {
    }

    public function verify(?string $token, ?string $remoteIp): bool
    {
        if (ENVIRONMENT === 'testing') {
            return true;
        }

        if ($token === null || $token === '') {
            return false;
        }

        try {
            $postFields = http_build_query(array_filter([
                'secret'   => $this->config->secretKey,
                'response' => $token,
                'remoteip' => $remoteIp,
            ]));

            $ch = curl_init(self::VERIFY_URL);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $postFields,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
            ]);
            $body = curl_exec($ch);

            if ($body === false) {
                log_message('error', 'reCAPTCHA verification request failed: ' . curl_error($ch));
                curl_close($ch);

                return false;
            }

            curl_close($ch);

            $result = json_decode((string) $body, true);

            return (bool) ($result['success'] ?? false);
        } catch (Throwable $e) {
            log_message('error', 'reCAPTCHA verification request failed: ' . $e->getMessage());

            return false;
        }
    }
}
