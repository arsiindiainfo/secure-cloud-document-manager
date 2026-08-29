<?php

namespace App\Exceptions;

class DocumentDeletedException extends ApiException
{
    public function __construct(string $message = 'This document has been moved to trash.')
    {
        parent::__construct(409, 'DOCUMENT_DELETED', $message);
    }
}
