<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

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
    });
});
