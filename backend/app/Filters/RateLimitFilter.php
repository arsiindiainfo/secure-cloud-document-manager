<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Filters;

use App\Exceptions\RateLimitedException;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

/**
 * §5/§15 — cache-backed fixed-window rate limit, keyed by client IP + route.
 * Route filter arguments are `limit,windowSeconds`, e.g. `rateLimit:10,60`
 * for "10 requests per 60 seconds" (§15's login limit).
 *
 * The window's own reset time is stored alongside the count (rather than
 * relying on the cache driver's own TTL/expiry as the window boundary) so a
 * steady trickle of requests can never keep re-arming the window forever —
 * every driver behind Services::cache() sees the same fixed window.
 */
class RateLimitFilter implements FilterInterface
{
    /** @param list<string>|null $arguments */
    public function before(RequestInterface $request, $arguments = null)
    {
        $limit         = isset($arguments[0]) ? (int) $arguments[0] : 60;
        $windowSeconds = isset($arguments[1]) ? (int) $arguments[1] : 60;

        $cache = Services::cache();
        // getServer() (not getUri(), which isn't part of RequestInterface)
        // — the route path is enough to key the window per-endpoint.
        $path  = (string) $request->getServer('REQUEST_URI');
        $key   = 'ratelimit_' . md5($request->getIPAddress() . '_' . $path);
        $now   = time();

        $state = $cache->get($key);
        if (! is_array($state) || $now >= $state['resetAt']) {
            $state = ['count' => 0, 'resetAt' => $now + $windowSeconds];
        }

        if ($state['count'] >= $limit) {
            throw new RateLimitedException();
        }

        $state['count']++;
        $cache->save($key, $state, max(1, $state['resetAt'] - $now));

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}

