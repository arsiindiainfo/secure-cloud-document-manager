<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/**
 * §13/§14 — reusable schema/response components. Every controller
 * references these by name (`#[OA\Response(ref: '#/components/responses/...')]`)
 * instead of repeating the error envelope shape on every single endpoint —
 * the one place that could drift from §14's catalog is this file.
 */
#[OA\Schema(
    schema: 'ErrorEnvelope',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'error', properties: [
            new OA\Property(property: 'code', type: 'string', example: 'VALIDATION_ERROR'),
            new OA\Property(property: 'message', type: 'string'),
            new OA\Property(property: 'details', type: 'array', nullable: true, items: new OA\Items(
                properties: [
                    new OA\Property(property: 'field', type: 'string'),
                    new OA\Property(property: 'message', type: 'string'),
                ],
                type: 'object',
            )),
        ], type: 'object'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'User',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'email', type: 'string', format: 'email'),
        new OA\Property(property: 'role', type: 'string', enum: ['ADMIN', 'MANAGER', 'EMPLOYEE']),
        new OA\Property(property: 'status', type: 'string', enum: ['ACTIVE', 'DISABLED']),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'Folder',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'parentFolderId', type: 'integer', nullable: true),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'createdBy', type: 'integer'),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time'),
        new OA\Property(property: 'deletedAt', type: 'string', format: 'date-time', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'Document',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'folderId', type: 'integer'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'tags', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'currentVersion', type: 'integer'),
        new OA\Property(property: 'createdBy', type: 'integer'),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time'),
        new OA\Property(property: 'deletedAt', type: 'string', format: 'date-time', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'DocumentDetail',
    allOf: [
        new OA\Schema(ref: '#/components/schemas/Document'),
        new OA\Schema(properties: [
            new OA\Property(property: 'currentVersionDetail', ref: '#/components/schemas/DocumentVersion', nullable: true),
            new OA\Property(property: 'effectivePermission', type: 'string', enum: ['VIEWER', 'EDITOR', 'OWNER']),
            new OA\Property(property: 'processingStatus', type: 'string', enum: ['PENDING', 'PROCESSING', 'COMPLETED', 'FAILED'], nullable: true),
        ], type: 'object'),
    ],
)]
#[OA\Schema(
    schema: 'DocumentVersion',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'documentId', type: 'integer'),
        new OA\Property(property: 'versionNo', type: 'integer'),
        new OA\Property(property: 'mimeType', type: 'string'),
        new OA\Property(property: 'sizeBytes', type: 'integer'),
        new OA\Property(property: 'checksumSha256', type: 'string'),
        new OA\Property(property: 'hasThumbnail', type: 'boolean'),
        new OA\Property(property: 'isCurrent', type: 'boolean'),
        new OA\Property(property: 'uploadedBy', type: 'integer'),
        new OA\Property(property: 'uploadedAt', type: 'string', format: 'date-time'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'ShareLink',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'documentId', type: 'integer'),
        new OA\Property(property: 'permission', type: 'string', enum: ['VIEW', 'DOWNLOAD']),
        new OA\Property(property: 'maxDownloads', type: 'integer', nullable: true),
        new OA\Property(property: 'downloadCount', type: 'integer'),
        new OA\Property(property: 'expiresAt', type: 'string', format: 'date-time'),
        new OA\Property(property: 'revokedAt', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'createdBy', type: 'integer'),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'PermissionGrant',
    properties: [
        new OA\Property(property: 'userId', type: 'integer'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'email', type: 'string', format: 'email'),
        new OA\Property(property: 'permission', type: 'string', enum: ['VIEWER', 'EDITOR', 'OWNER']),
        new OA\Property(property: 'grantedAt', type: 'string', format: 'date-time'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'PaginationMeta',
    properties: [
        new OA\Property(property: 'page', type: 'integer'),
        new OA\Property(property: 'limit', type: 'integer'),
        new OA\Property(property: 'total', type: 'integer'),
        new OA\Property(property: 'totalPages', type: 'integer'),
    ],
    type: 'object',
)]
#[OA\Response(response: 'ValidationError', description: '400 — payload failed rule-group validation', content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope'))]
#[OA\Response(response: 'Unauthorized', description: '401 — missing or expired access token', content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope'))]
#[OA\Response(response: 'ForbiddenRole', description: "403 — authenticated, but global role lacks permission", content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope'))]
#[OA\Response(response: 'NotFound', description: "404 — doesn't exist, or the caller has no grant on it (never distinguished, §6.3)", content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope'))]
#[OA\Response(response: 'Conflict', description: '409 — duplicate name, or a state conflict (e.g. LAST_OWNER, DOCUMENT_DELETED)', content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope'))]
#[OA\Response(response: 'RateLimited', description: '429 — too many requests', content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope'))]
final class Components
{
}

