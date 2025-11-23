<?php
// FILE: /app/controllers/UserController.php

class UserController extends Controller {
    public function index() {
        $this->requireAuth();
        $tenantId = $this->getTenantId();
        require_once __DIR__ . '/../models/User.php';
        $model = new User();
        $users = $model->getByTenant($tenantId);
        $this->view('users/index', ['title' => 'Users', 'users' => $users]);
    }

    public function create() {
        $this->requireRole(['tenant_admin', 'platform_admin']);
        $this->view('users/create', ['title' => 'Create User']);
    }

    public function store() {
        $this->requireRole(['tenant_admin', 'platform_admin']);
        $this->requireCsrf();
        require_once __DIR__ . '/../models/User.php';
        $model = new User();
        $model->createUser([
            'tenant_id' => $this->getTenantId(),
            'name' => $this->request->post('name'),
            'email' => $this->request->post('email'),
            'password' => $this->request->post('password'),
            'role' => $this->request->post('role'),
            'status' => 'active'
        ]);
        $this->session->flash('success', 'User created successfully');
        $this->redirect('/users');
    }

    public function edit($id) {
        $this->requireRole(['tenant_admin', 'platform_admin']);
        require_once __DIR__ . '/../models/User.php';
        $model = new User();
        $user = $model->find($id, $this->getTenantId());
        if (!$user) {
            $this->session->flash('error', 'User not found');
            $this->redirect('/users');
        }
        $this->view('users/edit', ['title' => 'Edit User', 'user' => $user]);
    }

    public function update($id) {
        $this->requireRole(['tenant_admin', 'platform_admin']);
        $this->requireCsrf();
        require_once __DIR__ . '/../models/User.php';
        $model = new User();
        $data = [
            'name' => $this->request->post('name'),
            'email' => $this->request->post('email'),
            'role' => $this->request->post('role'),
            'status' => $this->request->post('status')
        ];
        if ($this->request->post('password')) {
            $data['password'] = password_hash($this->request->post('password'), PASSWORD_DEFAULT);
        }
        $model->update($id, $data, $this->getTenantId());
        $this->session->flash('success', 'User updated successfully');
        $this->redirect('/users');
    }

    public function delete($id) {
        $this->requireRole(['tenant_admin', 'platform_admin']);
        $this->requireCsrf();
        require_once __DIR__ . '/../models/User.php';
        $model = new User();
        $model->delete($id, $this->getTenantId());
        $this->session->flash('success', 'User deleted successfully');
        $this->redirect('/users');
    }
}
