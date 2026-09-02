<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/**
 * §26 — root OpenAPI metadata. This class holds no code; it's just a place
 * to attach the document-level attributes zircote/swagger-php's scanner
 * looks for (§13's envelope/§14's error catalog are documented once here
 * rather than repeated on every endpoint).
 */
#[OA\Info(
    version: '1.0.0',
    title: 'Secure Cloud Document Manager API',
    description: "A Dropbox/Drive-style document manager. Every response is one of three shapes: `{success:true, data}`, "
        . '`{success:true, data, meta}` (paginated lists), or `{success:false, error:{code,message,details?}}` — see the '
        . '§14 error code catalog in the project plan for the full list of `error.code` values.',
    contact: new OA\Contact(name: 'Arsi India Info'),
)]
#[OA\Server(url: 'http://localhost:8082/api/v1', description: 'Local Docker Compose')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: 'Access token from POST /auth/login or /auth/refresh. Omitted entirely on /auth/login, /auth/refresh, '
        . '/s/:token, and /internal/processing-callback (HMAC-signed instead — see X-Signature below).',
)]
#[OA\SecurityScheme(
    securityScheme: 'internalHmac',
    type: 'apiKey',
    in: 'header',
    name: 'X-Signature',
    description: 'HMAC-SHA256 of the raw request body, keyed with a secret shared only with the Lambda processing worker (§9.3). Never a user JWT.',
)]
final class OpenApiInfo
{
}
