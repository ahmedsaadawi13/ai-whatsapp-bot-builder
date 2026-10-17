<?php
// FILE: /app/controllers/ChannelController.php

/**
 * Channel Controller - WhatsApp numbers and their (simulated) provider credentials.
 */
class ChannelController extends Controller {

    const PROVIDERS = ['cloud_api' => 'WhatsApp Cloud API', 'twilio' => 'Twilio', 'sandbox' => 'Sandbox (testing)', 'other' => 'Other'];

    public function index() {
        $tenantId = $this->requireTenant(self::BUILDER_ROLES);
        $model = new Channel();
        $filters = [
            'status' => in_array($this->request->get('status'), ['active', 'inactive'], true) ? $this->request->get('status') : null,
            'provider_type' => array_key_exists((string) $this->request->get('provider_type'), self::PROVIDERS) ? $this->request->get('provider_type') : null
        ];
        $search = $this->request->get('search');
        $pagination = $this->getPagination($model->count($filters, $tenantId, $search));

        $this->view('channels/index', [
            'title' => 'WhatsApp Channels',
            'channels' => $model->listWithBotCount($tenantId, $filters, $search, $pagination['per_page'], $pagination['offset']),
            'pagination' => $pagination,
            'filters' => $filters + ['search' => $search],
            'providers' => self::PROVIDERS,
            'webhookUrl' => rtrim(config('app.url'), '/') . '/api/v1/webhook'
        ]);
    }

    public function create() {
        $this->requireTenant(self::BUILDER_ROLES);
        $this->view('channels/create', ['title' => 'Add Channel', 'providers' => self::PROVIDERS]);
    }

    public function store() {
        $tenantId = $this->requireTenant(self::BUILDER_ROLES);
        $this->requireCsrf();

        $quota = QuotaService::check($tenantId, 'channels');
        if (!$quota['allowed']) {
            $this->session->flash('error', $quota['message']);
            $this->redirect('/channels');
        }

        $data = $this->collect();
        $errors = $this->validateChannel($data, 0);
        if (!empty($errors)) {
            $this->failValidation($errors);
        }

        (new Channel())->create([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'phone_number' => $data['phone_number'],
            'provider_type' => $data['provider_type'],
            'provider_config' => Channel::encodeConfig(json_array($data['provider_config'])),
            'webhook_url' => '/api/v1/webhook',
            'webhook_verify_token' => bin2hex(random_bytes(16)),
            'status' => $data['status']
        ]);
        $this->session->flash('success', 'Channel added. Configure your provider to call the webhook URL with the verify token shown below.');
        $this->redirect('/channels');
    }

    public function edit($id) {
        $tenantId = $this->requireTenant(self::BUILDER_ROLES);
        $channel = $this->findOwned(new Channel(), $id, $tenantId);
        $this->view('channels/edit', [
            'title' => 'Edit Channel',
            'channel' => $channel,
            'providers' => self::PROVIDERS,
            'configKeys' => array_keys(Channel::decodeConfig($channel['provider_config'])),
            'webhookUrl' => rtrim(config('app.url'), '/') . '/api/v1/webhook'
        ]);
    }

    public function update($id) {
        $tenantId = $this->requireTenant(self::BUILDER_ROLES);
        $this->requireCsrf();
        $model = new Channel();
        $channel = $this->findOwned($model, $id, $tenantId);

        $data = $this->collect();
        $errors = $this->validateChannel($data, $channel['id']);
        if (!empty($errors)) {
            $this->failValidation($errors);
        }

        $update = [
            'name' => $data['name'],
            'phone_number' => $data['phone_number'],
            'provider_type' => $data['provider_type'],
            'status' => $data['status']
        ];
        // Blank credentials field = keep the stored (encrypted) credentials.
        if (trim((string) $data['provider_config']) !== '') {
            $update['provider_config'] = Channel::encodeConfig(json_array($data['provider_config']));
        }
        if ($this->request->post('regenerate_token')) {
            $update['webhook_verify_token'] = bin2hex(random_bytes(16));
        }

        $model->update($channel['id'], $update, $tenantId);
        $this->session->flash('success', 'Channel updated');
        $this->redirect('/channels');
    }

    public function delete($id) {
        $tenantId = $this->requireTenant(['tenant_admin']);
        $this->requireCsrf();
        $model = new Channel();
        $channel = $this->findOwned($model, $id, $tenantId);
        $model->delete($channel['id'], $tenantId);
        $this->session->flash('success', 'Channel deleted together with its bots and conversations');
        $this->redirect('/channels');
    }

    private function collect() {
        return [
            'name' => $this->request->post('name'),
            'phone_number' => Validator::normalizePhone($this->request->post('phone_number')),
            'provider_type' => $this->request->post('provider_type'),
            'provider_config' => isset($_POST['provider_config']) ? trim((string) $_POST['provider_config']) : '',
            'status' => $this->request->post('status', 'active')
        ];
    }

    private function validateChannel($data, $exceptId) {
        $errors = $this->validate($data, [
            'name' => 'required|min:2|max:255',
            'phone_number' => 'required|phone',
            'provider_type' => 'required|in:' . implode(',', array_keys(self::PROVIDERS)),
            'provider_config' => 'json',
            'status' => 'required|in:active,inactive'
        ]);
        if (empty($errors['phone_number']) && (new Channel())->phoneExists($data['phone_number'], $exceptId)) {
            $errors['phone_number'][] = 'This WhatsApp number is already connected to an account';
        }
        if ($data['provider_config'] !== '' && empty($errors['provider_config']) && !is_array(json_decode($data['provider_config'], true))) {
            $errors['provider_config'][] = 'Credentials must be a JSON object, e.g. {"access_token": "..."}';
        }
        return $errors;
    }
}
