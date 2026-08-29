<?php

namespace App\Controllers;

use App\Services\AuthService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * §15 — public auth endpoints. Thin per §5: validate, call one Service
 * method, map the result to the envelope.
 */
class AuthController extends BaseController
{
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

    public function refresh(): ResponseInterface
    {
        $data = $this->validated('authRefresh');

        $result = (new AuthService())->refresh($data['refreshToken']);

        return $this->ok($result);
    }

    public function logout(): ResponseInterface
    {
        $data = $this->validated('authRefresh');

        (new AuthService())->logout($data['refreshToken']);

        return $this->ok(['loggedOut' => true]);
    }
}
