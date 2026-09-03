<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use OpenApi\Generator;

/**
 * §26 — served at /api/docs, non-production only. Generating the spec on
 * every request (rather than a build step) keeps this a zero-maintenance
 * artifact: it can never go stale relative to the actual annotated code,
 * at the cost of a scan on each request — acceptable for a docs endpoint
 * that unauthenticated tooling isn't hammering.
 */
class OpenApiController extends BaseController
{
    public function ui(): ResponseInterface
    {
        $this->guardNonProduction();

        return $this->response->setContentType('text/html')->setBody(<<<'HTML'
            <!doctype html>
            <html>
              <head>
                <title>Secure Cloud Document Manager — API Docs</title>
                <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
              </head>
              <body>
                <div id="swagger-ui"></div>
                <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
                <script>
                  window.onload = () => SwaggerUIBundle({ url: '/api/docs.json', dom_id: '#swagger-ui' });
                </script>
              </body>
            </html>
            HTML);
    }

    public function spec(): ResponseInterface
    {
        $this->guardNonProduction();

        $openApi = (new Generator())->generate([APPPATH . 'Controllers', APPPATH . 'OpenApi']);

        return $this->response->setContentType('application/json')->setBody((string) $openApi?->toJson());
    }

    private function guardNonProduction(): void
    {
        if (ENVIRONMENT === 'production') {
            throw PageNotFoundException::forPageNotFound();
        }
    }
}

