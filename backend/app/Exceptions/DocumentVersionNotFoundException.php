<?php

namespace App\Exceptions;

/**
 * §9.3: the Lambda's callback identifies a version by its s3_key (the only
 * thing it actually has — an S3 ObjectCreated event carries no application
 * ID). If sp_document_upload_commit/new_version hasn't run yet — the S3 PUT
 * finished but the browser's "complete" call hasn't landed — this key
 * legitimately doesn't exist yet, and the Lambda is expected to retry.
 */
class DocumentVersionNotFoundException extends ApiException
{
    public function __construct(string $message = 'No document version was found for this S3 key yet.')
    {
        parent::__construct(404, 'DOCUMENT_VERSION_NOT_FOUND', $message);
    }
}
