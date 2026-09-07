<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * S3 region/bucket/endpoint config, sourced from .env — the only place these
 * values are allowed to be hard-coded is here (§5 "Logging & configuration").
 * S3Service is the only class that reads this config.
 */
class Aws extends BaseConfig
{
    public string $region = '';

    /**
     * LocalStack endpoint for local dev (e.g. http://127.0.0.1:4566).
     * Left empty to use the real AWS endpoint.
     */
    public string $endpoint = '';

    public string $key    = '';
    public string $secret = '';

    public string $documentsBucket = '';
    public string $spaBucket       = '';

    /** Presigned URL lifetime, in seconds — 5 minutes per §10. */
    public int $presignTtlSeconds = 300;

    /**
     * SES's sending region can differ from where S3/EC2 live — the
     * arsiindiainfo.com domain identity + DKIM are verified in ap-south-2,
     * regardless of which region $region above points at. Falls back to
     * $region when unset, so a single-region deployment needs no extra
     * .env entry.
     */
    public string $sesRegion = '';

    public function __construct()
    {
        parent::__construct();

        $this->region            = (string) env('aws.region', 'us-east-1');
        $this->endpoint          = (string) env('aws.endpoint', '');
        $this->key               = (string) env('aws.key', '');
        $this->secret            = (string) env('aws.secret', '');
        $this->documentsBucket   = (string) env('aws.documentsBucket', '');
        $this->spaBucket         = (string) env('aws.spaBucket', '');
        $this->presignTtlSeconds = (int) env('aws.presignTtlSeconds', 300);
        $this->sesRegion         = (string) env('aws.sesRegion', '');
    }
}

