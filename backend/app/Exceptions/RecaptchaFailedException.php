<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Exceptions;

class RecaptchaFailedException extends ApiException
{
    public function __construct(string $message = 'Captcha verification failed. Please try again.')
    {
        parent::__construct(400, 'RECAPTCHA_FAILED', $message);
    }
}
