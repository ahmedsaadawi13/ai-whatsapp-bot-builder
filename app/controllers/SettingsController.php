<?php
// FILE: /app/controllers/SettingsController.php

class SettingsController extends Controller {
    public function index() {
        $this->requireAuth();
        $tenantId = $this->getTenantId();
        require_once __DIR__ . '/../models/Tenant.php';
        $tenantModel = new Tenant();
        $tenant = $tenantModel->find($tenantId);
        $this->view('settings/index', ['title' => 'Settings', 'tenant' => $tenant]);
    }

    public function update() {
        $this->requireAuth();
        $this->requireCsrf();
        $tenantId = $this->getTenantId();
        require_once __DIR__ . '/../models/Tenant.php';
        $tenantModel = new Tenant();
        $tenantModel->update($tenantId, [
            'name' => $this->request->post('name'),
            'email' => $this->request->post('email'),
            'phone' => $this->request->post('phone'),
            'industry' => $this->request->post('industry'),
            'timezone' => $this->request->post('timezone')
        ]);
        $this->session->flash('success', 'Settings updated successfully');
        $this->redirect('/settings');
    }

    public function apiKeys() {
        $this->requireAuth();
        $tenantId = $this->getTenantId();
        require_once __DIR__ . '/../models/ApiKey.php';
        $apiKeyModel = new ApiKey();
        $apiKeys = $apiKeyModel->findAll([], $tenantId);
        $this->view('settings/api_keys', ['title' => 'API Keys', 'apiKeys' => $apiKeys]);
    }

    public function generateApiKey() {
        $this->requireAuth();
        $this->requireCsrf();
        $tenantId = $this->getTenantId();
        $name = $this->request->post('name', 'API Key');
        require_once __DIR__ . '/../models/ApiKey.php';
        $apiKeyModel = new ApiKey();
        $result = $apiKeyModel->generate($tenantId, $name);
        $this->session->flash('success', 'API Key generated: ' . $result['key']);
        $this->session->flash('api_key', $result['key']);
        $this->redirect('/settings/api-keys');
    }

    public function revokeApiKey() {
        $this->requireAuth();
        $this->requireCsrf();
        $tenantId = $this->getTenantId();
        $keyId = $this->request->post('key_id');
        require_once __DIR__ . '/../models/ApiKey.php';
        $apiKeyModel = new ApiKey();
        $apiKeyModel->revoke($keyId, $tenantId);
        $this->session->flash('success', 'API Key revoked successfully');
        $this->redirect('/settings/api-keys');
    }
}
