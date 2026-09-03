<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Filters;

use App\Exceptions\ForbiddenActionException;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

/**
 * Route-level global-role gate, e.g. `filter => 'role:ADMIN'` (§6.1).
 * Always runs after JwtAuthFilter — AuthContext must already be populated.
 * This only checks the coarse global role; per-resource ACL checks live in
 * DocumentService::authorize() (§6.3), not here.
 */
class RoleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $allowedRoles = $arguments ?? [];

        if ($allowedRoles !== [] && ! in_array(Services::authContext()->role(), $allowedRoles, true)) {
            throw new ForbiddenActionException();
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}

