<?php

namespace App\Exceptions;

class ParentFolderNotFoundException extends ApiException
{
    public function __construct(string $message = 'Restore the parent folder first.')
    {
        parent::__construct(422, 'PARENT_FOLDER_NOT_FOUND', $message);
    }
}
