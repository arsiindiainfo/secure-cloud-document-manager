<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Exceptions;

class EmailNotVerifiedException extends ApiException
{
    public function __construct(string $message = 'Please verify your email before signing in — check your inbox for the link.')
    {
        parent::__construct(403, 'EMAIL_NOT_VERIFIED', $message);
    }
}
