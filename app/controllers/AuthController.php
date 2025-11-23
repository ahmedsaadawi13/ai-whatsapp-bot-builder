<?php
// FILE: /app/controllers/AuthController.php

/**
 * Auth Controller
 *
 * Handles user authentication (login, register, logout).
 */
class AuthController extends Controller {

    /**
     * Show login form
     */
    public function showLogin() {
        if ($this->isAuthenticated()) {
            $this->redirect('/dashboard');
        }

        $this->view('auth/login', [
            'title' => 'Login'
        ], null);
    }

    /**
     * Process login
     */
    public function login() {
        if (!$this->request->isPost()) {
            $this->redirect('/login');
        }

        $email = $this->request->post('email');
        $password = $this->request->post('password');

        // Validate
        $errors = $this->validate([
            'email' => $email,
            'password' => $password
        ], [
            'email' => 'required|email',
            'password' => 'required'
        ]);

        if (!empty($errors)) {
            $this->session->flash('error', 'Please provide valid credentials');
            $this->redirect('/login');
        }

        // Attempt login
        if ($this->auth->attempt($email, $password)) {
            $this->session->flash('success', 'Welcome back!');
            $this->redirect('/dashboard');
        } else {
            $this->session->flash('error', 'Invalid email or password');
            $this->redirect('/login');
        }
    }

    /**
     * Show registration form
     */
    public function showRegister() {
        if ($this->isAuthenticated()) {
            $this->redirect('/dashboard');
        }

        // Load plans
        require_once __DIR__ . '/../models/Plan.php';
        $planModel = new Plan();
        $plans = $planModel->getActivePlans();

        $this->view('auth/register', [
            'title' => 'Register',
            'plans' => $plans
        ], null);
    }

    /**
     * Process registration
     */
    public function register() {
        if (!$this->request->isPost()) {
            $this->redirect('/register');
        }

        $data = [
            'name' => $this->request->post('name'),
            'email' => $this->request->post('email'),
            'password' => $this->request->post('password'),
            'password_confirmation' => $this->request->post('password_confirmation'),
            'tenant_name' => $this->request->post('tenant_name'),
            'plan_id' => $this->request->post('plan_id', 1)
        ];

        // Validate
        $errors = $this->validate($data, [
            'name' => 'required|min:2',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6|confirmed',
            'tenant_name' => 'required|min:2'
        ]);

        if (!empty($errors)) {
            $this->session->flash('error', 'Please fix the errors and try again');
            $this->session->flash('errors', $errors);
            $this->redirect('/register');
        }

        // Create tenant and user
        require_once __DIR__ . '/../models/Tenant.php';
        require_once __DIR__ . '/../models/User.php';
        require_once __DIR__ . '/../models/Subscription.php';

        $tenantModel = new Tenant();
        $userModel = new User();
        $subscriptionModel = new Subscription();

        try {
            $tenantModel->beginTransaction();

            // Create tenant
            $slug = $tenantModel->generateSlug($data['tenant_name']);
            $tenantId = $tenantModel->create([
                'name' => $data['tenant_name'],
                'slug' => $slug,
                'email' => $data['email'],
                'status' => 'active'
            ]);

            // Create user
            $userId = $userModel->createUser([
                'tenant_id' => $tenantId,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => 'tenant_admin',
                'status' => 'active'
            ]);

            // Create subscription
            $subscriptionModel->create([
                'tenant_id' => $tenantId,
                'plan_id' => $data['plan_id'],
                'status' => 'active',
                'started_at' => date('Y-m-d H:i:s'),
                'expires_at' => date('Y-m-d H:i:s', strtotime('+1 month'))
            ]);

            $tenantModel->commit();

            // Login user
            $user = $userModel->find($userId);
            $this->auth->login($user);

            $this->session->flash('success', 'Registration successful! Welcome to AI WhatsApp Bot Builder');
            $this->redirect('/dashboard');

        } catch (Exception $e) {
            $tenantModel->rollback();
            $this->session->flash('error', 'Registration failed. Please try again.');
            $this->redirect('/register');
        }
    }

    /**
     * Logout
     */
    public function logout() {
        $this->auth->logout();
        $this->session->flash('success', 'You have been logged out');
        $this->redirect('/login');
    }
}
