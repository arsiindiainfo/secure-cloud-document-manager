<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Exceptions;

/**
 * §18: revoking a document_permissions grant must never leave a document
 * with zero OWNERs — enforced inside sp_document_permission_revoke, not in
 * PHP, since two concurrent revokes must not both pass a plain count check.
 */
class LastOwnerException extends ApiException
{
    public function __construct(string $message = 'Cannot revoke the last remaining owner.')
    {
        parent::__construct(409, 'LAST_OWNER', $message);
    }
}

