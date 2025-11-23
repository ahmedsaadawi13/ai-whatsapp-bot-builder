<?php
// FILE: /app/controllers/ContactController.php

class ContactController extends Controller {
    public function index() {
        $this->requireAuth();
        $tenantId = $this->getTenantId();
        $page = max(1, (int) $this->request->get('page', 1));
        $perPage = 20;
        require_once __DIR__ . '/../models/Contact.php';
        $model = new Contact();
        $filters = ['status' => $this->request->get('status'), 'search' => $this->request->get('search')];
        $filters = array_filter($filters);
        $total = $model->count($filters, $tenantId);
        $pagination = $this->getPagination($total, $perPage);
        $contacts = $model->getWithTags($tenantId, $filters, $perPage, $pagination['offset']);
        $this->view('contacts/index', ['title' => 'Contacts', 'contacts' => $contacts, 'pagination' => $pagination]);
    }

    public function show($id) {
        $this->requireAuth();
        require_once __DIR__ . '/../models/Contact.php';
        $model = new Contact();
        $contact = $model->find($id, $this->getTenantId());
        if (!$contact) {
            $this->session->flash('error', 'Contact not found');
            $this->redirect('/contacts');
        }
        $this->view('contacts/show', ['title' => 'Contact Details', 'contact' => $contact]);
    }

    public function create() {
        $this->requireAuth();
        $this->view('contacts/create', ['title' => 'Create Contact']);
    }

    public function store() {
        $this->requireAuth();
        $this->requireCsrf();
        require_once __DIR__ . '/../models/Contact.php';
        $model = new Contact();
        $model->create([
            'tenant_id' => $this->getTenantId(),
            'phone_number' => $this->request->post('phone_number'),
            'name' => $this->request->post('name'),
            'email' => $this->request->post('email'),
            'status' => 'active'
        ]);
        $this->session->flash('success', 'Contact created successfully');
        $this->redirect('/contacts');
    }

    public function edit($id) {
        $this->requireAuth();
        require_once __DIR__ . '/../models/Contact.php';
        $model = new Contact();
        $contact = $model->find($id, $this->getTenantId());
        if (!$contact) {
            $this->session->flash('error', 'Contact not found');
            $this->redirect('/contacts');
        }
        $this->view('contacts/edit', ['title' => 'Edit Contact', 'contact' => $contact]);
    }

    public function update($id) {
        $this->requireAuth();
        $this->requireCsrf();
        require_once __DIR__ . '/../models/Contact.php';
        $model = new Contact();
        $model->update($id, [
            'phone_number' => $this->request->post('phone_number'),
            'name' => $this->request->post('name'),
            'email' => $this->request->post('email'),
            'status' => $this->request->post('status')
        ], $this->getTenantId());
        $this->session->flash('success', 'Contact updated successfully');
        $this->redirect('/contacts/' . $id);
    }

    public function delete($id) {
        $this->requireAuth();
        $this->requireCsrf();
        require_once __DIR__ . '/../models/Contact.php';
        $model = new Contact();
        $model->delete($id, $this->getTenantId());
        $this->session->flash('success', 'Contact deleted successfully');
        $this->redirect('/contacts');
    }

    public function import() {
        $this->requireAuth();
        $this->requireCsrf();
        $this->session->flash('info', 'Import feature coming soon');
        $this->redirect('/contacts');
    }
}
