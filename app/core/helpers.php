<?php
// FILE: /app/core/helpers.php

/**
 * Small global helper functions used by views and services.
 */

/** Escape a value for safe HTML output. */
function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Read a config value using "file.key" notation, e.g. config('app.name'). */
function config($key, $default = null) {
    static $cache = [];
    $parts = explode('.', $key);
    $file = array_shift($parts);
    if (!isset($cache[$file])) {
        $path = APP_ROOT . '/config/' . basename($file) . '.php';
        $cache[$file] = is_file($path) ? require $path : [];
    }
    $value = $cache[$file];
    foreach ($parts as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

function app_debug() {
    return getenv('APP_DEBUG') === 'true';
}

function storage_path($path = '') {
    return APP_ROOT . '/storage' . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

/** Hidden CSRF input for forms. */
function csrf_field() {
    return CSRF::field();
}

/** Re-populate a form field after a validation error. */
function old($key, $default = '') {
    static $old = null;
    if ($old === null) {
        $session = new Session();
        $old = $session->get('_old_input', []);
        $session->remove('_old_input');
    }
    return isset($old[$key]) ? $old[$key] : $default;
}

/** Format a stored UTC datetime for display. */
function format_date($value, $format = 'M d, Y H:i') {
    if (empty($value)) {
        return '-';
    }
    $ts = strtotime($value);
    return $ts ? date($format, $ts) : '-';
}

/** Build a URL keeping the current query string but overriding some params. */
function query_url($overrides = []) {
    $params = array_merge($_GET, $overrides);
    foreach ($params as $k => $v) {
        if ($v === null || $v === '') {
            unset($params[$k]);
        }
    }
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    return $path . (empty($params) ? '' : '?' . http_build_query($params));
}

/** Decode a JSON column into an array (never null). */
function json_array($value) {
    if (is_array($value)) {
        return $value;
    }
    $decoded = json_decode((string) $value, true);
    return is_array($decoded) ? $decoded : [];
}

/** Percentage helper for usage bars. */
function percent($used, $limit) {
    if ((int) $limit <= 0) {
        return 0;
    }
    return (int) min(100, round(($used / $limit) * 100));
}

/** Validation errors flashed by Controller::failValidation(), keyed by field. */
function form_errors() {
    static $errors = null;
    if ($errors === null) {
        $errors = (new Session())->getFlash('errors', []);
        if (!is_array($errors)) {
            $errors = [];
        }
    }
    return $errors;
}

/** Inline error message HTML for one form field. */
function field_error($field) {
    $errors = form_errors();
    if (empty($errors[$field])) {
        return '';
    }
    return '<div class="field-error">' . e(implode(' ', (array) $errors[$field])) . '</div>';
}

function selected($a, $b) {
    return (string) $a === (string) $b ? ' selected' : '';
}

function checked($condition) {
    return $condition ? ' checked' : '';
}

/** Coloured badge for a status value. */
function badge($status) {
    return '<span class="badge badge-' . e(preg_replace('/[^a-z_]/', '', strtolower((string) $status))) . '">'
        . e(str_replace('_', ' ', (string) $status)) . '</span>';
}

/** Render a partial from /app/views/partials. */
function partial($name, $data = []) {
    View::partial($name, $data);
}

/** Small POST form button with CSRF token and confirmation prompt. */
function post_button($action, $label, $class = 'btn btn-small btn-danger', $confirm = 'Are you sure?', $fields = []) {
    $html = '<form method="POST" action="' . e($action) . '" class="inline-form"'
        . ($confirm ? ' data-confirm="' . e($confirm) . '"' : '') . '>' . CSRF::field();
    foreach ($fields as $name => $value) {
        $html .= '<input type="hidden" name="' . e($name) . '" value="' . e($value) . '">';
    }
    return $html . '<button type="submit" class="' . e($class) . '">' . e($label) . '</button></form>';
}

/** Human readable seconds, e.g. 75 -> "1m 15s". */
function duration($seconds) {
    if ($seconds === null) {
        return '-';
    }
    $seconds = (int) round($seconds);
    if ($seconds < 60) {
        return $seconds . 's';
    }
    if ($seconds < 3600) {
        return floor($seconds / 60) . 'm ' . ($seconds % 60) . 's';
    }
    return floor($seconds / 3600) . 'h ' . floor(($seconds % 3600) / 60) . 'm';
}

/** Safe JSON for embedding in an HTML attribute. */
function json_attr($data) {
    return e(json_encode($data));
}

/**
 * Return the URL only if it is http(s) or a local path; otherwise "#".
 * Use for every user-controlled href (blocks javascript:, data:, etc.).
 */
function safe_url($url) {
    $url = trim((string) $url);
    if ($url !== '' && $url[0] === '/' && (strlen($url) === 1 || $url[1] !== '/')) {
        return $url;
    }
    return preg_match('#^https?://#i', $url) && filter_var($url, FILTER_VALIDATE_URL) ? $url : '#';
}
