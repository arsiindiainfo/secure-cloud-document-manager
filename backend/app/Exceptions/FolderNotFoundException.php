<?php

namespace App\Exceptions;

/**
 * Also thrown when the folder exists but the caller has no grant on it —
 * the two cases are never distinguished in the response (§6.3 guardrail).
 */
class FolderNotFoundException extends ApiException
{
    public function __construct(string $message = 'Folder not found.')
    {
        parent::__construct(404, 'FOLDER_NOT_FOUND', $message);
    }
}
