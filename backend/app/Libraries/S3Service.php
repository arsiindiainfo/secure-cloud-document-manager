<?php

namespace App\Libraries;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Config\Aws as AwsConfig;

/**
 * The only code path allowed to talk to the AWS SDK (§5). Every presigned
 * URL is scoped to one exact object key with a short TTL (§10) — this class
 * never returns anything broader (no ListBucket, no bucket-wide access).
 */
class S3Service
{
    private readonly S3Client $client;

    public function __construct(private readonly AwsConfig $config = new AwsConfig())
    {
        $args = [
            'version'     => 'latest',
            'region'      => $this->config->region,
            'credentials' => ['key' => $this->config->key, 'secret' => $this->config->secret],
        ];

        // LocalStack needs an explicit endpoint + path-style addressing
        // (virtual-hosted-style bucket subdomains don't resolve locally).
        if ($this->config->endpoint !== '') {
            $args['endpoint']                = $this->config->endpoint;
            $args['use_path_style_endpoint']  = true;
        }

        $this->client = new S3Client($args);
    }

    public function presignPut(string $key, string $contentType): string
    {
        $command = $this->client->getCommand('PutObject', [
            'Bucket'      => $this->config->documentsBucket,
            'Key'         => $key,
            'ContentType' => $contentType,
        ]);

        return (string) $this->client
            ->createPresignedRequest($command, "+{$this->config->presignTtlSeconds} seconds")
            ->getUri();
    }

    public function presignGet(string $key, ?string $responseContentDisposition = null): string
    {
        $params = ['Bucket' => $this->config->documentsBucket, 'Key' => $key];
        if ($responseContentDisposition !== null) {
            $params['ResponseContentDisposition'] = $responseContentDisposition;
        }

        $command = $this->client->getCommand('GetObject', $params);

        return (string) $this->client
            ->createPresignedRequest($command, "+{$this->config->presignTtlSeconds} seconds")
            ->getUri();
    }

    /** @return array{sizeBytes: int, etag: string}|null null if the object doesn't exist */
    public function headObject(string $key): ?array
    {
        try {
            $result = $this->client->headObject([
                'Bucket' => $this->config->documentsBucket,
                'Key'    => $key,
            ]);
        } catch (S3Exception $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }

            throw $e;
        }

        return [
            'sizeBytes' => (int) $result['ContentLength'],
            'etag'      => trim((string) $result['ETag'], '"'),
        ];
    }
}
