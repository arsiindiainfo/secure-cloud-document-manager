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

    public function __construct()
    {
        parent::__construct();

        $this->secretKey = (string) env('recaptcha.secretKey', '');
    }
}
