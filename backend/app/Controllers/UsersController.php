<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Controllers;

use App\DTOs\PaginationRequestDTO;
use App\Services\UsersService;
use CodeIgniter\HTTP\ResponseInterface;
use OpenApi\Attributes as OA;

/**
 * §15 — user management. ADMIN-only except `me`, enforced via the `role`
 * route filter (app/Config/Routes.php), not in this Controller.
 */
class UsersController extends BaseController
{
    #[OA\Get(
        path: '/users/me',
        tags: ['Users'],
        summary: "Current user's profile",
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Profile', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', ref: '#/components/schemas/User'),
            ])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
        ],
    )]
    public function me(): ResponseInterface
    {
        return $this->ok((new UsersService())->me());
    }

    #[OA\Put(
        path: '/users/me/password',
        tags: ['Users'],
        summary: "Change the caller's own password",
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['currentPassword', 'newPassword'],
            properties: [
                new OA\Property(property: 'currentPassword', type: 'string'),
                new OA\Property(property: 'newPassword', type: 'string', minLength: 8),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Changed — every refresh token is revoked, including this session\'s'),
            new OA\Response(response: 400, description: '400 INCORRECT_PASSWORD or VALIDATION_ERROR', content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope')),
        ],
    )]
    public function changePassword(): ResponseInterface
    {
        $data = $this->validated('usersChangePassword');

        (new UsersService())->changePassword(service('authContext')->userId(), $data['currentPassword'], $data['newPassword']);

        return $this->ok(['message' => 'Password changed.']);
    }

    #[OA\Post(
        path: '/users',
        tags: ['Users'],
        summary: 'Invite a new user (ADMIN)',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['name', 'email', 'role'],
            properties: [
                new OA\Property(property: 'name', type: 'string', minLength: 2, maxLength: 120),
                new OA\Property(property: 'email', type: 'string', format: 'email'),
                new OA\Property(property: 'role', type: 'string', enum: ['ADMIN', 'MANAGER', 'EMPLOYEE']),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'Invited', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', properties: [new OA\Property(property: 'userId', type: 'integer')], type: 'object'),
            ])),
            new OA\Response(response: 400, ref: '#/components/responses/ValidationError'),
            new OA\Response(response: 403, ref: '#/components/responses/ForbiddenRole'),
            new OA\Response(response: 409, ref: '#/components/responses/Conflict', description: '409 DUPLICATE_NAME — email already registered'),
        ],
    )]
    public function invite(): ResponseInterface
    {
        $data = $this->validated('usersInvite');

        $result = (new UsersService())->invite(
            $data['name'],
            $data['email'],
            $data['role'],
            service('authContext')->userId(),
        );

        return $this->created(['userId' => $result['userId']]);
    }

    #[OA\Get(
        path: '/users',
        tags: ['Users'],
        summary: 'Paginated team member list (ADMIN)',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 20)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Users', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/User')),
                new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
            ])),
            new OA\Response(response: 403, ref: '#/components/responses/ForbiddenRole'),
        ],
    )]
    public function index(): ResponseInterface
    {
        $pagination = PaginationRequestDTO::fromRequest($this->request, ['createdAt', 'name'], 'createdAt');

        $result = (new UsersService())->list($pagination->page, $pagination->limit);

        return $this->paginated($result['items'], $pagination->meta($result['total']));
    }

    #[OA\Put(
        path: '/users/{id}',
        tags: ['Users'],
        summary: 'Update role/status (ADMIN)',
        security: [['bearerAuth' => []]],
        parameters: [new OA\PathParameter(name: 'id', schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [
            new OA\Property(property: 'role', type: 'string', enum: ['ADMIN', 'MANAGER', 'EMPLOYEE']),
            new OA\Property(property: 'status', type: 'string', enum: ['ACTIVE', 'DISABLED']),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Updated user', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', ref: '#/components/schemas/User'),
            ])),
            new OA\Response(response: 400, ref: '#/components/responses/ValidationError'),
            new OA\Response(response: 403, ref: '#/components/responses/ForbiddenRole'),
        ],
    )]
    public function update(int $id): ResponseInterface
    {
        $data = $this->validated('usersUpdate');

        $user = (new UsersService())->updateRoleStatus($id, $data['role'] ?? null, $data['status'] ?? null);

        return $this->ok($user);
    }

    #[OA\Delete(
        path: '/users/{id}',
        tags: ['Users'],
        summary: 'Permanently delete a user (ADMIN) — blocked for the caller\'s own account and one protected super-admin account',
        security: [['bearerAuth' => []]],
        parameters: [new OA\PathParameter(name: 'id', schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Deleted'),
            new OA\Response(response: 403, description: '403 FORBIDDEN_ROLE — own account or the protected super-admin', content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope')),
            new OA\Response(response: 404, description: '404 USER_NOT_FOUND', content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope')),
            new OA\Response(response: 409, description: '409 USER_HAS_CONTENT — they created folders/documents that still exist', content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope')),
        ],
    )]
    public function delete(int $id): ResponseInterface
    {
        (new UsersService())->delete($id, service('authContext')->userId());

        return $this->ok(['message' => 'User deleted.']);
    }
}

