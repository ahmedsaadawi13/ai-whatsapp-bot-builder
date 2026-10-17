<?php
// FILE: /app/controllers/KnowledgeBaseController.php

/**
 * Knowledge Base Controller - FAQ entries and uploaded documents for the AI engine.
 */
class KnowledgeBaseController extends Controller {

    const DOCUMENT_TYPES = ['txt', 'pdf', 'doc', 'docx'];

    public function index() {
        $tenantId = $this->requireTenant(self::BUILDER_ROLES);
        $model = new KnowledgeBase();
        $filters = [
            'status' => in_array($this->request->get('status'), ['active', 'inactive'], true) ? $this->request->get('status') : null,
            'bot_id' => (int) $this->request->get('bot_id') ?: null
        ];
        $search = $this->request->get('search');
        $pagination = $this->getPagination($model->count($filters, $tenantId, $search));

        $this->view('knowledge_base/index', [
            'title' => 'Knowledge Base',
            'entries' => $model->listWithBot($tenantId, $filters, $search, $pagination['per_page'], $pagination['offset']),
            'pagination' => $pagination,
            'filters' => $filters + ['search' => $search],
            'bots' => (new Bot())->getForSelect($tenantId)
        ]);
    }

    public function create() {
        $tenantId = $this->requireTenant(self::BUILDER_ROLES);
        $this->view('knowledge_base/create', ['title' => 'Add Knowledge', 'bots' => (new Bot())->getForSelect($tenantId)]);
    }

    public function store() {
        $tenantId = $this->requireTenant(self::BUILDER_ROLES);
        $this->requireCsrf();
        $data = $this->collect();
        $errors = $this->validateEntry($data, $tenantId);
        if (!empty($errors)) {
            $this->failValidation($errors);
        }
        (new KnowledgeBase())->create($data + ['tenant_id' => $tenantId]);
        $this->session->flash('success', 'Knowledge base entry added');
        $this->redirect('/knowledge-base');
    }

    /**
     * Upload a TXT/PDF/DOC/DOCX document; its text becomes a searchable entry.
     */
    public function upload() {
        $tenantId = $this->requireTenant(self::BUILDER_ROLES);
        $this->requireCsrf();

        $file = $this->request->file('document');
        $topic = trim((string) $this->request->post('topic'));
        $botId = (int) $this->request->post('bot_id') ?: null;

        if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
            $this->failValidation(['document' => ['Choose a document to upload']]);
        }
        if ($botId !== null && !(new Bot())->find($botId, $tenantId)) {
            $this->failValidation(['bot_id' => ['Choose one of your bots']]);
        }

        $quota = QuotaService::check($tenantId, 'storage', $file['size'] / 1048576);
        if (!$quota['allowed']) {
            $this->session->flash('error', $quota['message']);
            $this->redirect('/knowledge-base');
        }

        $uploader = new FileUpload();
        $uploader->setAllowedTypes(self::DOCUMENT_TYPES);
        $path = $uploader->upload($file, $tenantId, 'knowledge');
        if ($path === false) {
            $this->failValidation(['document' => $uploader->getErrors()]);
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $text = DocumentText::extract(FileUpload::resolve($path), $extension);
        $name = pathinfo($file['name'], PATHINFO_FILENAME);

        (new KnowledgeBase())->create([
            'tenant_id' => $tenantId,
            'bot_id' => $botId,
            'topic' => mb_substr($topic !== '' ? $topic : $name, 0, 255, 'UTF-8'),
            'question' => mb_substr($topic !== '' ? $topic : $name, 0, 255, 'UTF-8'),
            'answer' => $text !== '' ? $text : '(No text could be extracted from this document.)',
            'keywords' => implode(',', array_slice(KnowledgeBase::terms($topic . ' ' . $name), 0, 10)),
            'category' => 'Document',
            'file_path' => $path,
            'file_size' => $uploader->getLastSize(),
            'status' => $text !== '' ? 'active' : 'inactive'
        ]);

        $this->session->flash($text !== '' ? 'success' : 'error', $text !== ''
            ? 'Document uploaded and indexed (' . mb_strlen($text, 'UTF-8') . ' characters).'
            : 'Document stored, but no text could be extracted, so the entry was left inactive.');
        $this->redirect('/knowledge-base');
    }

    public function edit($id) {
        $tenantId = $this->requireTenant(self::BUILDER_ROLES);
        $this->view('knowledge_base/edit', [
            'title' => 'Edit Knowledge',
            'entry' => $this->findOwned(new KnowledgeBase(), $id, $tenantId),
            'bots' => (new Bot())->getForSelect($tenantId)
        ]);
    }

    public function update($id) {
        $tenantId = $this->requireTenant(self::BUILDER_ROLES);
        $this->requireCsrf();
        $model = new KnowledgeBase();
        $entry = $this->findOwned($model, $id, $tenantId);
        $data = $this->collect();
        $errors = $this->validateEntry($data, $tenantId);
        if (!empty($errors)) {
            $this->failValidation($errors);
        }
        $model->update($entry['id'], $data, $tenantId);
        $this->session->flash('success', 'Entry updated');
        $this->redirect('/knowledge-base');
    }

    public function delete($id) {
        $tenantId = $this->requireTenant(self::BUILDER_ROLES);
        $this->requireCsrf();
        $model = new KnowledgeBase();
        $entry = $this->findOwned($model, $id, $tenantId);
        if ($entry['file_path']) {
            (new FileUpload())->delete($entry['file_path']);
        }
        $model->delete($entry['id'], $tenantId);
        $this->session->flash('success', 'Entry deleted');
        $this->redirect('/knowledge-base');
    }

    private function collect() {
        return [
            'bot_id' => (int) $this->request->post('bot_id') ?: null,
            'topic' => $this->request->post('topic'),
            'question' => $this->request->post('question'),
            'answer' => $this->request->post('answer'),
            'keywords' => implode(',', Tag::parseList($this->request->post('keywords'))),
            'category' => $this->request->post('category') ?: null,
            'status' => $this->request->post('status', 'active')
        ];
    }

    private function validateEntry($data, $tenantId) {
        $errors = $this->validate($data, [
            'topic' => 'required|max:255',
            'question' => 'required|max:2000',
            'answer' => 'required|max:60000',
            'keywords' => 'max:1000',
            'category' => 'max:100',
            'status' => 'required|in:active,inactive'
        ]);
        if ($data['bot_id'] !== null && !(new Bot())->find($data['bot_id'], $tenantId)) {
            $errors['bot_id'][] = 'Choose one of your bots';
        }
        return $errors;
    }
}
