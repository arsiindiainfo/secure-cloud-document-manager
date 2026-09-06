<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

// A browser's CORS preflight is an OPTIONS request to the *same* path as
// the real call. CodeIgniter resolves routing before running filters, so
// without a matching OPTIONS route the preflight 404s before the `cors`
// global filter (app/Config/Filters.php) ever gets a chance to answer it.
$routes->options('(:any)', static fn () => service('response')->setStatusCode(204));

// §12: all authenticated routes live under /api/v1. Public routes
// (auth/login, auth/refresh, /s/:token, /internal/processing-callback) are
// deliberately outside the jwtAuth-filtered group below.
$routes->group('api/v1', function ($routes) {
    // §15 — 10/min/IP, cache-backed (§5).
    $routes->post('auth/login', 'AuthController::login', ['filter' => 'rateLimit:10,60']);
    $routes->post('auth/refresh', 'AuthController::refresh');
    $routes->post('auth/register', 'AuthController::register', ['filter' => 'rateLimit:10,60']);
    $routes->post('auth/verify-email', 'AuthController::verifyEmail', ['filter' => 'rateLimit:10,60']);

    // §31.2 — public project/author metadata.
    $routes->get('about', 'AboutController::index');

    $routes->group('', ['filter' => 'jwtAuth'], function ($routes) {
        $routes->post('auth/logout', 'AuthController::logout');

        $routes->get('users/me', 'UsersController::me');
        $routes->put('users/me/password', 'UsersController::changePassword');
        $routes->get('users/search', 'UsersController::search');

        $routes->group('users', ['filter' => 'role:ADMIN'], function ($routes) {
            $routes->post('/', 'UsersController::invite');
            $routes->get('/', 'UsersController::index');
            $routes->put('(:num)', 'UsersController::update/$1');
            $routes->delete('(:num)', 'UsersController::delete/$1');
        });

        // §16 — folders
        $routes->post('folders', 'FoldersController::create');
        $routes->get('folders/(:any)/children', 'FoldersController::children/$1');
        $routes->get('folders/children', 'FoldersController::children'); // root, no :id segment
        $routes->put('folders/(:num)', 'FoldersController::update/$1');
        $routes->delete('folders/(:num)', 'FoldersController::delete/$1');
        $routes->post('folders/(:num)/restore', 'FoldersController::restore/$1');
        $routes->get('folders/(:num)', 'FoldersController::show/$1');

        // §18 (folder variant) — sharing: internal grants only, no external links
        $routes->post('folders/(:num)/permissions', 'FolderSharingController::grant/$1');
        $routes->get('folders/(:num)/permissions', 'FolderSharingController::listGrants/$1');
        $routes->delete('folders/(:num)/permissions/(:num)', 'FolderSharingController::revoke/$1/$2');

        // §17/§24 — documents, upload flow, versions, search
        $routes->get('documents', 'DocumentsController::index');
        $routes->post('documents/uploads/initiate', 'DocumentsController::initiateUpload');
        $routes->post('documents/uploads/complete', 'DocumentsController::completeUpload');
        $routes->get('documents/(:num)', 'DocumentsController::show/$1');
        $routes->put('documents/(:num)', 'DocumentsController::update/$1');
        $routes->delete('documents/(:num)', 'DocumentsController::delete/$1');
        $routes->post('documents/(:num)/restore', 'DocumentsController::restore/$1');
        $routes->post('documents/(:num)/versions/initiate', 'DocumentsController::initiateVersion/$1');
        $routes->post('documents/(:num)/versions/complete', 'DocumentsController::completeVersion/$1');
        $routes->get('documents/(:num)/versions', 'DocumentsController::versions/$1');

        // §18 — download, preview, thumbnail
        $routes->get('documents/(:num)/download', 'DocumentsController::download/$1');
        $routes->get('documents/(:num)/preview', 'DocumentsController::preview/$1');
        $routes->get('documents/(:num)/thumbnail', 'DocumentsController::thumbnail/$1');

        // §18 — sharing: internal grants + external share links
        $routes->post('documents/(:num)/permissions', 'SharingController::grant/$1');
        $routes->get('documents/(:num)/permissions', 'SharingController::listGrants/$1');
        $routes->delete('documents/(:num)/permissions/(:num)', 'SharingController::revoke/$1/$2');
        $routes->post('documents/(:num)/share-links', 'SharingController::createShareLink/$1');
        $routes->get('documents/(:num)/share-links', 'SharingController::listShareLinks/$1');
        $routes->delete('share-links/(:num)', 'SharingController::revokeShareLink/$1');

        // §19 — audit trail, ADMIN only
        $routes->get('audit-logs', 'AuditLogController::index', ['filter' => 'role:ADMIN']);

        // §22.2/§22.8 — dashboard summary, trash listing (both caller-scoped)
        $routes->get('dashboard', 'DashboardController::index');
        $routes->get('trash', 'TrashController::index');
        $routes->delete('trash/documents/(:num)', 'TrashController::purgeDocument/$1');
        $routes->delete('trash/folders/(:num)', 'TrashController::purgeFolder/$1');
    });

    // §9.3/§19 — internal, HMAC-signed (never a user JWT): deliberately
    // outside the jwtAuth-filtered group above.
    $routes->post('internal/processing-callback', 'ProcessingCallbackController::callback', ['filter' => 'internalHmac']);

    // §12/§19 — public share-link resolve: trust is the 43-char token
    // itself, no jwtAuth filter. Lives under api/v1 (not a bare top-level
    // route) so it goes through the same nginx /api/ proxy as every other
    // endpoint — a bare `/s/(:any)` never reached this backend in
    // production, since nginx only proxies /api/ to it and serves
    // everything else (including /s/...) from the frontend's static files.
    $routes->get('s/(:any)', 'PublicShareController::resolve/$1');
});

// §26 — OpenAPI docs, non-production only (guarded inside the controller).
$routes->get('api/docs', 'OpenApiController::ui');
$routes->get('api/docs.json', 'OpenApiController::spec');

