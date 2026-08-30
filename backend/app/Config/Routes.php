<?php

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
    $routes->post('auth/login', 'AuthController::login');
    $routes->post('auth/refresh', 'AuthController::refresh');

    $routes->group('', ['filter' => 'jwtAuth'], function ($routes) {
        $routes->post('auth/logout', 'AuthController::logout');

        $routes->get('users/me', 'UsersController::me');

        $routes->group('users', ['filter' => 'role:ADMIN'], function ($routes) {
            $routes->post('/', 'UsersController::invite');
            $routes->get('/', 'UsersController::index');
            $routes->put('(:num)', 'UsersController::update/$1');
        });

        // §16 — folders
        $routes->post('folders', 'FoldersController::create');
        $routes->get('folders/(:any)/children', 'FoldersController::children/$1');
        $routes->get('folders/children', 'FoldersController::children'); // root, no :id segment
        $routes->put('folders/(:num)', 'FoldersController::update/$1');
        $routes->delete('folders/(:num)', 'FoldersController::delete/$1');
        $routes->post('folders/(:num)/restore', 'FoldersController::restore/$1');

        // §17 — documents, upload flow, versions
        $routes->post('documents/uploads/initiate', 'DocumentsController::initiateUpload');
        $routes->post('documents/uploads/complete', 'DocumentsController::completeUpload');
        $routes->get('documents/(:num)', 'DocumentsController::show/$1');
        $routes->put('documents/(:num)', 'DocumentsController::update/$1');
        $routes->delete('documents/(:num)', 'DocumentsController::delete/$1');
        $routes->post('documents/(:num)/restore', 'DocumentsController::restore/$1');
        $routes->post('documents/(:num)/versions/initiate', 'DocumentsController::initiateVersion/$1');
        $routes->post('documents/(:num)/versions/complete', 'DocumentsController::completeVersion/$1');
        $routes->get('documents/(:num)/versions', 'DocumentsController::versions/$1');
    });
});
