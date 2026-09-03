<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use OpenApi\Attributes as OA;

/** §31.2 — public project + author metadata. Presentation only, same spirit as the X-Powered-By header. */
class AboutController extends BaseController
{
    #[OA\Get(
        path: '/about',
        tags: ['Public'],
        summary: 'Project and author metadata',
        responses: [
            new OA\Response(response: 200, description: 'Metadata', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Secure Cloud Document Manager'),
                    new OA\Property(property: 'version', type: 'string', example: '1.0.0'),
                    new OA\Property(property: 'author', type: 'string', example: 'Arsi India Info'),
                    new OA\Property(property: 'website', type: 'string', example: 'https://arsiindiainfo.com'),
                    new OA\Property(property: 'license', type: 'string', example: 'MIT'),
                    new OA\Property(property: 'environment', type: 'string', example: 'development'),
                ], type: 'object'),
            ])),
        ],
    )]
    public function index(): ResponseInterface
    {
        return $this->ok([
            'name'        => 'Secure Cloud Document Manager',
            'version'     => '1.0.0',
            'author'      => 'Arsi India Info',
            'website'     => 'https://arsiindiainfo.com',
            'license'     => 'MIT',
            'environment' => ENVIRONMENT,
        ]);
    }
}

