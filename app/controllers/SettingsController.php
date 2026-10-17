<?php
// FILE: /app/controllers/SettingsController.php

/**
 * Settings Controller - tenant profile, logo upload and API keys.
 */
class SettingsController extends Controller {

    public function index() {
        $tenantId = $this->requireTenant(['tenant_admin']);
        $this->view('settings/index', [
            'title' => 'Settings',
            'tenant' => (new Tenant())->find($tenantId),
            'timezones' => DateTimeZone::listIdentifiers()
        ]);
    }

    public function update() {
        $tenantId = $this->requireTenant(['tenant_admin']);
        $this->requireCsrf();

        $data = [
            'name' => $this->request->post('name'),
            'email' => strtolower((string) $this->request->post('email')),
            'phone' => $this->request->post('phone') ?: null,
            'address' => $this->request->post('address') ?: null,
            'website' => $this->request->post('website') ?: null,
            'industry' => $this->request->post('industry') ?: null,
            'timezone' => $this->request->post('timezone', 'UTC')
        ];
        $errors = $this->validate($data, [
            'name' => 'required|min:2|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'max:50',
            'address' => 'max:255',
            'website' => 'url|max:255',
            'industry' => 'max:100'
        ]);
        if (!in_array($data['timezone'], DateTimeZone::listIdentifiers(), true)) {
            $errors['timezone'][] = 'Choose a valid timezone';
        }
        if (!empty($errors)) {
            $this->failValidation($errors);
        }

        (new Tenant())->update($tenantId, $data);
        $this->session->flash('success', 'Settings saved');
        $this->redirect('/settings');
    }

    public function uploadLogo() {
        $tenantId = $this->requireTenant(['tenant_admin']);
        $this->requireCsrf();
        $file = $this->request->file('logo');
        if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
            $this->failValidation(['logo' => ['Choose an image']]);
        }

        $quota = QuotaService::check($tenantId, 'storage', $file['size'] / 1048576);
        if (!$quota['allowed']) {
            $this->session->flash('error', $quota['message']);
            $this->redirect('/settings');
        }

        $uploader = new FileUpload();
        $uploader->setAllowedTypes(['jpg', 'jpeg', 'png']);
        $uploader->setMaxSize(2 * 1048576);
        $path = $uploader->upload($file, $tenantId, 'logos');
        if ($path === false) {
            $this->failValidation(['logo' => $uploader->getErrors()]);
        }

        $tenantModel = new Tenant();
        $old = $tenantModel->find($tenantId)['logo'];
        $tenantModel->update($tenantId, ['logo' => $path]);
        if ($old) {
            $uploader->delete($old);
        }
        $this->session->flash('success', 'Logo updated');
        $this->redirect('/settings');
    }

    public function apiKeys() {
        $tenantId = $this->requireTenant(self::DEVELOPER_ROLES);
        $base = rtrim(config('app.url'), '/');
        $this->view('settings/api_keys', [
            'title' => 'API Keys',
            'apiKeys' => (new ApiKey())->findAll([], $tenantId, 100, 0),
            'newKey' => $this->session->getFlash('new_api_key'),
            'apiBase' => $base . '/api/v1',
            'bots' => (new Bot())->getForSelect($tenantId),
            'channels' => (new Channel())->getForSelect($tenantId)
        ]);
    }

    public function generateApiKey() {
        $tenantId = $this->requireTenant(self::DEVELOPER_ROLES);
        $this->requireCsrf();
        $name = trim((string) $this->request->post('name', ''));
        if ($name === '' || mb_strlen($name, 'UTF-8') > 100) {
            $this->failValidation(['name' => ['Give the key a name (max 100 characters)']]);
        }
        $result = (new ApiKey())->generate($tenantId, $name);
        $this->session->flash('new_api_key', $result['key']);
        $this->session->flash('success', 'API key created. Copy it now - it will not be shown again.');
        $this->redirect('/settings/api-keys');
    }

    public function revokeApiKey() {
        $tenantId = $this->requireTenant(self::DEVELOPER_ROLES);
        $this->requireCsrf();
        $ok = (new ApiKey())->revoke((int) $this->request->post('key_id'), $tenantId);
        $this->session->flash($ok ? 'success' : 'error', $ok ? 'API key revoked' : 'API key not found');
        $this->redirect('/settings/api-keys');
    }
}
