<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Libraries;

use Config\Auth as AuthConfig;

/**
 * Verifies the X-Signature header on the internal processing callback
 * (§9.3): HMAC-SHA256 of the raw request body, using a secret shared only
 * with the Lambda worker's environment — never a user JWT.
 */
class HmacSignatureVerifier
{
    private readonly AuthConfig $config;

    public function __construct(?AuthConfig $config = null)
    {
        $this->config = $config ?? config(AuthConfig::class);
    }

    public function sign(string $rawBody): string
    {
        return hash_hmac('sha256', $rawBody, $this->config->internalHmacSecret);
    }

    public function verify(string $rawBody, ?string $signatureHeader): bool
    {
        if ($signatureHeader === null || $signatureHeader === '') {
            return false;
        }

        return hash_equals($this->sign($rawBody), $signatureHeader);
    }
}

