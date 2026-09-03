<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Filters;

use App\Exceptions\InvalidSignatureException;
use App\Libraries\HmacSignatureVerifier;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Guards POST /internal/processing-callback (§9.3): the Lambda worker signs
 * the raw request body with a secret shared only in its own environment —
 * never a user JWT, so this filter runs instead of jwtAuth on that route.
 */
class InternalHmacFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! (new HmacSignatureVerifier())->verify((string) $request->getBody(), $request->getHeaderLine('X-Signature'))) {
            throw new InvalidSignatureException();
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}

