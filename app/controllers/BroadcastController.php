<?php
// FILE: /app/controllers/BroadcastController.php

class BroadcastController extends Controller {
    public function index() {
        $this->requireAuth();
        require_once __DIR__ . '/../models/Broadcast.php';
        $model = new Broadcast();
        $broadcasts = $model->getWithDetails($this->getTenantId());
        $this->view('broadcasts/index', ['title' => 'Broadcasts', 'broadcasts' => $broadcasts]);
    }

    public function show($id) {
        $this->requireAuth();
        require_once __DIR__ . '/../models/Broadcast.php';
        $model = new Broadcast();
        $broadcast = $model->getWithStats($id, $this->getTenantId());
        if (!$broadcast) {
            $this->session->flash('error', 'Broadcast not found');
            $this->redirect('/broadcasts');
        }
        $this->view('broadcasts/show', ['title' => 'Broadcast Details', 'broadcast' => $broadcast]);
    }

    public function create() {
        $this->requireAuth();
        require_once __DIR__ . '/../models/Channel.php';
        require_once __DIR__ . '/../models/Template.php';
        $channelModel = new Channel();
        $templateModel = new Template();
        $channels = $channelModel->findAll([], $this->getTenantId());
        $templates = $templateModel->findAll([], $this->getTenantId());
        $this->view('broadcasts/create', ['title' => 'Create Broadcast', 'channels' => $channels, 'templates' => $templates]);
    }

    public function store() {
        $this->requireAuth();
        $this->requireCsrf();
        require_once __DIR__ . '/../models/Broadcast.php';
        $model = new Broadcast();
        $model->create([
            'tenant_id' => $this->getTenantId(),
            'channel_id' => $this->request->post('channel_id'),
            'template_id' => $this->request->post('template_id'),
            'name' => $this->request->post('name'),
            'message_content' => $this->request->post('message_content'),
            'status' => 'draft'
        ]);
        $this->session->flash('success', 'Broadcast created successfully');
        $this->redirect('/broadcasts');
    }

    public function delete($id) {
        $this->requireAuth();
        $this->requireCsrf();
        require_once __DIR__ . '/../models/Broadcast.php';
        $model = new Broadcast();
        $model->delete($id, $this->getTenantId());
        $this->session->flash('success', 'Broadcast deleted successfully');
        $this->redirect('/broadcasts');
    }
}
