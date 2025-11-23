<?php
// FILE: /app/core/Session.php

/**
 * Session Manager
 *
 * Handles session operations with security measures.
 */
class Session {

    /**
     * Start session if not already started
     */
    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Set session value
     *
     * @param string $key
     * @param mixed $value
     */
    public function set($key, $value) {
        $_SESSION[$key] = $value;
    }

    /**
     * Get session value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get($key, $default = null) {
        return isset($_SESSION[$key]) ? $_SESSION[$key] : $default;
    }

    /**
     * Check if session key exists
     *
     * @param string $key
     * @return bool
     */
    public function has($key) {
        return isset($_SESSION[$key]);
    }

    /**
     * Remove session value
     *
     * @param string $key
     */
    public function remove($key) {
        unset($_SESSION[$key]);
    }

    /**
     * Set flash message
     *
     * @param string $key
     * @param mixed $value
     */
    public function flash($key, $value) {
        $_SESSION['flash'][$key] = $value;
    }

    /**
     * Get flash message (removes after retrieval)
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getFlash($key, $default = null) {
        if (isset($_SESSION['flash'][$key])) {
            $value = $_SESSION['flash'][$key];
            unset($_SESSION['flash'][$key]);
            return $value;
        }
        return $default;
    }

    /**
     * Get all flash messages
     *
     * @return array
     */
    public function getAllFlash() {
        $flash = isset($_SESSION['flash']) ? $_SESSION['flash'] : [];
        unset($_SESSION['flash']);
        return $flash;
    }

    /**
     * Destroy session
     */
    public function destroy() {
        session_destroy();
        $_SESSION = [];
    }

    /**
     * Regenerate session ID (prevents session fixation)
     */
    public function regenerate() {
        session_regenerate_id(true);
    }
}
