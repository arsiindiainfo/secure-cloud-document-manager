<?php

namespace App\Libraries;

use App\Exceptions\ApiException;
use App\Exceptions\InternalErrorException;
use CodeIgniter\Debug\ExceptionHandlerInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

/**
 * Converts any thrown exception — typed ApiException, CI4 validation
 * failure, or truly unexpected — into the one standard error envelope
 * (§13.3, §5 "Exception handling"). This backend is API-only, so every
 * response this handler produces is JSON; there is no HTML error path.
 */
class ApiExceptionHandler implements ExceptionHandlerInterface
{
    public function handle(
        Throwable $exception,
        RequestInterface $request,
        ResponseInterface $response,
        int $statusCode,
        int $exitCode,
    ): void {
        if ($exception instanceof ApiException) {
            $errorCode = $exception->getErrorCode();
            $message   = $exception->getMessage();
            $details   = $exception->getDetails();
            $statusCode = $exception->getHttpStatus();
        } else {
            // Unexpected error — logged with a correlation id, no internals
            // leaked to the client (§5).
            $correlationId = $request->getHeaderLine('X-Request-Id') ?: bin2hex(random_bytes(8));
            log_message('critical', '[{correlationId}] ' . $exception::class . ': ' . $exception->getMessage() . "\n" . $exception->getTraceAsString(), [
                'correlationId' => $correlationId,
            ]);

            $fallback   = new InternalErrorException();
            $errorCode  = $fallback->getErrorCode();
            $message    = $fallback->getMessage();
            $details    = null;
            $statusCode = $fallback->getHttpStatus();
        }

        $body = [
            'success' => false,
            'error'   => array_filter([
                'code'    => $errorCode,
                'message' => $message,
                'details' => $details,
            ], static fn ($value) => $value !== null),
        ];

        $response
            ->setStatusCode($statusCode)
            ->setJSON($body)
            ->send();

        if (ENVIRONMENT !== 'testing') {
            // @codeCoverageIgnoreStart
            exit($exitCode);
            // @codeCoverageIgnoreEnd
        }
    }
}
