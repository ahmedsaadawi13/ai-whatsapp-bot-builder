<?php
// FILE: /app/controllers/BotController.php

/**
 * Bot Controller - bot CRUD, status toggle and a built-in chat simulator.
 */
class BotController extends Controller {

    const LANGUAGES = ['en' => 'English', 'ar' => 'Arabic', 'es' => 'Spanish', 'fr' => 'French', 'de' => 'German', 'pt' => 'Portuguese'];
    const STATUSES = ['active', 'paused', 'draft'];

    public function index() {
        $tenantId = $this->requireTenant(self::BUILDER_ROLES);
        $model = new Bot();
        $filters = [
            'status' => in_array($this->request->get('status'), self::STATUSES, true) ? $this->request->get('status') : null,
            'channel_id' => (int) $this->request->get('channel_id') ?: null
        ];
        $search = $this->request->get('search');
        $pagination = $this->getPagination($model->count($filters, $tenantId, $search));

        $this->view('bots/index', [
            'title' => 'Bots',
            'bots' => $model->listWithChannel($tenantId, $filters, $search, $pagination['per_page'], $pagination['offset']),
            'pagination' => $pagination,
            'filters' => $filters + ['search' => $search],
            'channels' => (new Channel())->getForSelect($tenantId)
        ]);
    }

    public function show($id) {
        $tenantId = $this->requireTenant(self::BUILDER_ROLES);
        $bot = (new Bot())->getWithDetails((int) $id, $tenantId);
        if (!$bot) {
            $this->abort(404, 'Bot not found');
        }
        $this->view('bots/show', [
            'title' => $bot['name'],
            'bot' => $bot,
            'flows' => (new Flow())->getByBot($bot['id'], $tenantId),
            'simulation' => $this->session->getFlash('simulation', [])
        ]);
    }

    public function create() {
        $tenantId = $this->requireTenant(self::BUILDER_ROLES);
        $this->view('bots/create', [
            'title' => 'Create Bot',
            'channels' => (new Channel())->getForSelect($tenantId),
            'tones' => AiEngine::TONES,
            'languages' => self::LANGUAGES
        ]);
    }

    public function store() {
        $tenantId = $this->requireTenant(self::BUILDER_ROLES);
        $this->requireCsrf();

        $quota = QuotaService::check($tenantId, 'bots');
        if (!$quota['allowed']) {
            $this->session->flash('error', $quota['message']);
            $this->redirect('/bots');
        }

        $data = $this->collect();
        $errors = $this->validateBot($data, $tenantId);
        if (!empty($errors)) {
            $this->failValidation($errors);
        }

        $id = (new Bot())->create($data + ['tenant_id' => $tenantId]);
        $this->session->flash('success', 'Bot created. Add a flow to define how it replies.');
        $this->redirect('/bots/' . $id);
    }

    public function edit($id) {
        $tenantId = $this->requireTenant(self::BUILDER_ROLES);
        $this->view('bots/edit', [
            'title' => 'Edit Bot',
            'bot' => $this->findOwned(new Bot(), $id, $tenantId),
            'channels' => (new Channel())->getForSelect($tenantId),
            'tones' => AiEngine::TONES,
            'languages' => self::LANGUAGES
        ]);
    }

    public function update($id) {
        $tenantId = $this->requireTenant(self::BUILDER_ROLES);
        $this->requireCsrf();
        $model = new Bot();
        $bot = $this->findOwned($model, $id, $tenantId);

        $data = $this->collect();
        $errors = $this->validateBot($data, $tenantId);
        if (!empty($errors)) {
            $this->failValidation($errors);
        }

        $model->update($bot['id'], $data, $tenantId);
        $this->session->flash('success', 'Bot updated');
        $this->redirect('/bots/' . $bot['id']);
    }

    public function delete($id) {
        $tenantId = $this->requireTenant(self::BUILDER_ROLES);
        $this->requireCsrf();
        $model = new Bot();
        $bot = $this->findOwned($model, $id, $tenantId);
        $model->delete($bot['id'], $tenantId);
        $this->session->flash('success', 'Bot deleted');
        $this->redirect('/bots');
    }

