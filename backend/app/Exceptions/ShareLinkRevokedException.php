<?php

namespace App\Exceptions;

class ShareLinkRevokedException extends ApiException
{
    public function __construct(string $message = 'This link has been revoked by its owner.')
    {
        parent::__construct(409, 'SHARE_LINK_REVOKED', $message);
    }
}
