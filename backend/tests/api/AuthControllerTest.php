<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace Tests\Api;

use Tests\Support\ApiTestCase;

/**
 * @internal
 */
final class AuthControllerTest extends ApiTestCase
{
    public function testLoginWithValidCredentialsReturnsTokens(): void
    {
        $result = $this->post('api/v1/auth/login', [
            'email'    => 'admin@meridian.test',
            'password' => 'Passw0rd!',
        ]);

        $result->assertStatus(200);
        $result->assertJSONFragment(['success' => true]);
        $body = json_decode($result->getJSON(), true);
        $this->assertNotEmpty($body['data']['accessToken']);
        $this->assertNotEmpty($body['data']['refreshToken']);
        $this->assertSame('admin@meridian.test', $body['data']['user']['email']);
    }

    public function testLoginWithWrongPasswordIsUnauthorized(): void
    {
        $result = $this->post('api/v1/auth/login', [
            'email'    => 'admin@meridian.test',
            'password' => 'wrong-password',
        ]);

        $result->assertStatus(401);
        $body = json_decode($result->getJSON(), true);
        $this->assertSame('UNAUTHORIZED', $body['error']['code']);
    }

    public function testLoginWithUnknownEmailReturnsSameGenericMessageAsWrongPassword(): void
    {
        // §15: never reveals whether the email exists — both failure modes
        // must be indistinguishable to the caller.
        $unknownEmailResult = $this->post('api/v1/auth/login', [
            'email'    => 'nobody@meridian.test',
            'password' => 'whatever',
        ]);
        $wrongPasswordResult = $this->post('api/v1/auth/login', [
            'email'    => 'admin@meridian.test',
            'password' => 'whatever',
        ]);

        $unknownBody  = json_decode($unknownEmailResult->getJSON(), true);
        $wrongPassBody = json_decode($wrongPasswordResult->getJSON(), true);

        $unknownEmailResult->assertStatus(401);
        $wrongPasswordResult->assertStatus(401);
        $this->assertSame($wrongPassBody['error']['message'], $unknownBody['error']['message']);
    }

    public function testLoginValidatesPayload(): void
    {
        $result = $this->post('api/v1/auth/login', ['email' => 'not-an-email', 'password' => '']);

        $result->assertStatus(400);
        $body = json_decode($result->getJSON(), true);
        $this->assertSame('VALIDATION_ERROR', $body['error']['code']);
    }

    public function testRefreshRotatesTokenAndRejectsReuse(): void
    {
        $login    = json_decode($this->post('api/v1/auth/login', [
            'email' => 'manager@meridian.test', 'password' => 'Passw0rd!',
        ])->getJSON(), true);
        $oldToken = $login['data']['refreshToken'];

        $refreshed = $this->post('api/v1/auth/refresh', ['refreshToken' => $oldToken]);
        $refreshed->assertStatus(200);

        $reuse = $this->post('api/v1/auth/refresh', ['refreshToken' => $oldToken]);
        $reuse->assertStatus(401);
    }

    public function testLoginIsRateLimitedAfterTenAttemptsPerMinute(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post('api/v1/auth/login', ['email' => 'admin@meridian.test', 'password' => 'wrong'])->assertStatus(401);
        }

        $result = $this->post('api/v1/auth/login', ['email' => 'admin@meridian.test', 'password' => 'wrong']);
        $result->assertStatus(429);
        $this->assertSame('RATE_LIMITED', json_decode($result->getJSON(), true)['error']['code']);
    }

    public function testLogoutRevokesRefreshToken(): void
    {
        $login = json_decode($this->post('api/v1/auth/login', [
            'email' => 'employee@meridian.test', 'password' => 'Passw0rd!',
        ])->getJSON(), true);

        $this->withHeaders(['Authorization' => 'Bearer ' . $login['data']['accessToken']])
            ->post('api/v1/auth/logout', ['refreshToken' => $login['data']['refreshToken']])
            ->assertStatus(200);

        $this->post('api/v1/auth/refresh', ['refreshToken' => $login['data']['refreshToken']])
            ->assertStatus(401);
    }
}

