<?php
// FILE: /app/core/Auth.php

/**
 * Authentication Manager
 *
 * Handles user authentication and authorization.
 */
class Auth {

    private $session;
    private $userModel;

    public function __construct() {
        $this->session = new Session();
        require_once __DIR__ . '/../models/User.php';
        $this->userModel = new User();
    }

    /**
     * Attempt to login user
     *
     * @param string $email
     * @param string $password
     * @return bool
     */
    public function attempt($email, $password) {
        $user = $this->userModel->findByEmail($email);

        if ($user && password_verify($password, $user['password'])) {
            // Check if user is active
            if ($user['status'] !== 'active') {
                return false;
            }

            // Set session
            $this->login($user);
            return true;
        }

        return false;
    }

    /**
     * Login user (set session)
     *
     * @param array $user
     */
    public function login($user) {
        $this->session->regenerate();
        $this->session->set('user_id', $user['id']);
        $this->session->set('user', $user);
    }

    /**
     * Logout user
     */
    public function logout() {
        $this->session->remove('user_id');
        $this->session->remove('user');
        $this->session->destroy();
    }

    /**
     * Check if user is authenticated
     *
     * @return bool
     */
    public function check() {
        return $this->session->has('user_id');
    }

    /**
     * Get authenticated user
     *
     * @return array|null
     */
    public function user() {
        if (!$this->check()) {
            return null;
        }

        $user = $this->session->get('user');

        // Refresh user data from database
        if ($user) {
            $freshUser = $this->userModel->find($user['id']);
            if ($freshUser) {
                $this->session->set('user', $freshUser);
                return $freshUser;
            }
        }

        return null;
    }

    /**
     * Get user ID
     *
     * @return int|null
     */
    public function id() {
        $user = $this->user();
        return $user ? (int) $user['id'] : null;
    }

    /**
     * Get tenant ID of authenticated user
     *
     * @return int|null
     */
    public function tenantId() {
        $user = $this->user();
        return $user && isset($user['tenant_id']) ? (int) $user['tenant_id'] : null;
    }

    /**
     * Check if user has role
     *
     * @param string|array $roles
     * @return bool
     */
    public function hasRole($roles) {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        $roles = is_array($roles) ? $roles : [$roles];
        return in_array($user['role'], $roles);
    }

    /**
     * Check if user is platform admin
     *
     * @return bool
     */
    public function isPlatformAdmin() {
        return $this->hasRole('platform_admin');
    }

    /**
     * Check if user is tenant admin
     *
     * @return bool
     */
    public function isTenantAdmin() {
        return $this->hasRole('tenant_admin');
    }
}
