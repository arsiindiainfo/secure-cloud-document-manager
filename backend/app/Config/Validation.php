<?php

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

    /** @var array<string, string> */
    public array $authLogin = [
        'email'    => 'required|valid_email',
        'password' => 'required',
    ];

    /** @var array<string, string> */
    public array $authRefresh = [
        'refreshToken' => 'required|string',
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
}
