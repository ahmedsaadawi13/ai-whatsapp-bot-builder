<?php
// FILE: /app/controllers/TenantController.php

class TenantController extends Controller {
    public function index() {
        $this->requireRole('platform_admin');
        require_once __DIR__ . '/../models/Tenant.php';
        $model = new Tenant();
        $page = max(1, (int) $this->request->get('page', 1));
        $perPage = 20;
        $total = $model->count([]);
        $pagination = $this->getPagination($total, $perPage);
        $tenants = $model->findAll([], null, $perPage, $pagination['offset']);
        $this->view('tenants/index', ['title' => 'Tenants', 'tenants' => $tenants, 'pagination' => $pagination]);
    }

    public function show($id) {
        $this->requireRole('platform_admin');
        require_once __DIR__ . '/../models/Tenant.php';
        $model = new Tenant();
        $tenant = $model->getWithSubscription($id);
        if (!$tenant) {
            $this->session->flash('error', 'Tenant not found');
            $this->redirect('/tenants');
        }
        $this->view('tenants/show', ['title' => 'Tenant Details', 'tenant' => $tenant]);
    }

    public function create() {
        $this->requireRole('platform_admin');
        $this->view('tenants/create', ['title' => 'Create Tenant']);
    }

    public function store() {
        $this->requireRole('platform_admin');
        $this->requireCsrf();
        require_once __DIR__ . '/../models/Tenant.php';
        $model = new Tenant();
        $slug = $model->generateSlug($this->request->post('name'));
        $id = $model->create([
            'name' => $this->request->post('name'),
            'slug' => $slug,
            'email' => $this->request->post('email'),
            'phone' => $this->request->post('phone'),
            'industry' => $this->request->post('industry'),
            'status' => 'active'
        ]);
        $this->session->flash('success', 'Tenant created successfully');
        $this->redirect('/tenants/' . $id);
    }

    public function edit($id) {
        $this->requireRole('platform_admin');
        require_once __DIR__ . '/../models/Tenant.php';
        $model = new Tenant();
        $tenant = $model->find($id);
        if (!$tenant) {
            $this->session->flash('error', 'Tenant not found');
            $this->redirect('/tenants');
        }
        $this->view('tenants/edit', ['title' => 'Edit Tenant', 'tenant' => $tenant]);
    }

    public function update($id) {
        $this->requireRole('platform_admin');
        $this->requireCsrf();
        require_once __DIR__ . '/../models/Tenant.php';
        $model = new Tenant();
        $model->update($id, [
            'name' => $this->request->post('name'),
            'email' => $this->request->post('email'),
            'phone' => $this->request->post('phone'),
            'industry' => $this->request->post('industry'),
            'status' => $this->request->post('status')
        ]);
        $this->session->flash('success', 'Tenant updated successfully');
        $this->redirect('/tenants/' . $id);
    }

    public function delete($id) {
        $this->requireRole('platform_admin');
        $this->requireCsrf();
        require_once __DIR__ . '/../models/Tenant.php';
        $model = new Tenant();
        $model->delete($id);
        $this->session->flash('success', 'Tenant deleted successfully');
        $this->redirect('/tenants');
    }
}
