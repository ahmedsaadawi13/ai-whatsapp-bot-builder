<?php
// FILE: /app/controllers/FlowStepController.php

/**
 * FlowStep Controller - the ordered actions of a flow.
 *
 * The form posts simple fields per action type; they are converted into
 * the action_config JSON consumed by BotEngine.
 */
class FlowStepController extends Controller {

    const ACTION_LABELS = [
        'send_text' => 'Send text message',
        'send_media' => 'Send media (image/file)',
        'ask_question' => 'Ask a question (quick replies / save answer)',
        'call_ai' => 'Answer with AI (knowledge base)',
        'call_webhook' => 'Call external webhook',
        'set_variable' => 'Set conversation variable',
        'branch_condition' => 'Branch on a condition'
    ];

    const OPERATORS = ['==', '!=', '>', '>=', '<', '<=', 'contains', 'exists'];

    public function index($flowId) {
        $tenantId = $this->requireTenant(self::BUILDER_ROLES);
        $flow = $this->findOwned(new Flow(), $flowId, $tenantId);
        $steps = (new FlowStep())->getByFlow($flow['id'], $tenantId);
        foreach ($steps as $i => $step) {
            $steps[$i]['config'] = json_array($step['action_config']);
        }
        $this->view('flow_steps/index', [
            'title' => 'Steps - ' . $flow['name'],
            'flow' => $flow,
            'bot' => (new Bot())->find($flow['bot_id'], $tenantId),
            'steps' => $steps,
            'actionLabels' => self::ACTION_LABELS
        ]);
    }

    public function create($flowId) {
        $tenantId = $this->requireTenant(self::BUILDER_ROLES);
        $flow = $this->findOwned(new Flow(), $flowId, $tenantId);
        $this->view('flow_steps/create', $this->formData($flow, $tenantId) + [
            'title' => 'Add Step',
            'step' => ['action_type' => $this->request->get('type', 'send_text'), 'step_order' => (new FlowStep())->nextOrder($flow['id'], $tenantId)],
            'config' => []
        ]);
    }

    public function store($flowId) {
        $tenantId = $this->requireTenant(self::BUILDER_ROLES);
        $this->requireCsrf();
        $flow = $this->findOwned(new Flow(), $flowId, $tenantId);

        list($actionType, $config, $errors) = $this->buildConfig($flow, $tenantId);
        if (!empty($errors)) {
            $this->failValidation($errors);
        }

        $model = new FlowStep();
        $order = (int) $this->request->post('step_order') ?: $model->nextOrder($flow['id'], $tenantId);
        $model->create([
            'tenant_id' => $tenantId,
            'flow_id' => $flow['id'],
            'step_order' => $order,
            'action_type' => $actionType,
            'action_config' => json_encode($config)
        ]);
        $this->session->flash('success', 'Step added');
        $this->redirect('/flows/' . $flow['id'] . '/steps');
    }

    public function edit($id) {
        $tenantId = $this->requireTenant(self::BUILDER_ROLES);
        $step = $this->findOwned(new FlowStep(), $id, $tenantId);
        $flow = (new Flow())->find($step['flow_id'], $tenantId);
        $this->view('flow_steps/edit', $this->formData($flow, $tenantId) + [
            'title' => 'Edit Step',
            'step' => $step,
            'config' => json_array($step['action_config'])
        ]);
    }

    public function update($id) {
        $tenantId = $this->requireTenant(self::BUILDER_ROLES);
        $this->requireCsrf();
        $model = new FlowStep();
        $step = $this->findOwned($model, $id, $tenantId);
        $flow = (new Flow())->find($step['flow_id'], $tenantId);

        list($actionType, $config, $errors) = $this->buildConfig($flow, $tenantId, json_array($step['action_config']));
        if (!empty($errors)) {
            $this->failValidation($errors);
        }

        $model->update($step['id'], [
            'step_order' => max(1, (int) $this->request->post('step_order', $step['step_order'])),
            'action_type' => $actionType,
            'action_config' => json_encode($config)
        ], $tenantId);
        $this->session->flash('success', 'Step updated');
        $this->redirect('/flows/' . $flow['id'] . '/steps');
    }

    public function delete($id) {
        $tenantId = $this->requireTenant(self::BUILDER_ROLES);
        $this->requireCsrf();
        $model = new FlowStep();
        $step = $this->findOwned($model, $id, $tenantId);
        $model->delete($step['id'], $tenantId);
        $this->session->flash('success', 'Step deleted');
        $this->redirect('/flows/' . $step['flow_id'] . '/steps');
    }

    public function moveUp($id) {
        $this->move($id, -1);
    }

    public function moveDown($id) {
        $this->move($id, 1);
    }

    private function move($id, $direction) {
        $tenantId = $this->requireTenant(self::BUILDER_ROLES);
        $this->requireCsrf();
        $model = new FlowStep();
        $step = $this->findOwned($model, $id, $tenantId);
        $model->move($step, $direction);
        $this->redirect('/flows/' . $step['flow_id'] . '/steps');
    }

