<?php
// FILE: /app/controllers/TenantController.php

/**
 * Tenant Controller - platform admin management of tenant accounts.
 */
class TenantController extends Controller {

    const STATUSES = ['active', 'suspended', 'cancelled'];

    public function index() {
        $this->requireRole('platform_admin');
        $model = new Tenant();
        $filters = ['status' => $this->request->get('status')];
        if (!in_array($filters['status'], self::STATUSES, true)) {
            $filters['status'] = null;
        }
        $search = $this->request->get('search');

        $pagination = $this->getPagination($model->count($filters, null, $search));
        $this->view('tenants/index', [
            'title' => 'Tenants',
            'tenants' => $model->listWithStats($filters, $search, $pagination['per_page'], $pagination['offset']),
            'pagination' => $pagination,
            'filters' => ['status' => $filters['status'], 'search' => $search]
        ]);
    }

    public function show($id) {
        $this->requireRole('platform_admin');
        $tenant = (new Tenant())->getWithSubscription((int) $id);
        if (!$tenant) {
            $this->abort(404, 'Tenant not found');
        }
        $this->view('tenants/show', [
            'title' => $tenant['name'],
            'tenant' => $tenant,
            'users' => (new User())->findAll([], $tenant['id'], 100, 0),
            'quota' => QuotaService::summary($tenant['id']),
            'invoices' => (new Invoice())->findAll([], $tenant['id'], 10, 0)
        ]);
    }

    public function create() {
        $this->requireRole('platform_admin');
        $this->view('tenants/create', ['title' => 'Create Tenant', 'plans' => (new Plan())->getActivePlans()]);
    }

    /**
     * Create a tenant together with its first admin user and subscription.
     */
    public function store() {
        $this->requireRole('platform_admin');
        $this->requireCsrf();

        $data = [
            'name' => $this->request->post('name'),
            'email' => strtolower((string) $this->request->post('email')),
            'phone' => $this->request->post('phone'),
            'industry' => $this->request->post('industry'),
            'plan_id' => $this->request->post('plan_id'),
            'admin_name' => $this->request->post('admin_name'),
            'admin_email' => strtolower((string) $this->request->post('admin_email')),
            'admin_password' => isset($_POST['admin_password']) ? (string) $_POST['admin_password'] : ''
        ];
        $errors = $this->validate($data, [
            'name' => 'required|min:2|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'max:50',
            'industry' => 'max:100',
            'plan_id' => 'required|integer',
            'admin_name' => 'required|min:2|max:255',
            'admin_email' => 'required|email|max:255|unique:users,email',
            'admin_password' => 'required|min:8|max:255'
        ]);
        $plan = (new Plan())->findActive($data['plan_id']);
        if (!$plan) {
            $errors['plan_id'][] = 'Please choose a valid plan';
        }
        if (!empty($errors)) {
            $this->failValidation($errors);
        }

        $tenantModel = new Tenant();
        $tenantModel->beginTransaction();
        try {
            $tenantId = $tenantModel->create([
                'name' => $data['name'],
                'slug' => $tenantModel->generateSlug($data['name']),
                'email' => $data['email'],
                'phone' => $data['phone'] ?: null,
                'industry' => $data['industry'] ?: null,
                'status' => 'active'
            ]);
            $userId = (new User())->createUser([
                'tenant_id' => $tenantId,
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'password' => $data['admin_password'],
                'role' => 'tenant_admin',
                'status' => 'active'
            ]);
            $tenantModel->commit();
        } catch (Exception $e) {
            $tenantModel->rollback();
            throw $e;
        }

        (new Subscription())->switchPlan($tenantId, $plan);
        Notifier::welcome($tenantModel->find($tenantId), (new User())->find($userId));

        $this->session->flash('success', 'Tenant created with its admin user');
        $this->redirect('/tenants/' . $tenantId);
    }

    public function edit($id) {
        $this->requireRole('platform_admin');
        $tenant = (new Tenant())->find((int) $id);
        if (!$tenant) {
            $this->abort(404, 'Tenant not found');
        }
        $this->view('tenants/edit', ['title' => 'Edit Tenant', 'tenant' => $tenant, 'timezones' => DateTimeZone::listIdentifiers()]);
    }

    public function update($id) {
        $this->requireRole('platform_admin');
        $this->requireCsrf();
        $model = new Tenant();
        if (!$model->find((int) $id)) {
            $this->abort(404, 'Tenant not found');
        }

        $data = [
            'name' => $this->request->post('name'),
            'email' => strtolower((string) $this->request->post('email')),
            'phone' => $this->request->post('phone'),
            'industry' => $this->request->post('industry'),
            'timezone' => $this->request->post('timezone', 'UTC'),
            'status' => $this->request->post('status')
        ];
        $errors = $this->validate($data, [
            'name' => 'required|min:2|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'max:50',
            'industry' => 'max:100',
            'status' => 'required|in:' . implode(',', self::STATUSES)
        ]);
        if (!in_array($data['timezone'], DateTimeZone::listIdentifiers(), true)) {
            $errors['timezone'][] = 'Invalid timezone';
        }
        if (!empty($errors)) {
            $this->failValidation($errors);
        }

        $model->update((int) $id, $data);
        $this->session->flash('success', 'Tenant updated');
        $this->redirect('/tenants/' . (int) $id);
    }

    public function delete($id) {
        $this->requireRole('platform_admin');
        $this->requireCsrf();
        (new Tenant())->delete((int) $id);
        $this->session->flash('success', 'Tenant and all of its data were deleted');
        $this->redirect('/tenants');
    }
}
