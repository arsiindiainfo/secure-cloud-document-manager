<?php

namespace Tests\Api;

use Tests\Support\ApiTestCase;

/**
 * @internal
 */
final class UsersControllerTest extends ApiTestCase
{
    public function testMeReturnsCallerProfile(): void
    {
        $result = $this->withHeaders($this->adminHeaders())->get('api/v1/users/me');

        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);
        $this->assertSame('admin@meridian.test', $body['data']['email']);
    }

    public function testMeWithoutTokenIsUnauthorized(): void
    {
        $this->get('api/v1/users/me')->assertStatus(401);
    }

    public function testAdminCanInviteUser(): void
    {
        $result = $this->withHeaders($this->adminHeaders())->post('api/v1/users', [
            'name' => 'New Hire', 'email' => 'newhire@meridian.test', 'role' => 'EMPLOYEE',
        ]);

        $result->assertStatus(201);
        $this->seeInDatabase('users', ['email' => 'newhire@meridian.test', 'role' => 'EMPLOYEE']);
    }

    public function testNonAdminCannotInviteUser(): void
    {
        $this->withHeaders($this->employeeHeaders())->post('api/v1/users', [
            'name' => 'Nope', 'email' => 'nope@meridian.test', 'role' => 'EMPLOYEE',
        ])->assertStatus(403);

        $this->dontSeeInDatabase('users', ['email' => 'nope@meridian.test']);
    }

    public function testInvitingDuplicateEmailFails(): void
    {
        $result = $this->withHeaders($this->adminHeaders())->post('api/v1/users', [
            'name' => 'Dup', 'email' => 'admin@meridian.test', 'role' => 'EMPLOYEE',
        ]);

        $result->assertStatus(400); // caught by the is_unique rule group before it reaches the Service
    }

    public function testAdminCanListUsers(): void
    {
        $result = $this->withHeaders($this->adminHeaders())->get('api/v1/users');

        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);
        $this->assertCount(3, $body['data']);
        $this->assertSame(3, $body['meta']['total']);
    }

    public function testDisablingUserRevokesRefreshTokens(): void
    {
        $login = json_decode($this->post('api/v1/auth/login', [
            'email' => 'employee@meridian.test', 'password' => 'Passw0rd!',
        ])->getJSON(), true);

        $this->withHeaders($this->adminHeaders())
            ->withBodyFormat('json')
            ->put('api/v1/users/3', ['status' => 'DISABLED'])
            ->assertStatus(200);

        $this->post('api/v1/auth/refresh', ['refreshToken' => $login['data']['refreshToken']])
            ->assertStatus(401);
    }
}
