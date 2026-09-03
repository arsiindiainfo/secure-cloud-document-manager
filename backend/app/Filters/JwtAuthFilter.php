<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Filters;

use App\Exceptions\UnauthorizedException;
use App\Libraries\JwtService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Throwable;

/**
 * Verifies `Authorization: Bearer <accessToken>` on every route it's applied
 * to (§12) and populates AuthContext for the rest of the request. Public
 * routes (/auth/login, /auth/refresh, /s/:token, /internal/processing-callback)
 * never carry this filter — see app/Config/Routes.php.
 */
class JwtAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $header = $request->getHeaderLine('Authorization');

        if (! preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            throw new UnauthorizedException();
        }

        try {
            $claims = (new JwtService())->verifyAccessToken($matches[1]);
        } catch (Throwable) {
            throw new UnauthorizedException();
        }

        Services::authContext()->setUser((int) $claims->sub, (string) $claims->role);

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}

