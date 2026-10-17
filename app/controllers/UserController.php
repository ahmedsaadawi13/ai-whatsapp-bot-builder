<?php
// FILE: /app/controllers/UserController.php

/**
 * User Controller - tenant admins manage their team.
 * Tenant admins can only assign tenant roles (never platform_admin).
 */
class UserController extends Controller {

    const ROLES = ['tenant_admin', 'agent', 'developer'];

    public function index() {
        $tenantId = $this->requireTenant(['tenant_admin']);
        $model = new User();
        $filters = [
            'role' => in_array($this->request->get('role'), self::ROLES, true) ? $this->request->get('role') : null,
            'status' => in_array($this->request->get('status'), ['active', 'inactive'], true) ? $this->request->get('status') : null
        ];
        $search = $this->request->get('search');
        $pagination = $this->getPagination($model->count($filters, $tenantId, $search));

        $this->view('users/index', [
            'title' => 'Team',
            'users' => $model->findAll($filters, $tenantId, $pagination['per_page'], $pagination['offset'], $search),
            'pagination' => $pagination,
            'filters' => $filters + ['search' => $search],
            'roles' => self::ROLES
        ]);
    }

    public function create() {
        $this->requireTenant(['tenant_admin']);
        $this->view('users/create', ['title' => 'Add Team Member', 'roles' => self::ROLES]);
    }

    public function store() {
        $tenantId = $this->requireTenant(['tenant_admin']);
        $this->requireCsrf();

        $data = [
            'name' => $this->request->post('name'),
            'email' => strtolower((string) $this->request->post('email')),
            'password' => isset($_POST['password']) ? (string) $_POST['password'] : '',
            'role' => $this->request->post('role')
        ];
        $errors = $this->validate($data, [
            'name' => 'required|min:2|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|min:8|max:255',
            'role' => 'required|in:' . implode(',', self::ROLES)
        ]);
        if (!empty($errors)) {
            $this->failValidation($errors);
        }

        (new User())->createUser($data + ['tenant_id' => $tenantId, 'status' => 'active']);
        $this->session->flash('success', 'Team member added');
        $this->redirect('/users');
    }

    public function edit($id) {
        $tenantId = $this->requireTenant(['tenant_admin']);
        $editUser = $this->findOwned(new User(), $id, $tenantId);
        unset($editUser['password']);
        $this->view('users/edit', ['title' => 'Edit Team Member', 'editUser' => $editUser, 'roles' => self::ROLES]);
    }

    public function update($id) {
        $tenantId = $this->requireTenant(['tenant_admin']);
        $this->requireCsrf();
        $model = new User();
        $target = $this->findOwned($model, $id, $tenantId);
        $isSelf = (int) $target['id'] === $this->auth->id();

        $data = [
            'name' => $this->request->post('name'),
            'email' => strtolower((string) $this->request->post('email')),
            'role' => $this->request->post('role'),
            'status' => $this->request->post('status')
        ];
        $password = isset($_POST['password']) ? (string) $_POST['password'] : '';

        $errors = $this->validate($data + ['password' => $password], [
            'name' => 'required|min:2|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . (int) $target['id'],
            'role' => 'required|in:' . implode(',', self::ROLES),
            'status' => 'required|in:active,inactive',
            'password' => 'min:8|max:255'
        ]);
        // Never let the last active admin lock everyone out.
        $demoting = $target['role'] === 'tenant_admin' && ($data['role'] !== 'tenant_admin' || $data['status'] !== 'active');
        if ($demoting && $model->countActiveAdmins($tenantId) <= 1) {
            $errors['role'][] = 'The workspace needs at least one active tenant admin';
        }
        if ($isSelf && $data['status'] !== 'active') {
            $errors['status'][] = 'You cannot deactivate your own account';
        }
        if (!empty($errors)) {
            $this->failValidation($errors);
        }

        if ($password !== '') {
            $data['password'] = password_hash($password, PASSWORD_DEFAULT);
        }
        $model->update($target['id'], $data, $tenantId);
        $this->session->flash('success', 'Team member updated');
        $this->redirect('/users');
    }

    public function delete($id) {
        $tenantId = $this->requireTenant(['tenant_admin']);
        $this->requireCsrf();
        $model = new User();
        $target = $this->findOwned($model, $id, $tenantId);

        if ((int) $target['id'] === $this->auth->id()) {
            $this->session->flash('error', 'You cannot delete your own account');
            $this->redirect('/users');
        }
        if ($target['role'] === 'tenant_admin' && $model->countActiveAdmins($tenantId) <= 1) {
            $this->session->flash('error', 'The workspace needs at least one tenant admin');
            $this->redirect('/users');
        }

        $model->delete($target['id'], $tenantId);
        $this->session->flash('success', 'Team member removed');
        $this->redirect('/users');
    }
}
