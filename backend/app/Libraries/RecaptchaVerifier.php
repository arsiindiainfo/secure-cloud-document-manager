<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Libraries;

use Config\Recaptcha as RecaptchaConfig;
use Config\Services;
use Throwable;

/**
 * Verifies a Google reCAPTCHA v2 response token against Google's
 * siteverify endpoint (§15 — bot mitigation on /auth/login, on top of the
 * existing per-IP rate limit). Skipped entirely in the `testing`
 * environment: PHPUnit can't solve a real captcha, and there is no value
 * in this making a real network call to Google on every test run.
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
            $response = Services::curlrequest()->request('POST', self::VERIFY_URL, [
                'form_params' => array_filter([
                    'secret'   => $this->config->secretKey,
                    'response' => $token,
                    'remoteip' => $remoteIp,
                ]),
            ]);

            $result = json_decode((string) $response->getBody(), true);

            return (bool) ($result['success'] ?? false);
        } catch (Throwable $e) {
            log_message('error', 'reCAPTCHA verification request failed: ' . $e->getMessage());

            return false;
        }
    }
}
