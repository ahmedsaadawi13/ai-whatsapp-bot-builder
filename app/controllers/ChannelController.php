<?php
// FILE: /app/controllers/ChannelController.php

class ChannelController extends Controller {
    public function index() {
        $this->requireAuth();
        $tenantId = $this->getTenantId();
        require_once __DIR__ . '/../models/Channel.php';
        $model = new Channel();
        $channels = $model->getByTenantWithBots($tenantId);
        $this->view('channels/index', ['title' => 'Channels', 'channels' => $channels]);
    }

    public function create() {
        $this->requireAuth();
        $this->view('channels/create', ['title' => 'Create Channel']);
    }

    public function store() {
        $this->requireAuth();
        $this->requireCsrf();
        require_once __DIR__ . '/../models/Channel.php';
        $model = new Channel();
        $model->create([
            'tenant_id' => $this->getTenantId(),
            'name' => $this->request->post('name'),
            'phone_number' => $this->request->post('phone_number'),
            'provider_type' => $this->request->post('provider_type'),
            'provider_config' => $this->request->post('provider_config'),
            'webhook_url' => $this->request->post('webhook_url'),
            'webhook_verify_token' => bin2hex(random_bytes(16)),
            'status' => 'active'
        ]);
        $this->session->flash('success', 'Channel created successfully');
        $this->redirect('/channels');
    }

    public function edit($id) {
        $this->requireAuth();
        require_once __DIR__ . '/../models/Channel.php';
        $model = new Channel();
        $channel = $model->find($id, $this->getTenantId());
        if (!$channel) {
            $this->session->flash('error', 'Channel not found');
            $this->redirect('/channels');
        }
        $this->view('channels/edit', ['title' => 'Edit Channel', 'channel' => $channel]);
    }

    public function update($id) {
        $this->requireAuth();
        $this->requireCsrf();
        require_once __DIR__ . '/../models/Channel.php';
        $model = new Channel();
        $model->update($id, [
            'name' => $this->request->post('name'),
            'phone_number' => $this->request->post('phone_number'),
            'provider_type' => $this->request->post('provider_type'),
            'provider_config' => $this->request->post('provider_config'),
            'webhook_url' => $this->request->post('webhook_url'),
            'status' => $this->request->post('status')
        ], $this->getTenantId());
        $this->session->flash('success', 'Channel updated successfully');
        $this->redirect('/channels');
    }

    public function delete($id) {
        $this->requireAuth();
        $this->requireCsrf();
        require_once __DIR__ . '/../models/Channel.php';
        $model = new Channel();
        $model->delete($id, $this->getTenantId());
        $this->session->flash('success', 'Channel deleted successfully');
        $this->redirect('/channels');
    }
}
