<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Controllers;

use App\Exceptions\ValidationException;
use App\Traits\ApiResponseTrait;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 *
 * Extend this class in any new controllers:
 * ```
 *     class Home extends BaseController
 * ```
 *
 * For security, be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    use ApiResponseTrait;

    /**
     * Runs the named §5 rule group and returns the validated data. Every
     * Controller action calls this before touching a Service — no
     * unvalidated input reaches business logic.
     *
     * @return array<string, mixed>
     */
    protected function validated(string $ruleGroup): array
    {
        $input = $this->request->getJSON(true) ?? $this->request->getPost();

        if (! $this->validateData($input, $ruleGroup)) {
            $details = [];
            foreach ($this->validator->getErrors() as $field => $message) {
                $details[] = ['field' => $field, 'message' => $message];
            }

            throw new ValidationException($details);
        }

        return $input;
    }
    /**
     * Be sure to declare properties for any property fetch you initialized.
     * The creation of dynamic property is deprecated in PHP 8.2.
     */

    // protected $session;

    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Load here all helpers you want to be available in your controllers that extend BaseController.
        // Caution: Do not put the this below the parent::initController() call below.
        // $this->helpers = ['form', 'url'];

        // Caution: Do not edit this line.
        parent::initController($request, $response, $logger);

        // Preload any models, libraries, etc, here.
        // $this->session = service('session');
    }
}

