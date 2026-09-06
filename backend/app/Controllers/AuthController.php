<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\RegistrationService;
use CodeIgniter\HTTP\ResponseInterface;
use OpenApi\Attributes as OA;

/**
 * §15 — public auth endpoints. Thin per §5: validate, call one Service
 * method, map the result to the envelope.
 */
class AuthController extends BaseController
{
    #[OA\Post(
        path: '/auth/login',
        tags: ['Auth'],
        summary: 'Authenticate and issue tokens',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['email', 'password'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email'),
                new OA\Property(property: 'password', type: 'string'),
                new OA\Property(property: 'recaptchaToken', type: 'string', description: 'Google reCAPTCHA v2 response token'),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Tokens + user profile', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'accessToken', type: 'string'),
                    new OA\Property(property: 'refreshToken', type: 'string'),
                    new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                ], type: 'object'),
            ])),
            new OA\Response(response: 400, ref: '#/components/responses/ValidationError', description: '400 VALIDATION_ERROR, or RECAPTCHA_FAILED if the captcha token is missing/invalid'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized', description: '401 — invalid credentials (generic; never reveals whether the email exists)'),
            new OA\Response(response: 429, ref: '#/components/responses/RateLimited'),
        ],
    )]
    public function login(): ResponseInterface
    {
        $data = $this->validated('authLogin');

        $result = (new AuthService())->login($data['email'], $data['password'], $this->request->getIPAddress(), $data['recaptchaToken'] ?? null);

        return $this->ok([
            'accessToken'  => $result['accessToken'],
            'refreshToken' => $result['refreshToken'],
            'user'         => $result['user'],
        ]);
    }

    #[OA\Post(
        path: '/auth/refresh',
        tags: ['Auth'],
        summary: 'Rotate access/refresh tokens',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['refreshToken'],
            properties: [new OA\Property(property: 'refreshToken', type: 'string')],
        )),
        responses: [
            new OA\Response(response: 200, description: 'New token pair', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'accessToken', type: 'string'),
                    new OA\Property(property: 'refreshToken', type: 'string'),
                ], type: 'object'),
            ])),
            new OA\Response(response: 400, ref: '#/components/responses/ValidationError'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized', description: '401 — expired, revoked, or already-rotated refresh token'),
        ],
    )]
    public function refresh(): ResponseInterface
    {
        $data = $this->validated('authRefresh');

        $result = (new AuthService())->refresh($data['refreshToken']);

        return $this->ok($result);
    }

    #[OA\Post(
        path: '/auth/logout',
        tags: ['Auth'],
        summary: 'Revoke the caller\'s current refresh token',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['refreshToken'],
            properties: [new OA\Property(property: 'refreshToken', type: 'string')],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Logged out'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
        ],
    )]
    public function logout(): ResponseInterface
    {
        $data = $this->validated('authRefresh');

        (new AuthService())->logout($data['refreshToken']);

        return $this->ok(['loggedOut' => true]);
    }

    #[OA\Post(
        path: '/auth/register',
        tags: ['Auth'],
        summary: 'Self-register (role MANAGER) — requires clicking the emailed verification link before login',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['name', 'email', 'password'],
            properties: [
                new OA\Property(property: 'name', type: 'string', minLength: 2, maxLength: 120),
                new OA\Property(property: 'email', type: 'string', format: 'email'),
                new OA\Property(property: 'password', type: 'string', minLength: 8),
                new OA\Property(property: 'recaptchaToken', type: 'string', description: 'Google reCAPTCHA v2 response token'),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'Registered — check email to verify', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', properties: [new OA\Property(property: 'userId', type: 'integer')], type: 'object'),
            ])),
            new OA\Response(response: 400, ref: '#/components/responses/ValidationError', description: '400 VALIDATION_ERROR, or RECAPTCHA_FAILED'),
            new OA\Response(response: 409, ref: '#/components/responses/Conflict', description: '409 DUPLICATE_NAME — email already registered'),
            new OA\Response(response: 429, ref: '#/components/responses/RateLimited'),
        ],
    )]
    public function register(): ResponseInterface
    {
        $data = $this->validated('authRegister');

        $userId = (new RegistrationService())->register(
            $data['name'],
            $data['email'],
            $data['password'],
            $data['recaptchaToken'] ?? null,
            $this->request->getIPAddress(),
        );

        return $this->created(['userId' => $userId]);
    }

    #[OA\Post(
        path: '/auth/verify-email',
        tags: ['Auth'],
        summary: 'Confirm a self-registered email address via its verification token',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['token'],
            properties: [new OA\Property(property: 'token', type: 'string')],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Verified — the account can now log in'),
            new OA\Response(response: 400, description: '400 VALIDATION_ERROR or INVALID_VERIFICATION_TOKEN', content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope')),
        ],
    )]
    public function verifyEmail(): ResponseInterface
    {
        $data = $this->validated('authVerifyEmail');

        (new RegistrationService())->verifyEmail($data['token']);

        return $this->ok(['verified' => true]);
    }
}

