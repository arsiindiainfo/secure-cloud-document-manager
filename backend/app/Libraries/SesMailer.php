<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Libraries;

use Aws\Ses\Exception\SesException;
use Aws\Ses\SesClient;
use Config\Aws as AwsConfig;
use Config\Email as EmailConfig;

/**
 * Sends transactional email via AWS SES instead of CI4's SMTP-backed
 * Email service. Sending as a personal Gmail address with a spoofed
 * product display name reads as a phishing signal and lands in spam;
 * SES lets every outbound email go out as noreply@arsiindiainfo.com,
 * a domain with a verified SES identity and passing DKIM (the same
 * setup email-campaign-delivery-tracker already relies on).
 */
class SesMailer
{
    private readonly SesClient $client;

    public function __construct(
        private readonly AwsConfig $awsConfig = new AwsConfig(),
        private readonly EmailConfig $emailConfig = new EmailConfig(),
    ) {
        $args = [
            'version' => 'latest',
            'region'  => $this->awsConfig->sesRegion !== '' ? $this->awsConfig->sesRegion : $this->awsConfig->region,
        ];

        // Only pass explicit credentials when actually configured (local
        // dev against a mocked endpoint). Omitting this in production lets
        // the AWS SDK fall back to the EC2 instance's IAM role, same as
        // S3Service.
        if ($this->awsConfig->key !== '' && $this->awsConfig->secret !== '') {
            $args['credentials'] = ['key' => $this->awsConfig->key, 'secret' => $this->awsConfig->secret];
        }

        if ($this->awsConfig->endpoint !== '') {
            $args['endpoint'] = $this->awsConfig->endpoint;
        }

        $this->client = new SesClient($args);
    }

    public function send(string $to, string $subject, string $html): bool
    {
        try {
            $this->client->sendEmail([
                'Source'      => "{$this->emailConfig->fromName} <{$this->emailConfig->fromEmail}>",
                'Destination' => ['ToAddresses' => [$to]],
                'Message'     => [
                    'Subject' => ['Data' => $subject, 'Charset' => 'UTF-8'],
                    'Body'    => ['Html' => ['Data' => $html, 'Charset' => 'UTF-8']],
                ],
            ]);

            return true;
        } catch (SesException $e) {
            $reason = $e->getAwsErrorMessage() ?? $e->getMessage();
            log_message('error', "SES send failed: {$reason}");

            return false;
        }
    }
}
