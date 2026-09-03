<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Exceptions;

use CodeIgniter\Exceptions\HTTPExceptionInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\HTTP\ResponsableInterface;
use RuntimeException;

/**
 * Base of every typed exception in the app. Carries the HTTP status and
 * machine-readable error code needed to build the standard error envelope
 * (§13.3, §14) — no call site ever builds that envelope by hand.
 *
 * Implements ResponsableInterface (not just HTTPExceptionInterface) because
 * CodeIgniter\CodeIgniter::run() only specially catches ResponsableInterface
 * and PageNotFoundException inline — everything else re-throws past the
 * request cycle to PHP's global exception handler, which isn't installed in
 * FeatureTestTrait's test context. getResponse() here is what lets a thrown
 * ApiException turn into a normal envelope response in both real requests
 * and feature tests, not just production.
 */
class ApiException extends RuntimeException implements HTTPExceptionInterface, ResponsableInterface
{
    private readonly int $httpStatus;

    /** @param array<int, array{field: string, message: string}>|null $details */
    public function __construct(
        int $httpStatus,
        private readonly string $errorCode,
        string $message,
        private readonly ?array $details = null,
    ) {
        parent::__construct($message, $httpStatus);
        $this->httpStatus = $httpStatus;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /** @return array<int, array{field: string, message: string}>|null */
    public function getDetails(): ?array
    {
        return $this->details;
    }

    public function getResponse(): ResponseInterface
    {
        $body = [
            'success' => false,
            'error'   => array_filter([
                'code'    => $this->errorCode,
                'message' => $this->getMessage(),
                'details' => $this->details,
            ], static fn ($value) => $value !== null),
        ];

        return service('response')->setStatusCode($this->httpStatus)->setJSON($body);
    }
}

