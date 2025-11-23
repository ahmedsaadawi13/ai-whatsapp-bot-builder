<?php
// FILE: /app/controllers/BotController.php

/**
 * Bot Controller
 *
 * Handles bot CRUD operations.
 */
class BotController extends Controller {

    /**
     * List bots
     */
    public function index() {
        $this->requireAuth();

        $tenantId = $this->getTenantId();
        $page = max(1, (int) $this->request->get('page', 1));
        $perPage = 20;

        require_once __DIR__ . '/../models/Bot.php';
        $botModel = new Bot();

        $total = $botModel->count([], $tenantId);
        $pagination = $this->getPagination($total, $perPage);
        $bots = $botModel->getByTenantWithChannel($tenantId, $perPage, $pagination['offset']);

        $this->view('bots/index', [
            'title' => 'Bots',
            'bots' => $bots,
            'pagination' => $pagination
        ]);
    }

    /**
     * Show bot details
     */
    public function show($id) {
        $this->requireAuth();

        $tenantId = $this->getTenantId();

        require_once __DIR__ . '/../models/Bot.php';
        $botModel = new Bot();
        $bot = $botModel->getWithDetails($id, $tenantId);

        if (!$bot) {
            $this->session->flash('error', 'Bot not found');
            $this->redirect('/bots');
        }

        $this->view('bots/show', [
            'title' => 'Bot Details',
            'bot' => $bot
        ]);
    }

    /**
     * Show create form
     */
    public function create() {
        $this->requireAuth();

        $tenantId = $this->getTenantId();

        require_once __DIR__ . '/../models/Channel.php';
        $channelModel = new Channel();
        $channels = $channelModel->findAll([], $tenantId);

        $this->view('bots/create', [
            'title' => 'Create Bot',
            'channels' => $channels
        ]);
    }

    /**
     * Store new bot
     */
    public function store() {
        $this->requireAuth();
        $this->requireCsrf();

        $tenantId = $this->getTenantId();

        $data = [
            'name' => $this->request->post('name'),
            'description' => $this->request->post('description'),
            'channel_id' => $this->request->post('channel_id'),
            'default_language' => $this->request->post('default_language', 'en'),
            'ai_enabled' => $this->request->post('ai_enabled', 0),
            'ai_tone' => $this->request->post('ai_tone', 'friendly'),
            'ai_max_length' => $this->request->post('ai_max_length', 500),
            'status' => $this->request->post('status', 'draft')
        ];

        $errors = $this->validate($data, [
            'name' => 'required|min:2',
            'channel_id' => 'required|numeric'
        ]);

        if (!empty($errors)) {
            $this->session->flash('error', 'Please fix the errors');
            $this->session->flash('errors', $errors);
            $this->redirect('/bots/create');
        }

        require_once __DIR__ . '/../models/Bot.php';
        $botModel = new Bot();

        $data['tenant_id'] = $tenantId;
        $botId = $botModel->create($data);

        $this->session->flash('success', 'Bot created successfully');
        $this->redirect('/bots/' . $botId);
    }

    /**
     * Show edit form
     */
    public function edit($id) {
        $this->requireAuth();

        $tenantId = $this->getTenantId();

        require_once __DIR__ . '/../models/Bot.php';
        require_once __DIR__ . '/../models/Channel.php';

        $botModel = new Bot();
        $channelModel = new Channel();

        $bot = $botModel->find($id, $tenantId);
        if (!$bot) {
            $this->session->flash('error', 'Bot not found');
            $this->redirect('/bots');
        }

        $channels = $channelModel->findAll([], $tenantId);

        $this->view('bots/edit', [
            'title' => 'Edit Bot',
            'bot' => $bot,
            'channels' => $channels
        ]);
    }

    /**
     * Update bot
     */
    public function update($id) {
        $this->requireAuth();
        $this->requireCsrf();

        $tenantId = $this->getTenantId();

        $data = [
            'name' => $this->request->post('name'),
            'description' => $this->request->post('description'),
            'channel_id' => $this->request->post('channel_id'),
            'default_language' => $this->request->post('default_language'),
            'ai_enabled' => $this->request->post('ai_enabled', 0),
            'ai_tone' => $this->request->post('ai_tone'),
            'ai_max_length' => $this->request->post('ai_max_length'),
            'status' => $this->request->post('status')
        ];

        require_once __DIR__ . '/../models/Bot.php';
        $botModel = new Bot();

        if ($botModel->update($id, $data, $tenantId)) {
            $this->session->flash('success', 'Bot updated successfully');
        } else {
            $this->session->flash('error', 'Failed to update bot');
        }

        $this->redirect('/bots/' . $id);
    }

    /**
     * Delete bot
     */
    public function delete($id) {
        $this->requireAuth();
        $this->requireCsrf();

        $tenantId = $this->getTenantId();

        require_once __DIR__ . '/../models/Bot.php';
        $botModel = new Bot();

        if ($botModel->delete($id, $tenantId)) {
            $this->session->flash('success', 'Bot deleted successfully');
        } else {
            $this->session->flash('error', 'Failed to delete bot');
        }

        $this->redirect('/bots');
    }

    /**
     * Toggle bot status
     */
    public function toggleStatus($id) {
        $this->requireAuth();
        $this->requireCsrf();

        $tenantId = $this->getTenantId();

        require_once __DIR__ . '/../models/Bot.php';
        $botModel = new Bot();

        if ($botModel->toggleStatus($id, $tenantId)) {
            $this->session->flash('success', 'Bot status updated');
        } else {
            $this->session->flash('error', 'Failed to update bot status');
        }

        $this->redirect('/bots/' . $id);
    }
}
