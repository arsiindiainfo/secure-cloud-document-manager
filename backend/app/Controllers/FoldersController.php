<?php

namespace App\Controllers;

use App\Services\FolderService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * §16 — folder CRUD. Thin per §5: validate, call one Service method, map
 * the result to the envelope.
 */
class FoldersController extends BaseController
{
    public function create(): ResponseInterface
    {
        $data = $this->validated('folderCreate');

        $folder = (new FolderService())->create($data['name'], isset($data['parentFolderId']) ? (int) $data['parentFolderId'] : null);

        return $this->created($folder);
    }

    /** `$idParam` is either a numeric folder id or the literal "root" (§16). */
    public function children(string $idParam = 'root'): ResponseInterface
    {
        $folderId = $idParam === 'root' ? null : (int) $idParam;

        $result = (new FolderService())->listChildren($folderId);

        return $this->ok([
            'folders'    => $result['folders'],
            'documents'  => $result['documents'],
            'breadcrumb' => $result['breadcrumb'],
        ]);
    }

    public function update(int $id): ResponseInterface
    {
        $data = $this->validated('folderUpdate');

        // array_key_exists (not isset) — a client sending parentFolderId: null
        // (move to root) must be distinguished from omitting the field
        // entirely (rename only); both look identical to isset().
        $moveRequested  = array_key_exists('parentFolderId', $data);
        $parentFolderId = $moveRequested && $data['parentFolderId'] !== null ? (int) $data['parentFolderId'] : null;

        $folder = (new FolderService())->update($id, $data['name'], $parentFolderId, $moveRequested);

        return $this->ok($folder);
    }

    public function delete(int $id): ResponseInterface
    {
        (new FolderService())->softDelete($id);

        return $this->ok(['deleted' => true]);
    }

    public function restore(int $id): ResponseInterface
    {
        (new FolderService())->restore($id);

        return $this->ok(['restored' => true]);
    }
}
