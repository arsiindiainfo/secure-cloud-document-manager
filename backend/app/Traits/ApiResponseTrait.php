<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Traits;

use CodeIgniter\HTTP\ResponseInterface;

/**
 * Builds the standard success envelope (§13.1, §13.2). Used by every
 * Controller — errors never go through here, they're thrown as ApiException
 * subclasses and rendered by ApiExceptionHandler (§13.3) instead, so a
 * Controller action either returns one of these or throws.
 */
trait ApiResponseTrait
{
    protected function ok(mixed $data, int $status = 200): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /**
     * @param array<int, mixed> $items
     * @param array{page: int, limit: int, total: int} $rawMeta
     */
    protected function paginated(array $items, array $rawMeta, int $status = 200): ResponseInterface
    {
        $totalPages = $rawMeta['limit'] > 0 ? (int) ceil($rawMeta['total'] / $rawMeta['limit']) : 0;

        return $this->response->setStatusCode($status)->setJSON([
            'success' => true,
            'data'    => $items,
            'meta'    => [
                'page'       => $rawMeta['page'],
                'limit'      => $rawMeta['limit'],
                'total'      => $rawMeta['total'],
                'totalPages' => $totalPages,
            ],
        ]);
    }

    protected function created(mixed $data): ResponseInterface
    {
        return $this->ok($data, 201);
    }

    protected function noContent(): ResponseInterface
    {
        return $this->response->setStatusCode(204);
    }
}

