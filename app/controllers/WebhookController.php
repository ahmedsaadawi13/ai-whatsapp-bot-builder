<?php
// FILE: /app/controllers/WebhookController.php

class WebhookController extends Controller {
    public function index() {
        $this->requireAuth();
        require_once __DIR__ . '/../models/Webhook.php';
        $model = new Webhook();
        $webhooks = $model->findAll([], $this->getTenantId());
        $this->view('webhooks/index', ['title' => 'Webhooks', 'webhooks' => $webhooks]);
    }

    public function create() {
        $this->requireAuth();
        require_once __DIR__ . '/../models/Bot.php';
        $botModel = new Bot();
        $bots = $botModel->findAll([], $this->getTenantId());
        $this->view('webhooks/create', ['title' => 'Create Webhook', 'bots' => $bots]);
    }

    public function store() {
        $this->requireAuth();
        $this->requireCsrf();
        require_once __DIR__ . '/../models/Webhook.php';
        $model = new Webhook();
        $events = json_encode($this->request->post('events', []));
        $model->create([
            'tenant_id' => $this->getTenantId(),
            'bot_id' => $this->request->post('bot_id'),
            'name' => $this->request->post('name'),
            'url' => $this->request->post('url'),
            'events' => $events,
            'secret' => bin2hex(random_bytes(16)),
            'status' => 'active'
        ]);
        $this->session->flash('success', 'Webhook created successfully');
        $this->redirect('/webhooks');
    }

    public function edit($id) {
        $this->requireAuth();
        require_once __DIR__ . '/../models/Webhook.php';
        require_once __DIR__ . '/../models/Bot.php';
        $webhookModel = new Webhook();
        $botModel = new Bot();
        $webhook = $webhookModel->find($id, $this->getTenantId());
        $bots = $botModel->findAll([], $this->getTenantId());
        if (!$webhook) {
            $this->session->flash('error', 'Webhook not found');
            $this->redirect('/webhooks');
        }
        $this->view('webhooks/edit', ['title' => 'Edit Webhook', 'webhook' => $webhook, 'bots' => $bots]);
    }

    public function update($id) {
        $this->requireAuth();
        $this->requireCsrf();
        require_once __DIR__ . '/../models/Webhook.php';
        $model = new Webhook();
        $events = json_encode($this->request->post('events', []));
        $model->update($id, [
            'bot_id' => $this->request->post('bot_id'),
            'name' => $this->request->post('name'),
            'url' => $this->request->post('url'),
            'events' => $events,
            'status' => $this->request->post('status')
        ], $this->getTenantId());
        $this->session->flash('success', 'Webhook updated successfully');
        $this->redirect('/webhooks');
    }

    public function delete($id) {
        $this->requireAuth();
        $this->requireCsrf();
        require_once __DIR__ . '/../models/Webhook.php';
        $model = new Webhook();
        $model->delete($id, $this->getTenantId());
        $this->session->flash('success', 'Webhook deleted successfully');
        $this->redirect('/webhooks');
    }

    public function logs($id) {
        $this->requireAuth();
        require_once __DIR__ . '/../models/WebhookLog.php';
        $model = new WebhookLog();
        $logs = $model->getByWebhook($id);
        $this->view('webhooks/logs', ['title' => 'Webhook Logs', 'webhookId' => $id, 'logs' => $logs]);
    }
}
