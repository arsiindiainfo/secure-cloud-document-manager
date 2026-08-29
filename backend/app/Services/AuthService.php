<?php

namespace App\Services;

use App\Exceptions\UnauthorizedException;
use App\Libraries\JwtService;
use App\Models\RefreshTokenModel;
use App\Models\AuditLogModel;
use App\Models\UserModel;
use Config\Auth as AuthConfig;

/**
 * Framework-agnostic business logic for §15's auth endpoints — unit
 * testable without booting CodeIgniter's HTTP stack (§5). Never reveals
 * whether an email exists; every failure path returns the same
 * UnauthorizedException regardless of *why* it failed.
 */
class AuthService
{
    public function __construct(
        private readonly UserModel $users = new UserModel(),
        private readonly RefreshTokenModel $refreshTokens = new RefreshTokenModel(),
        private readonly AuditLogModel $auditLog = new AuditLogModel(),
        private readonly JwtService $jwt = new JwtService(),
        private readonly AuthConfig $config = new AuthConfig(),
    ) {
    }

    /** @return array{accessToken: string, refreshToken: string, user: \App\Entities\User} */
    public function login(string $email, string $password, ?string $ip = null): array
    {
        $credentials = $this->users->findForAuthentication($email);

        if ($credentials === null || $credentials['status'] !== 'ACTIVE'
            || ! password_verify($password, $credentials['passwordHash'])) {
            $this->auditLog->record(
                $credentials['userId'] ?? null,
                'LOGIN_FAILED',
                'USER',
                $credentials['userId'] ?? 0,
                ['email' => $email],
                $ip,
            );

            throw new UnauthorizedException('Invalid email or password.');
        }

        $accessToken  = $this->jwt->issueAccessToken($credentials['userId'], $credentials['role']);
        $refreshToken = $this->issueRefreshToken($credentials['userId']);

        $this->auditLog->record($credentials['userId'], 'LOGIN_SUCCESS', 'USER', $credentials['userId'], [], $ip);

        /** @var \App\Entities\User $user */
        $user = $this->users->find($credentials['userId']);

        return ['accessToken' => $accessToken, 'refreshToken' => $refreshToken, 'user' => $user];
    }

    /** @return array{accessToken: string, refreshToken: string} */
    public function refresh(string $refreshToken): array
    {
        $active = $this->refreshTokens->findActiveByToken($refreshToken);

        if ($active === null) {
            throw new UnauthorizedException('Refresh token is invalid or expired.');
        }

        /** @var \App\Entities\User|null $user */
        $user = $this->users->find($active['userId']);
        if ($user === null || $user->status !== 'ACTIVE') {
            throw new UnauthorizedException('Account is no longer active.');
        }

        $this->refreshTokens->revoke($active['id']);

        return [
            'accessToken'  => $this->jwt->issueAccessToken($user->id, $user->role),
            'refreshToken' => $this->issueRefreshToken($user->id),
        ];
    }

    public function logout(string $refreshToken): void
    {
        $active = $this->refreshTokens->findActiveByToken($refreshToken);
        if ($active !== null) {
            $this->refreshTokens->revoke($active['id']);
        }
    }

    private function issueRefreshToken(int $userId): string
    {
        $token = bin2hex(random_bytes(32));
        $this->refreshTokens->issue($userId, $token, $this->config->refreshTokenTtl);

        return $token;
    }
}
