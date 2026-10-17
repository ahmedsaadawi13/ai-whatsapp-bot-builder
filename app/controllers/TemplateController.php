<?php
// FILE: /app/controllers/TemplateController.php

/**
 * Template Controller - message templates with {{placeholders}}.
 */
class TemplateController extends Controller {

    const LANGUAGES = ['en', 'ar', 'es', 'fr', 'de', 'pt'];

    public function index() {
        $tenantId = $this->requireTenant(self::BUILDER_ROLES);
        $model = new Template();
        $filters = [
            'category' => in_array($this->request->get('category'), Template::CATEGORIES, true) ? $this->request->get('category') : null,
            'status' => in_array($this->request->get('status'), ['active', 'inactive'], true) ? $this->request->get('status') : null
        ];
        $search = $this->request->get('search');
        $pagination = $this->getPagination($model->count($filters, $tenantId, $search));

        $this->view('templates/index', [
            'title' => 'Message Templates',
            'templates' => $model->findAll($filters, $tenantId, $pagination['per_page'], $pagination['offset'], $search),
            'pagination' => $pagination,
            'filters' => $filters + ['search' => $search],
            'categories' => Template::CATEGORIES
        ]);
    }

    public function create() {
        $this->requireTenant(self::BUILDER_ROLES);
        $this->view('templates/create', ['title' => 'Create Template', 'categories' => Template::CATEGORIES, 'languages' => self::LANGUAGES]);
    }

    public function store() {
        $tenantId = $this->requireTenant(self::BUILDER_ROLES);
        $this->requireCsrf();
        $data = $this->collect();
        $errors = $this->validateTemplate($data);
        if (!empty($errors)) {
            $this->failValidation($errors);
        }
        (new Template())->create($data + ['tenant_id' => $tenantId]);
        $this->session->flash('success', 'Template created');
        $this->redirect('/templates');
    }

    public function edit($id) {
        $tenantId = $this->requireTenant(self::BUILDER_ROLES);
        $this->view('templates/edit', [
            'title' => 'Edit Template',
            'template' => $this->findOwned(new Template(), $id, $tenantId),
            'categories' => Template::CATEGORIES,
            'languages' => self::LANGUAGES
        ]);
    }

    public function update($id) {
        $tenantId = $this->requireTenant(self::BUILDER_ROLES);
        $this->requireCsrf();
        $model = new Template();
        $template = $this->findOwned($model, $id, $tenantId);
        $data = $this->collect();
        $errors = $this->validateTemplate($data);
        if (!empty($errors)) {
            $this->failValidation($errors);
        }
        $model->update($template['id'], $data, $tenantId);
        $this->session->flash('success', 'Template updated');
        $this->redirect('/templates');
    }

    public function delete($id) {
        $tenantId = $this->requireTenant(self::BUILDER_ROLES);
        $this->requireCsrf();
        $model = new Template();
        $template = $this->findOwned($model, $id, $tenantId);
        $model->delete($template['id'], $tenantId);
        $this->session->flash('success', 'Template deleted');
        $this->redirect('/templates');
    }

    private function collect() {
        $data = [
            'name' => $this->request->post('name'),
            'category' => $this->request->post('category'),
            'language' => $this->request->post('language', 'en'),
            'header' => $this->request->post('header') ?: null,
            'body' => $this->request->post('body'),
            'footer' => $this->request->post('footer') ?: null,
            'status' => $this->request->post('status', 'active')
        ];
        // Placeholders are detected automatically from header/body/footer.
        $data['variables'] = json_encode(Template::extractVariables($data['header'] . ' ' . $data['body'] . ' ' . $data['footer']));
        return $data;
    }

    private function validateTemplate($data) {
        return $this->validate($data, [
            'name' => 'required|min:2|max:255',
            'category' => 'required|in:' . implode(',', Template::CATEGORIES),
            'language' => 'required|in:' . implode(',', self::LANGUAGES),
            'header' => 'max:255',
            'body' => 'required|max:4096',
            'footer' => 'max:255',
            'status' => 'required|in:active,inactive'
        ]);
    }
}
