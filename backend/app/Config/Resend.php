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
 * Resend API key, sourced from .env — production transactional email
 * (ResendMailer) sends through this. Left empty in local dev, where mail
 * goes to Mailhog instead (see ResendMailer's ENVIRONMENT check).
 */
class Resend extends BaseConfig
{
    public string $apiKey = '';

    public function __construct()
    {
        parent::__construct();

        $this->apiKey = (string) env('resend.apiKey', '');
    }
}
