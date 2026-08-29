<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * JWT + internal HMAC callback secrets/TTLs, sourced from .env (§5, §15, §9.3).
 */
class Auth extends BaseConfig
{
    public string $jwtSecret = '';

    /** Access token lifetime, in seconds — 15 minutes per §5. */
    public int $accessTokenTtl = 900;

    /** Refresh token lifetime, in seconds — 14 days. */
    public int $refreshTokenTtl = 1209600;

    /** Shared only with the Lambda worker — never a user JWT (§9.3). */
    public string $internalHmacSecret = '';

    public function __construct()
    {
        parent::__construct();

        $this->jwtSecret          = (string) env('auth.jwtSecret', '');
        $this->accessTokenTtl     = (int) env('auth.accessTokenTtl', 900);
        $this->refreshTokenTtl    = (int) env('auth.refreshTokenTtl', 1209600);
        $this->internalHmacSecret = (string) env('auth.internalHmacSecret', '');
    }
}
