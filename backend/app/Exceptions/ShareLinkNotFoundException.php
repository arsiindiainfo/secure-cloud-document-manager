<?php

namespace App\Exceptions;

class ShareLinkNotFoundException extends ApiException
{
    public function __construct(string $message = 'This link is invalid.')
    {
        parent::__construct(404, 'SHARE_LINK_NOT_FOUND', $message);
    }
}
