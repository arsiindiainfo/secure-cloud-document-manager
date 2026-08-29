<?php

/**
 * PHPStan-only bootstrap: CI4's real request lifecycle defines these
 * constants in public/index.php before anything else loads, and its global
 * helper functions (env, service, config, view, log_message, is_cli, ...)
 * live in system/Common.php. Neither is available to a static analyzer
 * running outside that lifecycle, so this file recreates just enough of it
 * for PHPStan to resolve real function signatures instead of flagging
 * every framework helper call as undefined.
 */

if (! defined('APPPATH')) {
    define('APPPATH', __DIR__ . '/app/');
}
if (! defined('ROOTPATH')) {
    define('ROOTPATH', __DIR__ . '/');
}
if (! defined('FCPATH')) {
    define('FCPATH', __DIR__ . '/public/');
}
if (! defined('WRITEPATH')) {
    define('WRITEPATH', __DIR__ . '/writable/');
}
if (! defined('SYSTEMPATH')) {
    define('SYSTEMPATH', __DIR__ . '/vendor/codeigniter4/framework/system/');
}
if (! defined('ENVIRONMENT')) {
    define('ENVIRONMENT', 'testing');
}
if (! defined('CI_DEBUG')) {
    define('CI_DEBUG', 1);
}
if (! defined('APP_NAMESPACE')) {
    define('APP_NAMESPACE', 'App');
}

require_once SYSTEMPATH . 'Common.php';
