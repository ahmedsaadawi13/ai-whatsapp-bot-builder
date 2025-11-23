<?php
// FILE: /app/controllers/KnowledgeBaseController.php

class KnowledgeBaseController extends Controller {
    public function index() {
        $this->requireAuth();
        $tenantId = $this->getTenantId();
        require_once __DIR__ . '/../models/KnowledgeBase.php';
        $model = new KnowledgeBase();
        $entries = $model->findAll([], $tenantId);
        $this->view('knowledge_base/index', ['title' => 'Knowledge Base', 'entries' => $entries]);
    }

    public function create() {
        $this->requireAuth();
        require_once __DIR__ . '/../models/Bot.php';
        $botModel = new Bot();
        $bots = $botModel->findAll([], $this->getTenantId());
        $this->view('knowledge_base/create', ['title' => 'Create Entry', 'bots' => $bots]);
    }

    public function store() {
        $this->requireAuth();
        $this->requireCsrf();
        require_once __DIR__ . '/../models/KnowledgeBase.php';
        $model = new KnowledgeBase();
        $model->create([
            'tenant_id' => $this->getTenantId(),
            'bot_id' => $this->request->post('bot_id'),
            'topic' => $this->request->post('topic'),
            'question' => $this->request->post('question'),
            'answer' => $this->request->post('answer'),
            'keywords' => $this->request->post('keywords'),
            'category' => $this->request->post('category'),
            'status' => 'active'
        ]);
        $this->session->flash('success', 'Knowledge base entry created successfully');
        $this->redirect('/knowledge-base');
    }

    public function edit($id) {
        $this->requireAuth();
        require_once __DIR__ . '/../models/KnowledgeBase.php';
        require_once __DIR__ . '/../models/Bot.php';
        $model = new KnowledgeBase();
        $botModel = new Bot();
        $entry = $model->find($id, $this->getTenantId());
        $bots = $botModel->findAll([], $this->getTenantId());
        if (!$entry) {
            $this->session->flash('error', 'Entry not found');
            $this->redirect('/knowledge-base');
        }
        $this->view('knowledge_base/edit', ['title' => 'Edit Entry', 'entry' => $entry, 'bots' => $bots]);
    }

    public function update($id) {
        $this->requireAuth();
        $this->requireCsrf();
        require_once __DIR__ . '/../models/KnowledgeBase.php';
        $model = new KnowledgeBase();
        $model->update($id, [
            'bot_id' => $this->request->post('bot_id'),
            'topic' => $this->request->post('topic'),
            'question' => $this->request->post('question'),
            'answer' => $this->request->post('answer'),
            'keywords' => $this->request->post('keywords'),
            'category' => $this->request->post('category'),
            'status' => $this->request->post('status')
        ], $this->getTenantId());
        $this->session->flash('success', 'Entry updated successfully');
        $this->redirect('/knowledge-base');
    }

    public function delete($id) {
        $this->requireAuth();
        $this->requireCsrf();
        require_once __DIR__ . '/../models/KnowledgeBase.php';
        $model = new KnowledgeBase();
        $model->delete($id, $this->getTenantId());
        $this->session->flash('success', 'Entry deleted successfully');
        $this->redirect('/knowledge-base');
    }
}
