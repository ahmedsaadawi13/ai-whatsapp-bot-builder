<?php
// FILE: /app/core/Controller.php

/**
 * Base Controller Class
 *
 * Provides common functionality for all controllers including
 * view rendering, redirects, and JSON responses.
 */
class Controller {

    protected $auth;
    protected $session;
    protected $request;

    public function __construct() {
        $this->auth = new Auth();
        $this->session = new Session();
        $this->request = new Request();
    }

    /**
     * Load and render a view
     *
     * @param string $view View file name (e.g., 'dashboard/index')
     * @param array $data Data to pass to view
     * @param string|null $layout Layout file name or null for no layout
     */
    protected function view($view, $data = [], $layout = 'layout') {
        $viewRenderer = new View();
        $viewRenderer->render($view, $data, $layout);
    }

    /**
     * Redirect to URL
     *
     * @param string $url
     * @param int $code HTTP status code
     */
    protected function redirect($url, $code = 302) {
        header("Location: {$url}", true, $code);
        exit;
    }

    /**
     * Return JSON response
     *
     * @param mixed $data
     * @param int $statusCode
     */
    protected function json($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Get current authenticated user
     *
     * @return array|null
     */
    protected function getUser() {
        return $this->auth->user();
    }

    /**
     * Get current tenant ID
     *
     * @return int|null
     */
    protected function getTenantId() {
        $user = $this->getUser();
        return $user ? (int) $user['tenant_id'] : null;
    }

    /**
     * Check if user is authenticated
     *
     * @return bool
     */
    protected function isAuthenticated() {
        return $this->auth->check();
    }

    /**
     * Require authentication (redirect if not authenticated)
     */
    protected function requireAuth() {
        if (!$this->isAuthenticated()) {
            $this->session->flash('error', 'Please login to access this page');
            $this->redirect('/login');
        }
    }

    /**
     * Check if user has specific role
     *
     * @param string|array $roles
     * @return bool
     */
    protected function hasRole($roles) {
        $user = $this->getUser();
        if (!$user) {
            return false;
        }

        $roles = is_array($roles) ? $roles : [$roles];
        return in_array($user['role'], $roles);
    }

    /**
     * Require specific role (redirect if unauthorized)
     *
     * @param string|array $roles
     */
    protected function requireRole($roles) {
        $this->requireAuth();

        if (!$this->hasRole($roles)) {
            $this->session->flash('error', 'Unauthorized access');
            $this->redirect('/dashboard');
        }
    }

    /**
     * Validate CSRF token
     *
     * @return bool
     */
    protected function validateCsrf() {
        $token = $this->request->post('csrf_token');
        return CSRF::validate($token);
    }

    /**
     * Require valid CSRF token
     */
    protected function requireCsrf() {
        if (!$this->validateCsrf()) {
            $this->json(['error' => 'Invalid CSRF token'], 403);
        }
    }

    /**
     * Get pagination data
     *
     * @param int $total Total records
     * @param int $perPage Records per page
     * @return array
     */
    protected function getPagination($total, $perPage = 20) {
        $page = max(1, (int) $this->request->get('page', 1));
        $offset = ($page - 1) * $perPage;
        $totalPages = ceil($total / $perPage);

        return [
            'page' => $page,
            'per_page' => $perPage,
            'offset' => $offset,
            'total' => $total,
            'total_pages' => $totalPages,
            'has_prev' => $page > 1,
            'has_next' => $page < $totalPages
        ];
    }

    /**
     * Validate input data
     *
     * @param array $data
     * @param array $rules
     * @return array Errors array
     */
    protected function validate($data, $rules) {
        $validator = new Validator();
        return $validator->validate($data, $rules);
    }
}
