<?php
// FILE: /app/controllers/WebhookController.php

/**
 * Webhook Controller - outgoing webhooks (integrations) and their delivery logs.
 */
class WebhookController extends Controller {

    public function index() {
        $tenantId = $this->requireTenant(self::DEVELOPER_ROLES);
        $model = new Webhook();
        $filters = ['status' => in_array($this->request->get('status'), ['active', 'inactive'], true) ? $this->request->get('status') : null];
        $search = $this->request->get('search');
        $pagination = $this->getPagination($model->count($filters, $tenantId, $search));

        $this->view('webhooks/index', [
            'title' => 'Webhooks',
            'webhooks' => $model->listWithStats($tenantId, $filters, $search, $pagination['per_page'], $pagination['offset']),
            'pagination' => $pagination,
            'filters' => $filters + ['search' => $search]
        ]);
    }

    public function create() {
        $tenantId = $this->requireTenant(self::DEVELOPER_ROLES);
        $this->view('webhooks/create', ['title' => 'Add Webhook', 'bots' => (new Bot())->getForSelect($tenantId), 'events' => Webhook::EVENTS]);
    }

    public function store() {
        $tenantId = $this->requireTenant(self::DEVELOPER_ROLES);
        $this->requireCsrf();
        list($data, $errors) = $this->collect($tenantId);
        if (!empty($errors)) {
            $this->failValidation($errors);
        }
        (new Webhook())->create($data + ['tenant_id' => $tenantId, 'secret' => 'whsec_' . bin2hex(random_bytes(20))]);
        $this->session->flash('success', 'Webhook created. Verify deliveries with the X-Signature header and the secret shown on the edit page.');
        $this->redirect('/webhooks');
    }

    public function edit($id) {
        $tenantId = $this->requireTenant(self::DEVELOPER_ROLES);
        $webhook = $this->findOwned(new Webhook(), $id, $tenantId);
        $webhook['events_list'] = json_array($webhook['events']);
        $this->view('webhooks/edit', [
            'title' => 'Edit Webhook',
            'webhook' => $webhook,
            'bots' => (new Bot())->getForSelect($tenantId),
            'events' => Webhook::EVENTS
        ]);
    }

    public function update($id) {
        $tenantId = $this->requireTenant(self::DEVELOPER_ROLES);
        $this->requireCsrf();
        $model = new Webhook();
        $webhook = $this->findOwned($model, $id, $tenantId);
        list($data, $errors) = $this->collect($tenantId);
        if (!empty($errors)) {
            $this->failValidation($errors);
        }
        if ($this->request->post('regenerate_secret')) {
            $data['secret'] = 'whsec_' . bin2hex(random_bytes(20));
        }
        $model->update($webhook['id'], $data, $tenantId);
        $this->session->flash('success', 'Webhook updated');
        $this->redirect('/webhooks');
    }

    public function delete($id) {
        $tenantId = $this->requireTenant(self::DEVELOPER_ROLES);
        $this->requireCsrf();
        $model = new Webhook();
        $webhook = $this->findOwned($model, $id, $tenantId);
        $model->delete($webhook['id'], $tenantId);
        $this->session->flash('success', 'Webhook deleted');
        $this->redirect('/webhooks');
    }

    /** Send a sample "webhook.test" event right now. */
    public function test($id) {
        $tenantId = $this->requireTenant(self::DEVELOPER_ROLES);
        $this->requireCsrf();
        $webhook = $this->findOwned(new Webhook(), $id, $tenantId);
        $body = json_encode([
            'event' => 'webhook.test',
            'tenant_id' => $tenantId,
            'webhook_id' => (int) $webhook['id'],
            'timestamp' => gmdate('c'),
            'data' => ['message' => 'This is a test event from AI WhatsApp Bot Builder']
        ]);
        $result = WebhookDispatcher::deliver($webhook, 'webhook.test', $body, 1);
        $this->session->flash($result['ok'] ? 'success' : 'error', $result['ok']
            ? 'Test delivered (HTTP ' . $result['code'] . ')'
            : 'Test failed: ' . ($result['code'] ? 'HTTP ' . $result['code'] : mb_substr($result['body'], 0, 200, 'UTF-8')));
        $this->redirect('/webhooks/' . $webhook['id'] . '/logs');
    }

    public function logs($id) {
        $tenantId = $this->requireTenant(self::DEVELOPER_ROLES);
        $webhook = $this->findOwned(new Webhook(), $id, $tenantId);
        $logModel = new WebhookLog();
        $status = $this->request->get('status');
        $status = in_array($status, ['success', 'failed'], true) ? $status : null;
        $pagination = $this->getPagination($logModel->countByWebhook($webhook['id'], $tenantId, $status), 25);

        $this->view('webhooks/logs', [
            'title' => 'Webhook Logs - ' . $webhook['name'],
            'webhook' => $webhook,
            'logs' => $logModel->getByWebhook($webhook['id'], $tenantId, $status, $pagination['per_page'], $pagination['offset']),
            'pagination' => $pagination,
            'statusFilter' => $status
        ]);
    }

    private function collect($tenantId) {
        $events = isset($_POST['events']) && is_array($_POST['events']) ? array_values(array_intersect($_POST['events'], Webhook::EVENTS)) : [];
        $data = [
            'name' => $this->request->post('name'),
            'url' => $this->request->post('url'),
            'bot_id' => (int) $this->request->post('bot_id') ?: null,
            'events' => json_encode($events),
            'retry_attempts' => (int) $this->request->post('retry_attempts', 3),
            'status' => $this->request->post('status', 'active')
        ];
        $errors = $this->validate($data, [
            'name' => 'required|min:2|max:255',
            'url' => 'required|url|max:255',
            'status' => 'required|in:active,inactive'
        ]);
        if (empty($errors['url']) && !WebhookDispatcher::isSafeUrl($data['url'])) {
            $errors['url'][] = 'URL must be a public http(s) address (private/internal addresses are blocked)';
        }
        if (empty($events)) {
            $errors['events'][] = 'Select at least one event';
        }
        if ($data['retry_attempts'] < 1 || $data['retry_attempts'] > 10) {
            $errors['retry_attempts'][] = 'Attempts must be between 1 and 10';
        }
        if ($data['bot_id'] && !(new Bot())->find($data['bot_id'], $tenantId)) {
            $errors['bot_id'][] = 'Choose one of your bots';
        }
        return [$data, $errors];
    }
}