    public function toggleStatus($id) {
        $tenantId = $this->requireTenant(self::BUILDER_ROLES);
        $this->requireCsrf();
        $bot = $this->findOwned(new Bot(), $id, $tenantId);
        (new Bot())->toggleStatus($bot['id'], $tenantId);
        $this->session->flash('success', 'Bot status updated');
        $this->redirect('/bots/' . $bot['id']);
    }

    /**
     * Chat simulator: send a test message as a sandbox contact and see the replies.
     * Works for draft/paused bots too. Returns JSON for AJAX calls.
     */
    public function simulate($id) {
        $tenantId = $this->requireTenant(self::BUILDER_ROLES);
        $this->requireCsrf();
        $bot = $this->findOwned(new Bot(), $id, $tenantId);
        $message = trim((string) $this->request->post('message', ''));

        if ($message === '' || mb_strlen($message, 'UTF-8') > 1000) {
            $error = 'Enter a message (max 1000 characters)';
            if ($this->request->isAjax()) {
                $this->json(['error' => $error], 422);
            }
            $this->session->flash('error', $error);
            $this->redirect('/bots/' . $bot['id']);
        }

        if ($this->request->post('reset')) {
            $contact = (new Contact())->findByPhoneNumber(MessageProcessor::SIMULATOR_PHONE, $tenantId);
            if ($contact) {
                $conversation = (new Conversation())->findOrCreate($contact['id'], $bot['channel_id'], $tenantId, $bot['id'])[0];
                (new Conversation())->setStatus($conversation['id'], $tenantId, 'closed');
            }
        }

        $channel = (new Channel())->find($bot['channel_id'], $tenantId);
        $result = (new MessageProcessor($tenantId))->handleInbound($channel, $bot, MessageProcessor::SIMULATOR_PHONE, $message, [
            'source' => 'simulator',
            'simulation' => true
        ]);

        if ($this->request->isAjax()) {
            if (!$result['ok']) {
                $this->json(['error' => $result['error']], $result['status']);
            }
            $this->json([
                'conversation_id' => (int) $result['conversation']['id'],
                'replies' => $result['replies'],
                'handed_off' => $result['handed_off']
            ]);
        }

        if (!$result['ok']) {
            $this->session->flash('error', $result['error']);
        } else {
            $this->session->flash('simulation', ['message' => $message, 'replies' => $result['replies']]);
        }
        $this->redirect('/bots/' . $bot['id']);
    }

    private function collect() {
        return [
            'name' => $this->request->post('name'),
            'description' => $this->request->post('description'),
            'channel_id' => (int) $this->request->post('channel_id'),
            'default_language' => $this->request->post('default_language', 'en'),
            'ai_enabled' => $this->request->post('ai_enabled') ? 1 : 0,
            'ai_tone' => $this->request->post('ai_tone', 'friendly'),
            'ai_max_length' => (int) $this->request->post('ai_max_length', 500),
            'fallback_message' => $this->request->post('fallback_message') ?: null,
            'status' => $this->request->post('status', 'draft')
        ];
    }

    private function validateBot($data, $tenantId) {
        $errors = $this->validate($data, [
            'name' => 'required|min:2|max:255',
            'description' => 'max:2000',
            'channel_id' => 'required|integer',
            'default_language' => 'required|in:' . implode(',', array_keys(self::LANGUAGES)),
            'ai_tone' => 'required|in:' . implode(',', AiEngine::TONES),
            'fallback_message' => 'max:500',
            'status' => 'required|in:' . implode(',', self::STATUSES)
        ]);
        if ($data['ai_max_length'] < 50 || $data['ai_max_length'] > 4000) {
            $errors['ai_max_length'][] = 'Max answer length must be between 50 and 4000 characters';
        }
        if (!(new Channel())->find($data['channel_id'], $tenantId)) {
            $errors['channel_id'][] = 'Please choose one of your channels';
        }
        return $errors;
    }
}
