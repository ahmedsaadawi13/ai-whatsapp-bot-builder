<?php
// FILE: /app/core/CSRF.php

/**
 * CSRF Protection
 *
 * Generates and validates CSRF tokens to prevent cross-site request forgery.
 */
class CSRF {

    /**
     * Generate CSRF token
     *
     * @return string
     */
    public static function generate() {
        $session = new Session();

        if (!$session->has('csrf_token')) {
            $token = bin2hex(random_bytes(32));
            $session->set('csrf_token', $token);
        }

        return $session->get('csrf_token');
    }

    /**
     * Validate CSRF token
     *
     * @param string $token
     * @return bool
     */
    public static function validate($token) {
        $session = new Session();
        $sessionToken = $session->get('csrf_token');

        if (!$sessionToken || !$token) {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }

    /**
     * Get CSRF token input field HTML
     *
     * @return string
     */
    public static function field() {
        $token = self::generate();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }

    /**
     * Get CSRF token meta tag HTML
     *
     * @return string
     */
    public static function meta() {
        $token = self::generate();
        return '<meta name="csrf-token" content="' . htmlspecialchars($token) . '">';
    }
}
