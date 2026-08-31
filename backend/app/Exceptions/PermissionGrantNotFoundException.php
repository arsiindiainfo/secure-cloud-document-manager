<?php

namespace App\Exceptions;

class PermissionGrantNotFoundException extends ApiException
{
    public function __construct(string $message = 'This user has no direct access to revoke.')
    {
        parent::__construct(404, 'PERMISSION_GRANT_NOT_FOUND', $message);
    }
}
