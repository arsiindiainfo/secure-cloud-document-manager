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
     * Kill switch, defaults OFF. On demo2, Google's siteverify rejected
     * every check made through CodeIgniter's HTTP client with
     * invalid-input-response for otherwise-valid tokens, and switching to
     * raw curl (see RecaptchaVerifier) didn't resolve it either — disabled
     * here until that's root-caused. Flip on by setting
     * `recaptcha.enabled=true` in .env once it's fixed; the secret key
     * stays configured below in the meantime.
     */
    public bool $enabled = false;

    public function __construct()
    {
        parent::__construct();

        $this->secretKey = (string) env('recaptcha.secretKey', '');
        $this->enabled   = filter_var(env('recaptcha.enabled', false), FILTER_VALIDATE_BOOLEAN);
    }
}
