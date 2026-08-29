<?php

namespace App\Controllers;

use App\DTOs\PaginationRequestDTO;
use App\Services\UsersService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * §15 — user management. ADMIN-only except `me`, enforced via the `role`
 * route filter (app/Config/Routes.php), not in this Controller.
 */
class UsersController extends BaseController
{
    public function me(): ResponseInterface
    {
        return $this->ok((new UsersService())->me());
    }

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

    public function index(): ResponseInterface
    {
        $pagination = PaginationRequestDTO::fromRequest($this->request, ['createdAt', 'name'], 'createdAt');

        $result = (new UsersService())->list($pagination->page, $pagination->limit);

        return $this->paginated($result['items'], $pagination->meta($result['total']));
    }

    public function update(int $id): ResponseInterface
    {
        $data = $this->validated('usersUpdate');

        $user = (new UsersService())->updateRoleStatus($id, $data['role'] ?? null, $data['status'] ?? null);

        return $this->ok($user);
    }
}
