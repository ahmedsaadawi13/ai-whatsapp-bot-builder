<?php
// FILE: /app/controllers/FlowController.php

/**
 * Flow Controller - triggers that start a sequence of steps for a bot.
 */
class FlowController extends Controller {

    const TRIGGER_LABELS = [
        'keyword' => 'Keyword match (exact)',
        'message_contains' => 'Message contains',
        'menu' => 'Menu option selection',
        'button' => 'Button click (payload)',
        'default' => 'Default fallback'
    ];

    public function index($botId) {
        $tenantId = $this->requireTenant(self::BUILDER_ROLES);
        $bot = $this->findOwned(new Bot(), $botId, $tenantId);
        $this->view('flows/index', [
            'title' => 'Flows - ' . $bot['name'],
            'bot' => $bot,
            'flows' => (new Flow())->getByBot($bot['id'], $tenantId),
            'triggerLabels' => self::TRIGGER_LABELS
        ]);
    }

    public function create($botId) {
        $tenantId = $this->requireTenant(self::BUILDER_ROLES);
        $this->view('flows/create', [
            'title' => 'Create Flow',
            'bot' => $this->findOwned(new Bot(), $botId, $tenantId),
            'triggerLabels' => self::TRIGGER_LABELS
        ]);
    }

    public function store($botId) {
        $tenantId = $this->requireTenant(self::BUILDER_ROLES);
        $this->requireCsrf();
        $bot = $this->findOwned(new Bot(), $botId, $tenantId);

        $data = $this->collect();
        $errors = $this->validateFlow($data);
        if (!empty($errors)) {
            $this->failValidation($errors);
        }

        $id = (new Flow())->create($data + ['tenant_id' => $tenantId, 'bot_id' => $bot['id']]);
        $this->session->flash('success', 'Flow created. Now add its steps.');
        $this->redirect('/flows/' . $id . '/steps');
    }

    public function edit($id) {
        $tenantId = $this->requireTenant(self::BUILDER_ROLES);
        $flow = $this->findOwned(new Flow(), $id, $tenantId);
        $this->view('flows/edit', [
            'title' => 'Edit Flow',
            'flow' => $flow,
            'bot' => (new Bot())->find($flow['bot_id'], $tenantId),
            'triggerLabels' => self::TRIGGER_LABELS
        ]);
    }

    public function update($id) {
        $tenantId = $this->requireTenant(self::BUILDER_ROLES);
        $this->requireCsrf();
        $model = new Flow();
        $flow = $this->findOwned($model, $id, $tenantId);

        $data = $this->collect();
        $errors = $this->validateFlow($data);
        if (!empty($errors)) {
            $this->failValidation($errors);
        }

        $model->update($flow['id'], $data, $tenantId);
        $this->session->flash('success', 'Flow updated');
        $this->redirect('/bots/' . $flow['bot_id'] . '/flows');
    }

    public function delete($id) {
        $tenantId = $this->requireTenant(self::BUILDER_ROLES);
        $this->requireCsrf();
        $model = new Flow();
        $flow = $this->findOwned($model, $id, $tenantId);
        $model->delete($flow['id'], $tenantId);
        $this->session->flash('success', 'Flow deleted');
        $this->redirect('/bots/' . $flow['bot_id'] . '/flows');
    }

    private function collect() {
        $type = $this->request->post('trigger_type');
        return [
            'name' => $this->request->post('name'),
            'description' => $this->request->post('description'),
            'trigger_type' => $type,
            'trigger_value' => $type === 'default' ? null : $this->request->post('trigger_value'),
            'priority' => (int) $this->request->post('priority', 0),
            'status' => $this->request->post('status', 'active')
        ];
    }

    private function validateFlow($data) {
        $errors = $this->validate($data, [
            'name' => 'required|min:2|max:255',
            'description' => 'max:2000',
            'trigger_type' => 'required|in:' . implode(',', Flow::TRIGGER_TYPES),
            'trigger_value' => 'max:255',
            'status' => 'required|in:active,inactive'
        ]);
        if ($data['trigger_type'] !== 'default' && empty(Flow::triggerValues($data['trigger_value']))) {
            $errors['trigger_value'][] = 'Enter at least one trigger value (comma-separated)';
        }
        if ($data['priority'] < -100 || $data['priority'] > 100) {
            $errors['priority'][] = 'Priority must be between -100 and 100';
        }
        return $errors;
    }
}
