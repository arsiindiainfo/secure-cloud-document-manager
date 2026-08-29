<?php

namespace App\Libraries;

use Config\Auth as AuthConfig;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use stdClass;
use UnexpectedValueException;

/**
 * Mints and verifies the short-lived access token (§5, §15). Refresh tokens
 * are handled separately (AuthService) since they're stored hashed in
 * refresh_tokens, not JWTs — only the access token is a JWT.
 */
class JwtService
{
    private readonly AuthConfig $config;

    public function __construct(?AuthConfig $config = null)
    {
        $this->config = $config ?? config(AuthConfig::class);
    }

    /** @param array<string, mixed> $claims merged into the token payload alongside sub/iat/exp */
    public function issueAccessToken(int $userId, string $role, array $claims = []): string
    {
        $now = time();

        $payload = array_merge($claims, [
            'sub'  => $userId,
            'role' => $role,
            'iat'  => $now,
            'exp'  => $now + $this->config->accessTokenTtl,
        ]);

        return JWT::encode($payload, $this->config->jwtSecret, 'HS256');
    }

    /** @throws ExpiredException|SignatureInvalidException|UnexpectedValueException on an invalid/expired token */
    public function verifyAccessToken(string $token): stdClass
    {
        return JWT::decode($token, new Key($this->config->jwtSecret, 'HS256'));
    }
}
