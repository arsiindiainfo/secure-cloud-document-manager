<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\DTOs;

use CodeIgniter\HTTP\IncomingRequest;

/**
 * Shared page/limit/sort/search parsing for every list endpoint (§5, §24) —
 * one place enforces "page >= 1, limit 1-100" so every resource's list
 * endpoint behaves identically to the frontend's shared DataTable/grid.
 */
final class PaginationRequestDTO
{
    public function __construct(
        public readonly int $page,
        public readonly int $limit,
        public readonly ?string $search,
        public readonly ?string $sort,
        public readonly string $direction,
    ) {
    }

    /** @param list<string> $allowedSorts */
    public static function fromRequest(IncomingRequest $request, array $allowedSorts, string $defaultSort): self
    {
        $page  = max(1, (int) ($request->getGet('page') ?? 1));
        $limit = (int) ($request->getGet('limit') ?? 20);
        $limit = max(1, min(100, $limit));

        $sort      = $request->getGet('sort');
        $sort      = in_array($sort, $allowedSorts, true) ? $sort : $defaultSort;
        $direction = $request->getGet('direction') === 'asc' ? 'asc' : 'desc';

        $search = $request->getGet('search');
        $search = $search === null || $search === '' ? null : $search;

        return new self($page, $limit, $search, $sort, $direction);
    }

    /** @return array{page: int, limit: int, total: int} */
    public function meta(int $total): array
    {
        return ['page' => $this->page, 'limit' => $this->limit, 'total' => $total];
    }
}

