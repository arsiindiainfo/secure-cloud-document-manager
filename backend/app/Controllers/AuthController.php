<?php

namespace App\Controllers;

use App\Services\AuthService;
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
            new OA\Response(response: 400, ref: '#/components/responses/ValidationError'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized', description: '401 — invalid credentials (generic; never reveals whether the email exists)'),
            new OA\Response(response: 429, ref: '#/components/responses/RateLimited'),
        ],
    )]
    public function login(): ResponseInterface
    {
        $data = $this->validated('authLogin');

        $result = (new AuthService())->login($data['email'], $data['password'], $this->request->getIPAddress());

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
}
