<?php

namespace App\Controllers;

use App\Services\ProcessingService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * §9.3 — internal, HMAC-signed (InternalHmacFilter), never a user JWT. The
 * Lambda worker calls this once after thumbnail/metadata/scan processing.
 */
class ProcessingCallbackController extends BaseController
{
    public function callback(): ResponseInterface
    {
        $data = $this->validated('processingCallback');

        (new ProcessingService())->handleCallback(
            $data['s3Key'],
            $data['status'],
            $data['scanResult'] ?? null,
            $data['metadata'] ?? null,
            $data['thumbnailS3Key'] ?? null,
            $data['errorMessage'] ?? null,
        );

        return $this->ok(['received' => true]);
    }
}
