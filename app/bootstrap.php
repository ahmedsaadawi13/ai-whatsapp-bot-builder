<?php
// FILE: /app/bootstrap.php

/**
 * AI WhatsApp Bot Builder - Application Bootstrap
 *
 * Shared by the web front controller, cron scripts and tests:
 * loads .env, sets UTC, and registers a class autoloader.
 */

define('APP_ROOT', dirname(__DIR__));

date_default_timezone_set('UTC');

// Load .env (KEY=VALUE lines, # comments). Real environment variables win.
$envFile = APP_ROOT . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim(trim($value), "\"'");
        if (getenv($key) === false) {
            putenv($key . '=' . $value);
        }
    }
}

// Autoload classes from core, services, models and controllers.
spl_autoload_register(function ($class) {
    if (!preg_match('/^[A-Za-z0-9_]+$/', $class)) {
        return;
    }
    foreach (['core', 'services', 'models', 'controllers'] as $dir) {
        $file = APP_ROOT . '/app/' . $dir . '/' . $class . '.php';
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});

require_once APP_ROOT . '/app/core/helpers.php';
