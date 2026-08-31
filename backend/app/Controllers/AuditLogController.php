<?php

namespace App\Controllers;

use App\DTOs\PaginationRequestDTO;
use App\Models\AuditLogModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * §19 — GET /audit-logs. ADMIN only (enforced by the route's role:ADMIN
 * filter, see app/Config/Routes.php) — no per-resource ACL applies here.
 */
class AuditLogController extends BaseController
{
    public function index(): ResponseInterface
    {
        $pagination = PaginationRequestDTO::fromRequest($this->request, ['createdAt'], 'createdAt');

        $entityId = $this->request->getGet('entityId');
        $userId   = $this->request->getGet('userId');

        $result = (new AuditLogModel())->paginatedList(
            $this->request->getGet('entityType') ?: null,
            $entityId !== null ? (int) $entityId : null,
            $userId !== null ? (int) $userId : null,
            $this->request->getGet('action') ?: null,
            $this->request->getGet('dateFrom') ?: null,
            $this->request->getGet('dateTo') ?: null,
            $pagination->page,
            $pagination->limit,
        );

        return $this->paginated($result['items'], $pagination->meta($result['total']));
    }
}
