<?php
// FILE: /app/controllers/TemplateController.php

class TemplateController extends Controller {
    public function index() {
        $this->requireAuth();
        require_once __DIR__ . '/../models/Template.php';
        $model = new Template();
        $templates = $model->findAll([], $this->getTenantId());
        $this->view('templates/index', ['title' => 'Templates', 'templates' => $templates]);
    }

    public function create() {
        $this->requireAuth();
        $this->view('templates/create', ['title' => 'Create Template']);
    }

    public function store() {
        $this->requireAuth();
        $this->requireCsrf();
        require_once __DIR__ . '/../models/Template.php';
        $model = new Template();
        $model->create([
            'tenant_id' => $this->getTenantId(),
            'name' => $this->request->post('name'),
            'category' => $this->request->post('category'),
            'language' => $this->request->post('language', 'en'),
            'header' => $this->request->post('header'),
            'body' => $this->request->post('body'),
            'footer' => $this->request->post('footer'),
            'variables' => $this->request->post('variables'),
            'status' => 'active'
        ]);
        $this->session->flash('success', 'Template created successfully');
        $this->redirect('/templates');
    }

    public function edit($id) {
        $this->requireAuth();
        require_once __DIR__ . '/../models/Template.php';
        $model = new Template();
        $template = $model->find($id, $this->getTenantId());
        if (!$template) {
            $this->session->flash('error', 'Template not found');
            $this->redirect('/templates');
        }
        $this->view('templates/edit', ['title' => 'Edit Template', 'template' => $template]);
    }

    public function update($id) {
        $this->requireAuth();
        $this->requireCsrf();
        require_once __DIR__ . '/../models/Template.php';
        $model = new Template();
        $model->update($id, [
            'name' => $this->request->post('name'),
            'category' => $this->request->post('category'),
            'language' => $this->request->post('language'),
            'header' => $this->request->post('header'),
            'body' => $this->request->post('body'),
            'footer' => $this->request->post('footer'),
            'variables' => $this->request->post('variables'),
            'status' => $this->request->post('status')
        ], $this->getTenantId());
        $this->session->flash('success', 'Template updated successfully');
        $this->redirect('/templates');
    }

    public function delete($id) {
        $this->requireAuth();
        $this->requireCsrf();
        require_once __DIR__ . '/../models/Template.php';
        $model = new Template();
        $model->delete($id, $this->getTenantId());
        $this->session->flash('success', 'Template deleted successfully');
        $this->redirect('/templates');
    }
}
