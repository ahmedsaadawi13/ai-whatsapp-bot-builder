<?php
// FILE: /app/core/Auth.php

/**
 * Authentication Manager
 *
 * Session-based login bound to a tenant. Includes simple brute-force
 * throttling (5 failed attempts per email+IP per 15 minutes).
 */
class Auth {

    const MAX_ATTEMPTS = 5;            // per email + IP
    const MAX_ATTEMPTS_PER_EMAIL = 20; // per email across all IPs
    const LOCKOUT_MINUTES = 15;

    private $session;
    private $userModel;

    /** Per-request cache: false = not loaded yet. */
    private static $cachedUser = false;

    /** Last error from attempt(), for the login form. */
    private $lastError = null;

    public function __construct() {
        $this->session = new Session();
        $this->userModel = new User();
    }

    /**
     * Try to log a user in.
     */
    public function attempt($email, $password, $ip = '') {
        $this->lastError = null;
        $email = strtolower(trim($email));

        if ($this->tooManyAttempts($email, $ip)) {
            $this->lastError = 'Too many failed attempts. Try again in ' . self::LOCKOUT_MINUTES . ' minutes.';
            return false;
        }

        $user = $this->userModel->findByEmail($email);

        if (!$user || !password_verify($password, $user['password'])) {
            $this->recordFailure($email, $ip);
            $this->lastError = 'Invalid email or password';
            return false;
        }

        if ($user['status'] !== 'active') {
            $this->lastError = 'Your account is inactive';
            return false;
        }

        if ($user['tenant_id'] !== null) {
            $tenant = (new Tenant())->find($user['tenant_id']);
            if (!$tenant || $tenant['status'] !== 'active') {
                $this->lastError = 'Your organization account is suspended';
                return false;
            }
        }

        $this->clearFailures($email, $ip);
        $this->userModel->updateLastLogin($user['id']);
        $this->login($user);
        return true;
    }

    public function getLastError() {
        return $this->lastError;
    }

    public function login($user) {
        $this->session->regenerate();
        $this->session->set('user_id', (int) $user['id']);
        self::$cachedUser = false;
    }

    /**
     * Log out but keep the session alive so flash messages still work.
     */
    public function logout() {
        $this->session->remove('user_id');
        $this->session->remove('csrf_token');
        $this->session->regenerate();
        self::$cachedUser = null;
    }

    public function check() {
        return $this->user() !== null;
    }

    /**
     * Current user (loaded once per request). Deleted or deactivated
     * users are logged out automatically.
     */
    public function user() {
        if (self::$cachedUser !== false) {
            return self::$cachedUser;
        }

        $userId = $this->session->get('user_id');
        if (!$userId) {
            self::$cachedUser = null;
            return null;
        }

        $user = $this->userModel->find((int) $userId);
        if (!$user || $user['status'] !== 'active') {
            $this->logout();
            return null;
        }
        // A suspended/cancelled tenant loses access immediately, not only at next login.
        if ($user['tenant_id'] !== null) {
            $tenant = (new Tenant())->find($user['tenant_id']);
            if (!$tenant || $tenant['status'] !== 'active') {
                $this->logout();
                return null;
            }
        }

        unset($user['password']);
        self::$cachedUser = $user;
        return $user;
    }

    public function id() {
        $user = $this->user();
        return $user ? (int) $user['id'] : null;
    }

    public function tenantId() {
        $user = $this->user();
        return ($user && $user['tenant_id'] !== null) ? (int) $user['tenant_id'] : null;
    }

    public function hasRole($roles) {
        $user = $this->user();
        if (!$user) {
            return false;
        }
        $roles = is_array($roles) ? $roles : [$roles];
        return in_array($user['role'], $roles, true);
    }

    public function isPlatformAdmin() {
        return $this->hasRole('platform_admin');
    }

    public function isTenantAdmin() {
        return $this->hasRole('tenant_admin');
    }

    /** Reset the per-request cache (used by tests). */
    public static function flushCache() {
        self::$cachedUser = false;
    }

    private function tooManyAttempts($email, $ip) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(ip_address = ?), 0) AS from_ip
                              FROM login_attempts WHERE email = ? AND created_at > ?");
        $stmt->execute([$ip, $email, date('Y-m-d H:i:s', time() - self::LOCKOUT_MINUTES * 60)]);
        $row = $stmt->fetch();
        return (int) $row['from_ip'] >= self::MAX_ATTEMPTS || (int) $row['total'] >= self::MAX_ATTEMPTS_PER_EMAIL;
    }

    private function recordFailure($email, $ip) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("INSERT INTO login_attempts (email, ip_address, created_at) VALUES (?, ?, ?)");
        $stmt->execute([$email, $ip, date('Y-m-d H:i:s')]);
    }

    private function clearFailures($email, $ip) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("DELETE FROM login_attempts WHERE email = ? AND ip_address = ?");
        $stmt->execute([$email, $ip]);
    }
}
