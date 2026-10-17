<?php
// FILE: /app/controllers/AuthController.php

/**
 * Auth Controller - login, tenant self-registration, logout.
 */
class AuthController extends Controller {

    public function showLogin() {
        if ($this->isAuthenticated()) {
            $this->redirect('/dashboard');
        }
        $this->view('auth/login', ['title' => 'Login'], null);
    }

    public function login() {
        $this->requireCsrf();

        $email = (string) $this->request->post('email', '');
        $password = isset($_POST['password']) ? (string) $_POST['password'] : '';

        if ($email === '' || $password === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->session->flash('error', 'Please enter a valid email and password');
            $this->session->set('_old_input', ['email' => $email]);
            $this->redirect('/login');
        }

        if ($this->auth->attempt($email, $password, $this->request->ip())) {
            $this->session->flash('success', 'Welcome back!');
            $this->redirect('/dashboard');
        }

        $this->session->flash('error', $this->auth->getLastError());
        $this->session->set('_old_input', ['email' => $email]);
        $this->redirect('/login');
    }

    public function showRegister() {
        if ($this->isAuthenticated()) {
            $this->redirect('/dashboard');
        }
        $this->view('auth/register', [
            'title' => 'Register',
            'plans' => (new Plan())->getActivePlans()
        ], null);
    }

    /**
     * Self-service signup: creates tenant + tenant_admin + subscription + first invoice.
     */
    public function register() {
        $this->requireCsrf();

        $data = [
            'name' => $this->request->post('name'),
            'email' => strtolower((string) $this->request->post('email')),
            'password' => isset($_POST['password']) ? (string) $_POST['password'] : '',
            'password_confirmation' => isset($_POST['password_confirmation']) ? (string) $_POST['password_confirmation'] : '',
            'tenant_name' => $this->request->post('tenant_name'),
            'plan_id' => $this->request->post('plan_id')
        ];

        $errors = $this->validate($data, [
            'tenant_name' => 'required|min:2|max:255',
            'name' => 'required|min:2|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|min:8|max:255|confirmed',
            'plan_id' => 'required|integer'
        ]);

        $plan = (new Plan())->findActive($data['plan_id']);
        if (!$plan) {
            $errors['plan_id'][] = 'Please choose a valid plan';
        }
        if (!empty($errors)) {
            $this->failValidation($errors, '/register');
        }

        $tenantModel = new Tenant();
        try {
            $tenantModel->beginTransaction();

            $tenantId = $tenantModel->create([
                'name' => $data['tenant_name'],
                'slug' => $tenantModel->generateSlug($data['tenant_name']),
                'email' => $data['email'],
                'timezone' => 'UTC',
                'status' => 'active'
            ]);

            $userId = (new User())->createUser([
                'tenant_id' => $tenantId,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => 'tenant_admin',
                'status' => 'active'
            ]);

            $tenantModel->commit();
        } catch (Exception $e) {
            $tenantModel->rollback();
            error_log('Registration failed: ' . $e->getMessage());
            $this->session->flash('error', 'Registration failed. Please try again.');
            $this->redirect('/register');
        }

        (new Subscription())->switchPlan($tenantId, $plan);

        $tenant = $tenantModel->find($tenantId);
        $user = (new User())->find($userId);
        Notifier::welcome($tenant, $user);

        $this->auth->login($user);
        $this->session->flash('success', 'Welcome to AI WhatsApp Bot Builder! Your first invoice is waiting under Billing.');
        $this->redirect('/dashboard');
    }

    public function logout() {
        $this->auth->logout();
        $this->session->flash('success', 'You have been logged out');
        $this->redirect('/login');
    }
}
