<?php

namespace App\Controllers;

use App\Services\DocumentService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * §17 — documents, the three-step upload flow, and versions. Thin per §5.
 */
class DocumentsController extends BaseController
{
    public function initiateUpload(): ResponseInterface
    {
        $data = $this->validated('uploadInitiate');

        $result = (new DocumentService())->initiateUpload(
            (int) $data['folderId'],
            $data['fileName'],
            $data['mimeType'],
            (int) $data['sizeBytes'],
        );

        return $this->ok($result);
    }

    public function completeUpload(): ResponseInterface
    {
        $data = $this->validated('uploadComplete');

        $document = (new DocumentService())->completeUpload(
            (int) $data['folderId'],
            $data['s3Key'],
            $data['name'],
            $data['description'] ?? null,
            $data['tags'] ?? null,
            $data['checksumSha256'],
        );

        return $this->created($document);
    }

    public function show(int $id): ResponseInterface
    {
        $detail = (new DocumentService())->getDetail($id);

        return $this->ok($detail);
    }

    public function update(int $id): ResponseInterface
    {
        $data = $this->validated('documentUpdate');

        $document = (new DocumentService())->update(
            $id,
            $data['name'] ?? null,
            $data['description'] ?? null,
            $data['tags'] ?? null,
            isset($data['folderId']) ? (int) $data['folderId'] : null,
        );

        return $this->ok($document);
    }

    public function delete(int $id): ResponseInterface
    {
        (new DocumentService())->softDelete($id);

        return $this->ok(['deleted' => true]);
    }

    public function restore(int $id): ResponseInterface
    {
        (new DocumentService())->restore($id);

        return $this->ok(['restored' => true]);
    }

    public function initiateVersion(int $id): ResponseInterface
    {
        $data = $this->validated('versionInitiate');

        $result = (new DocumentService())->initiateVersion($id, $data['fileName'], $data['mimeType'], (int) $data['sizeBytes']);

        return $this->ok($result);
    }

    public function completeVersion(int $id): ResponseInterface
    {
        $data = $this->validated('versionComplete');

        $result = (new DocumentService())->completeVersion($id, $data['s3Key'], $data['checksumSha256']);

        return $this->created($result);
    }

    public function versions(int $id): ResponseInterface
    {
        $versions = (new DocumentService())->listVersions($id);

        return $this->ok($versions);
    }
}
