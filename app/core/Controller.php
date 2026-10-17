<?php
// FILE: /app/core/Controller.php

/**
 * Base Controller Class
 *
 * Shared helpers: rendering, redirects, JSON, auth/role/tenant guards,
 * CSRF checks, pagination and validation.
 */
class Controller {

    /** Roles allowed to build bots, flows, KB, templates and broadcasts. */
    const BUILDER_ROLES = ['tenant_admin', 'developer'];

    /** Roles allowed to manage webhooks and API keys. */
    const DEVELOPER_ROLES = ['tenant_admin', 'developer'];

    /** Every tenant-level role. */
    const TENANT_ROLES = ['tenant_admin', 'agent', 'developer'];

    protected $auth;
    protected $session;
    protected $request;

    public function __construct() {
        $this->session = new Session();
        $this->auth = new Auth();
        $this->request = new Request();
    }

    protected function view($view, $data = [], $layout = 'layout') {
        $renderer = new View();
        $renderer->render($view, $data, $layout);
    }

    protected function redirect($url, $code = 302) {
        // Only allow local redirects to avoid open-redirect issues.
        if (!is_string($url) || $url === '' || $url[0] !== '/' || strpos($url, '//') === 0) {
            $url = '/';
        }
        header("Location: {$url}", true, $code);
        exit;
    }

    /** Redirect back to the referring page (same host only). */
    protected function back($fallback = '/dashboard') {
        $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
        $path = parse_url($referer, PHP_URL_PATH);
        $query = parse_url($referer, PHP_URL_QUERY);
        $host = parse_url($referer, PHP_URL_HOST);
        $currentHost = isset($_SERVER['HTTP_HOST']) ? parse_url('http://' . $_SERVER['HTTP_HOST'], PHP_URL_HOST) : null;
        if ($path && $host === $currentHost) {
            $this->redirect($path . ($query ? '?' . $query : ''));
        }
        $this->redirect($fallback);
    }

    protected function json($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }

    protected function abort($code = 404, $message = 'Page not found') {
        http_response_code($code);
        $this->view('errors/error', ['title' => $message, 'code' => $code, 'message' => $message], 'layout');
        exit;
    }

    protected function getUser() {
        return $this->auth->user();
    }

    protected function getTenantId() {
        return $this->auth->tenantId();
    }

    protected function isAuthenticated() {
        return $this->auth->user() !== null;
    }

    protected function requireAuth() {
        if (!$this->isAuthenticated()) {
            $this->session->flash('error', 'Please login to access this page');
            $this->redirect('/login');
        }
    }

    protected function hasRole($roles) {
        return $this->auth->hasRole($roles);
    }

    /**
     * Require login with one of the given roles.
     */
    protected function requireRole($roles) {
        $this->requireAuth();
        if (!$this->hasRole($roles)) {
            $this->abort(403, 'You do not have permission to access this page');
        }
    }

    /**
     * Require a tenant user (optionally limited to some roles) and return the tenant ID.
     * Platform admins have no tenant and are sent to the tenant list.
     */
    protected function requireTenant($roles = null) {
        $this->requireAuth();
        $tenantId = $this->getTenantId();
        if ($tenantId === null) {
            $this->session->flash('error', 'This page is only available inside a tenant account');
            $this->redirect('/tenants');
        }
        if ($roles !== null && !$this->hasRole($roles)) {
            $this->abort(403, 'You do not have permission to access this page');
        }
        return $tenantId;
    }

    protected function validateCsrf() {
        $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : null;
        if (!$token && isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
        }
        return CSRF::validate($token);
    }

    /**
     * Require POST with a valid CSRF token.
     */
    protected function requireCsrf() {
        if (!$this->request->isPost() || !$this->validateCsrf()) {
            if ($this->request->isAjax()) {
                $this->json(['error' => 'Invalid CSRF token'], 403);
            }
            $this->session->flash('error', 'Your session expired. Please try again.');
            $this->back();
        }
    }

    /**
     * Load a tenant-owned record or show 404.
     */
    protected function findOwned(Model $model, $id, $tenantId) {
        $record = $model->find((int) $id, $tenantId);
        if (!$record) {
            $this->abort(404, 'Record not found');
        }
        return $record;
    }

    /**
     * Flash validation errors + old input and go back.
     */
    protected function failValidation($errors, $redirect = null) {
        $this->session->flash('error', 'Please fix the errors below');
        $this->session->flash('errors', $errors);
        $input = $_POST;
        unset($input['password'], $input['password_confirmation'], $input['csrf_token']);
        $this->session->set('_old_input', $input);
        if ($redirect) {
            $this->redirect($redirect);
        }
        $this->back();
    }

    protected function getPagination($total, $perPage = 20) {
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($totalPages, max(1, (int) $this->request->get('page', 1)));

        return [
            'page' => $page,
            'per_page' => $perPage,
            'offset' => ($page - 1) * $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
            'has_prev' => $page > 1,
            'has_next' => $page < $totalPages
        ];
    }

    protected function validate($data, $rules) {
        $validator = new Validator();
        return $validator->validate($data, $rules);
    }
}
