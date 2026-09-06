<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace Config;

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Validation\StrictRules\CreditCardRules;
use CodeIgniter\Validation\StrictRules\FileRules;
use CodeIgniter\Validation\StrictRules\FormatRules;
use CodeIgniter\Validation\StrictRules\Rules;

class Validation extends BaseConfig
{
    // --------------------------------------------------------------------
    // Setup
    // --------------------------------------------------------------------

    /**
     * Stores the classes that contain the
     * rules that are available.
     *
     * @var list<string>
     */
    public array $ruleSets = [
        Rules::class,
        FormatRules::class,
        FileRules::class,
        CreditCardRules::class,
    ];

    /**
     * Specifies the views that are used to display the
     * errors.
     *
     * @var array<string, string>
     */
    public array $templates = [
        'list'   => 'CodeIgniter\Validation\Views\list',
        'single' => 'CodeIgniter\Validation\Views\single',
    ];

    // --------------------------------------------------------------------
    // Rules (§5 "Every endpoint declares a named rule group" — one group
    // per endpoint, named after it; Controllers call $this->validateData()
    // against these before ever touching a Service).
    // --------------------------------------------------------------------

    // recaptchaToken is intentionally not `required` here — a missing/empty
    // token is rejected by RecaptchaVerifier itself (§15), which also lets
    // the `testing` environment skip real Google verification without
    // every existing login test needing to fabricate a token.
    /** @var array<string, string> */
    public array $authLogin = [
        'email'          => 'required|valid_email',
        'password'       => 'required',
        'recaptchaToken' => 'permit_empty|string',
    ];

    /** @var array<string, string> */
    public array $authRefresh = [
        'refreshToken' => 'required|string',
    ];

    /** @var array<string, string> */
    public array $authRegister = [
        'name'           => 'required|min_length[2]|max_length[120]',
        'email'          => 'required|valid_email|is_unique[users.email]',
        'password'       => 'required|min_length[8]',
        'recaptchaToken' => 'permit_empty|string',
    ];

    /** @var array<string, string> */
    public array $authVerifyEmail = [
        'token' => 'required|string|exact_length[43]',
    ];

    /** @var array<string, string> */
    public array $usersInvite = [
        'name'  => 'required|min_length[2]|max_length[120]',
        'email' => 'required|valid_email|is_unique[users.email]',
        'role'  => 'required|in_list[ADMIN,MANAGER,EMPLOYEE]',
    ];

    /** @var array<string, string> */
    public array $usersUpdate = [
        'role'   => 'permit_empty|in_list[ADMIN,MANAGER,EMPLOYEE]',
        'status' => 'permit_empty|in_list[ACTIVE,DISABLED]',
    ];

    /** @var array<string, string> */
    public array $usersChangePassword = [
        'currentPassword' => 'required',
        'newPassword'     => 'required|min_length[8]',
    ];

    // Folder/document names may not contain path-separator-like characters
    // — they end up sanitized further still before ever building an S3 key
    // (§5), this just rejects the obviously-invalid case fast.
    private const NAME_RULE = 'required|min_length[1]|max_length[180]|regex_match[/^[^\/\\\\:\*\?"<>\|]+$/]';

    /** @var array<string, string> */
    public array $folderCreate = [
        'name'           => self::NAME_RULE,
        'parentFolderId' => 'permit_empty|is_natural_no_zero',
    ];

    /** @var array<string, string> */
    public array $folderUpdate = [
        'name'           => self::NAME_RULE,
        'parentFolderId' => 'permit_empty|is_natural_no_zero',
    ];

    // mimeType/sizeBytes are intentionally NOT range/list-checked here —
    // failing those needs the specific UNSUPPORTED_FILE_TYPE/FILE_TOO_LARGE
    // codes (§14), not a generic VALIDATION_ERROR, so DocumentService checks
    // them explicitly instead (§17).
    /** @var array<string, string> */
    public array $uploadInitiate = [
        'folderId'  => 'required|is_natural_no_zero',
        'fileName'  => 'required|min_length[1]|max_length[200]',
        'mimeType'  => 'required|string',
        'sizeBytes' => 'required|is_natural_no_zero',
    ];

    /** @var array<string, string> */
    public array $uploadComplete = [
        'folderId'       => 'required|is_natural_no_zero',
        's3Key'          => 'required|string',
        'name'           => 'required|min_length[1]|max_length[200]',
        'description'    => 'permit_empty|max_length[500]',
        'tags'           => 'permit_empty|max_length[255]',
        'checksumSha256' => 'required|exact_length[64]',
    ];

    /** @var array<string, string> */
    public array $versionInitiate = [
        'fileName'  => 'required|min_length[1]|max_length[200]',
        'mimeType'  => 'required|string',
        'sizeBytes' => 'required|is_natural_no_zero',
    ];

    /** @var array<string, string> */
    public array $versionComplete = [
        's3Key'          => 'required|string',
        'checksumSha256' => 'required|exact_length[64]',
    ];

    /** @var array<string, string> */
    public array $documentUpdate = [
        'name'        => 'permit_empty|min_length[1]|max_length[200]',
        'description' => 'permit_empty|max_length[500]',
        'tags'        => 'permit_empty|max_length[255]',
        'folderId'    => 'permit_empty|is_natural_no_zero',
    ];

    /** @var array<string, string> */
    public array $permissionGrant = [
        'email'      => 'required|valid_email',
        'permission' => 'required|in_list[VIEWER,EDITOR,OWNER]',
    ];

    // expiresInHours max 168 = 7 days (§18 "max 7 days for the demo").
    /** @var array<string, string> */
    public array $shareLinkCreate = [
        'permission'     => 'required|in_list[VIEW,DOWNLOAD]',
        'expiresInHours' => 'required|is_natural_no_zero|less_than_equal_to[168]',
        'maxDownloads'   => 'permit_empty|is_natural_no_zero|less_than_equal_to[1000]',
    ];

    /** @var array<string, string> */
    public array $processingCallback = [
        's3Key'          => 'required|string',
        'status'         => 'required|in_list[COMPLETED,FAILED]',
        'scanResult'     => 'permit_empty|in_list[CLEAN,FLAGGED]',
        'thumbnailS3Key' => 'permit_empty|string',
        'errorMessage'   => 'permit_empty|max_length[500]',
    ];
}

