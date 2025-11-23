<?php
// FILE: /app/core/Request.php

/**
 * HTTP Request Handler
 *
 * Provides methods to access request data safely.
 */
class Request {

    /**
     * Get all input data
     *
     * @return array
     */
    public function all() {
        return array_merge($_GET, $_POST);
    }

    /**
     * Get value from GET parameters
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get($key, $default = null) {
        return isset($_GET[$key]) ? $this->clean($_GET[$key]) : $default;
    }

    /**
     * Get value from POST parameters
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function post($key, $default = null) {
        return isset($_POST[$key]) ? $this->clean($_POST[$key]) : $default;
    }

    /**
     * Get value from request (checks POST then GET)
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function input($key, $default = null) {
        if (isset($_POST[$key])) {
            return $this->clean($_POST[$key]);
        }
        if (isset($_GET[$key])) {
            return $this->clean($_GET[$key]);
        }
        return $default;
    }

    /**
     * Check if key exists in request
     *
     * @param string $key
     * @return bool
     */
    public function has($key) {
        return isset($_GET[$key]) || isset($_POST[$key]);
    }

    /**
     * Get request method
     *
     * @return string
     */
    public function method() {
        return $_SERVER['REQUEST_METHOD'];
    }

    /**
     * Check if request is POST
     *
     * @return bool
     */
    public function isPost() {
        return $this->method() === 'POST';
    }

    /**
     * Check if request is GET
     *
     * @return bool
     */
    public function isGet() {
        return $this->method() === 'GET';
    }

    /**
     * Check if request is AJAX
     *
     * @return bool
     */
    public function isAjax() {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Get uploaded file
     *
     * @param string $key
     * @return array|null
     */
    public function file($key) {
        return isset($_FILES[$key]) ? $_FILES[$key] : null;
    }

    /**
     * Get JSON body content
     *
     * @return array|null
     */
    public function json() {
        $body = file_get_contents('php://input');
        return json_decode($body, true);
    }

    /**
     * Get request URI
     *
     * @return string
     */
    public function uri() {
        return parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    }

    /**
     * Get full URL
     *
     * @return string
     */
    public function url() {
        return $_SERVER['REQUEST_URI'];
    }

    /**
     * Get client IP address
     *
     * @return string
     */
    public function ip() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        return $_SERVER['REMOTE_ADDR'];
    }

    /**
     * Clean input data (basic sanitization)
     *
     * @param mixed $data
     * @return mixed
     */
    private function clean($data) {
        if (is_array($data)) {
            return array_map([$this, 'clean'], $data);
        }
        return trim($data);
    }
}
