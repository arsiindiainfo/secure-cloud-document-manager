<?php

namespace App\Exceptions;

class ShareLinkExpiredException extends ApiException
{
    public function __construct(string $message = 'This link has expired.')
    {
        parent::__construct(409, 'SHARE_LINK_EXPIRED', $message);
    }
}
