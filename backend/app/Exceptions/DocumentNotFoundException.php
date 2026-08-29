<?php

namespace App\Exceptions;

/**
 * Also thrown when the document exists but the caller has no grant on it —
 * the two cases are never distinguished in the response (§6.3 guardrail).
 */
class DocumentNotFoundException extends ApiException
{
    public function __construct(string $message = 'Document not found.')
    {
        parent::__construct(404, 'DOCUMENT_NOT_FOUND', $message);
    }
}
