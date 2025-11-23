<?php
// FILE: /app/controllers/FlowStepController.php

class FlowStepController extends Controller {
    public function index($flowId) {
        $this->requireAuth();
        require_once __DIR__ . '/../models/FlowStep.php';
        $model = new FlowStep();
        $steps = $model->getByFlow($flowId, $this->getTenantId());
        $this->view('flow_steps/index', ['title' => 'Flow Steps', 'flowId' => $flowId, 'steps' => $steps]);
    }

    public function create($flowId) {
        $this->requireAuth();
        $this->view('flow_steps/create', ['title' => 'Create Step', 'flowId' => $flowId]);
    }

    public function store($flowId) {
        $this->requireAuth();
        $this->requireCsrf();
        require_once __DIR__ . '/../models/FlowStep.php';
        $model = new FlowStep();
        $actionConfig = json_encode($this->request->post('action_config'));
        $model->create([
            'tenant_id' => $this->getTenantId(),
            'flow_id' => $flowId,
            'step_order' => $this->request->post('step_order'),
            'action_type' => $this->request->post('action_type'),
            'action_config' => $actionConfig
        ]);
        $this->session->flash('success', 'Step created successfully');
        $this->redirect('/flows/' . $flowId . '/steps');
    }

    public function edit($id) {
        $this->requireAuth();
        require_once __DIR__ . '/../models/FlowStep.php';
        $model = new FlowStep();
        $step = $model->find($id, $this->getTenantId());
        if (!$step) {
            $this->session->flash('error', 'Step not found');
            $this->redirect('/bots');
        }
        $this->view('flow_steps/edit', ['title' => 'Edit Step', 'step' => $step]);
    }

    public function update($id) {
        $this->requireAuth();
        $this->requireCsrf();
        require_once __DIR__ . '/../models/FlowStep.php';
        $model = new FlowStep();
        $step = $model->find($id, $this->getTenantId());
        $actionConfig = json_encode($this->request->post('action_config'));
        $model->update($id, [
            'step_order' => $this->request->post('step_order'),
            'action_type' => $this->request->post('action_type'),
            'action_config' => $actionConfig
        ], $this->getTenantId());
        $this->session->flash('success', 'Step updated successfully');
        $this->redirect('/flows/' . $step['flow_id'] . '/steps');
    }

    public function delete($id) {
        $this->requireAuth();
        $this->requireCsrf();
        require_once __DIR__ . '/../models/FlowStep.php';
        $model = new FlowStep();
        $step = $model->find($id, $this->getTenantId());
        $model->delete($id, $this->getTenantId());
        $this->session->flash('success', 'Step deleted successfully');
        $this->redirect('/flows/' . $step['flow_id'] . '/steps');
    }
}
