<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Google reCAPTCHA v2 secret key, sourced from .env — added to /auth/login
 * to keep automated credential-stuffing bots off the one endpoint that's
 * otherwise just rate-limited (§15). The site key is public by design and
 * lives in the frontend's own .env, not here.
 */
class Recaptcha extends BaseConfig
{
    public string $secretKey = '';

    /**
     * Kill switch, defaults ON. Was disabled after demo2's siteverify
     * rejected every check with invalid-input-response — most likely the
     * same root cause as the DB connection bug fixed alongside this
     * (env vars invisible to CodeIgniter during real request handling
     * under spark serve; see docker-compose.prod.yml's .env mount).
     * Re-enabled to test that theory. Set `recaptcha.enabled=false` in
     * .env to kill it again if it's still broken.
     */
    public bool $enabled = true;

    public function __construct()
    {
        parent::__construct();

        $this->secretKey = (string) env('recaptcha.secretKey', '');
        $this->enabled   = filter_var(env('recaptcha.enabled', true), FILTER_VALIDATE_BOOLEAN);
    }
}
