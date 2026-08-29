<?php

namespace App\Exceptions;

use CodeIgniter\Exceptions\HTTPExceptionInterface;
use RuntimeException;

/**
 * Base of every typed exception in the app. Carries the HTTP status and
 * machine-readable error code the global exception handler needs to build
 * the standard error envelope (§13.3, §14) — no call site ever builds that
 * envelope by hand. Implements HTTPExceptionInterface so CodeIgniter's own
 * Debug\Exceptions dispatcher reads the HTTP status straight off getCode()
 * before handing off to our ApiExceptionHandler (Config\Exceptions::handler()).
 */
class ApiException extends RuntimeException implements HTTPExceptionInterface
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
}