    private function formData($flow, $tenantId) {
        $otherFlows = array_filter((new Flow())->getByBot($flow['bot_id'], $tenantId), function ($f) use ($flow) {
            return (int) $f['id'] !== (int) $flow['id'];
        });
        return [
            'flow' => $flow,
            'bot' => (new Bot())->find($flow['bot_id'], $tenantId),
            'actionLabels' => self::ACTION_LABELS,
            'operators' => self::OPERATORS,
            'otherFlows' => array_values($otherFlows)
        ];
    }

    /**
     * Convert posted fields into [action_type, config array, errors].
     */
    private function buildConfig($flow, $tenantId, $existing = []) {
        $type = $this->request->post('action_type');
        $post = function ($key) {
            return isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : '';
        };
        $errors = [];
        $config = [];

        if (!array_key_exists((string) $type, self::ACTION_LABELS)) {
            return [null, [], ['action_type' => ['Choose an action type']]];
        }

        $variableName = function ($value, $field) use (&$errors) {
            if ($value !== '' && !preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,49}$/', $value)) {
                $errors[$field][] = 'Variable names may contain letters, numbers and underscores';
            }
            return $value;
        };

        switch ($type) {
            case 'send_text':
                $config['message'] = $post('message');
                if ($config['message'] === '') {
                    $errors['message'][] = 'Message is required';
                }
                break;

            case 'send_media':
                $config['caption'] = $post('caption');
                $config['media_url'] = $post('media_url');
                $file = $this->request->file('media_file');
                if ($file && $file['error'] !== UPLOAD_ERR_NO_FILE) {
                    $quota = QuotaService::check($tenantId, 'storage', $file['size'] / 1048576);
                    if (!$quota['allowed']) {
                        $errors['media_file'][] = $quota['message'];
                        break;
                    }
                    $uploader = new FileUpload();
                    $uploader->setAllowedTypes(['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'txt']);
                    $path = $uploader->upload($file, $tenantId, 'media');
                    if ($path === false) {
                        $errors['media_file'] = $uploader->getErrors();
                        break;
                    }
                    $config['media_url'] = '/files?path=' . rawurlencode($path);
                }
                if ($config['media_url'] === '' && !empty($existing['media_url'])) {
                    $config['media_url'] = $existing['media_url'];
                }
                if ($config['media_url'] === '') {
                    $errors['media_url'][] = 'Provide a media URL or upload a file';
                } elseif (strpos($config['media_url'], '/files?path=') !== 0
                    && (!preg_match('#^https?://#i', $config['media_url']) || !filter_var($config['media_url'], FILTER_VALIDATE_URL))) {
                    $errors['media_url'][] = 'Media URL must start with http:// or https://';
                }
                break;

            case 'ask_question':
                $config['question'] = $post('question');
                $config['variable'] = $variableName($post('variable'), 'variable');
                $config['options'] = Tag::parseList($post('options'));
                if ($config['question'] === '') {
                    $errors['question'][] = 'Question is required';
                }
                if ($config['variable'] === '') {
                    unset($config['variable']);
                }
                if (empty($config['options'])) {
                    unset($config['options']);
                }
                break;

            case 'call_ai':
                $config['prompt'] = $post('prompt');
                break;

            case 'call_webhook':
                $config['url'] = $post('url');
                $config['secret'] = $post('secret');
                $config['error_message'] = $post('error_message');
                if (!filter_var($config['url'], FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $config['url'])) {
                    $errors['url'][] = 'Enter a valid http(s) URL';
                }
                break;

            case 'set_variable':
                $config['key'] = $variableName($post('key'), 'key');
                $config['value'] = $post('value');
                if ($config['key'] === '') {
                    $errors['key'][] = 'Variable name is required';
                }
                break;

            case 'branch_condition':
                $config['variable'] = $variableName($post('cond_variable'), 'cond_variable');
                $config['operator'] = in_array($post('operator'), self::OPERATORS, true) ? $post('operator') : '==';
                $config['value'] = $post('cond_value');
                $config['then_message'] = $post('then_message');
                $config['else_message'] = $post('else_message');
                foreach (['then_flow_id', 'else_flow_id'] as $key) {
                    $flowId = (int) $post($key);
                    if ($flowId > 0) {
                        $target = (new Flow())->find($flowId, $tenantId);
                        if (!$target || (int) $target['bot_id'] !== (int) $flow['bot_id']) {
                            $errors[$key][] = 'Choose a flow of the same bot';
                        }
                        $config[$key] = $flowId;
                    }
                }
                if ($config['variable'] === '') {
                    $errors['cond_variable'][] = 'Variable to check is required';
                }
                break;
        }

        foreach ($config as $key => $value) {
            if (is_string($value) && mb_strlen($value, 'UTF-8') > 4000) {
                $errors[$key][] = 'Too long (max 4000 characters)';
            }
        }

        return [$type, $config, $errors];
    }
}
