<?php

namespace App\Controllers;

use App\Exceptions\ShareLinkNotFoundException;
use App\Services\SharingService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * §19 — the one unauthenticated, unversioned route in the whole API. No
 * jwtAuth filter, no /api/v1 prefix (see app/Config/Routes.php).
 */
class PublicShareController extends BaseController
{
    public function resolve(string $token): ResponseInterface
    {
        // §19: a malformed token is treated identically to "doesn't exist" —
        // never distinguish the two for an unauthenticated caller.
        if (! preg_match('/^[A-Za-z0-9_-]{43}$/', $token)) {
            throw new ShareLinkNotFoundException();
        }

        $result = (new SharingService())->resolvePublicLink($token);

        return $this->ok($result);
    }
}
