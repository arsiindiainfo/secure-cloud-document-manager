<?php

namespace App\Exceptions;

class ShareLinkLimitReachedException extends ApiException
{
    public function __construct(string $message = 'This link has reached its download limit.')
    {
        parent::__construct(409, 'SHARE_LINK_LIMIT_REACHED', $message);
    }
}
