<?php
// FILE: /app/controllers/FlowController.php

class FlowController extends Controller {
    public function index($botId) {
        $this->requireAuth();
        $tenantId = $this->getTenantId();
        require_once __DIR__ . '/../models/Flow.php';
        require_once __DIR__ . '/../models/Bot.php';
        $flowModel = new Flow();
        $botModel = new Bot();
        $bot = $botModel->find($botId, $tenantId);
        if (!$bot) {
            $this->session->flash('error', 'Bot not found');
            $this->redirect('/bots');
        }
        $flows = $flowModel->getByBot($botId, $tenantId);
        $this->view('flows/index', ['title' => 'Flows', 'bot' => $bot, 'flows' => $flows]);
    }

    public function create($botId) {
        $this->requireAuth();
        require_once __DIR__ . '/../models/Bot.php';
        $botModel = new Bot();
        $bot = $botModel->find($botId, $this->getTenantId());
        $this->view('flows/create', ['title' => 'Create Flow', 'bot' => $bot]);
    }

    public function store($botId) {
        $this->requireAuth();
        $this->requireCsrf();
        require_once __DIR__ . '/../models/Flow.php';
        $model = new Flow();
        $model->create([
            'tenant_id' => $this->getTenantId(),
            'bot_id' => $botId,
            'name' => $this->request->post('name'),
            'description' => $this->request->post('description'),
            'trigger_type' => $this->request->post('trigger_type'),
            'trigger_value' => $this->request->post('trigger_value'),
            'priority' => $this->request->post('priority', 0),
            'status' => 'active'
        ]);
        $this->session->flash('success', 'Flow created successfully');
        $this->redirect('/bots/' . $botId . '/flows');
    }

    public function edit($id) {
        $this->requireAuth();
        require_once __DIR__ . '/../models/Flow.php';
        $model = new Flow();
        $flow = $model->find($id, $this->getTenantId());
        if (!$flow) {
            $this->session->flash('error', 'Flow not found');
            $this->redirect('/bots');
        }
        $this->view('flows/edit', ['title' => 'Edit Flow', 'flow' => $flow]);
    }

    public function update($id) {
        $this->requireAuth();
        $this->requireCsrf();
        require_once __DIR__ . '/../models/Flow.php';
        $model = new Flow();
        $flow = $model->find($id, $this->getTenantId());
        $model->update($id, [
            'name' => $this->request->post('name'),
            'description' => $this->request->post('description'),
            'trigger_type' => $this->request->post('trigger_type'),
            'trigger_value' => $this->request->post('trigger_value'),
            'priority' => $this->request->post('priority'),
            'status' => $this->request->post('status')
        ], $this->getTenantId());
        $this->session->flash('success', 'Flow updated successfully');
        $this->redirect('/bots/' . $flow['bot_id'] . '/flows');
    }

    public function delete($id) {
        $this->requireAuth();
        $this->requireCsrf();
        require_once __DIR__ . '/../models/Flow.php';
        $model = new Flow();
        $flow = $model->find($id, $this->getTenantId());
        $model->delete($id, $this->getTenantId());
        $this->session->flash('success', 'Flow deleted successfully');
        $this->redirect('/bots/' . $flow['bot_id'] . '/flows');
    }
}
