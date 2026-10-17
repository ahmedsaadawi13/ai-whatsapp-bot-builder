<?php
// FILE: /app/controllers/ContactController.php

/**
 * Contact Controller - contacts, tags, custom fields and CSV import.
 */
class ContactController extends Controller {

    const STATUSES = ['active', 'blocked', 'unsubscribed'];
    const IMPORT_MAX_ROWS = 5000;

    public function index() {
        $tenantId = $this->requireTenant(self::TENANT_ROLES);
        $model = new Contact();
        $filters = [
            'status' => in_array($this->request->get('status'), self::STATUSES, true) ? $this->request->get('status') : null,
            'tag_id' => (int) $this->request->get('tag_id') ?: null
        ];
        $search = $this->request->get('search');
        $pagination = $this->getPagination($model->countWithTags($tenantId, $filters, $search));

        $this->view('contacts/index', [
            'title' => 'Contacts',
            'contacts' => $model->listWithTags($tenantId, $filters, $search, $pagination['per_page'], $pagination['offset']),
            'pagination' => $pagination,
            'filters' => $filters + ['search' => $search],
            'tags' => (new Tag())->getForTenant($tenantId),
            'statuses' => self::STATUSES
        ]);
    }

    public function show($id) {
        $tenantId = $this->requireTenant(self::TENANT_ROLES);
        $contact = $this->findOwned(new Contact(), $id, $tenantId);
        $this->view('contacts/show', [
            'title' => $contact['name'] ?: $contact['phone_number'],
            'contact' => $contact,
            'contactTags' => (new Tag())->getByContact($contact['id'], $tenantId),
            'customFields' => json_array($contact['custom_fields']),
            'conversations' => (new Conversation())->getForInbox($tenantId, ['contact_id' => $contact['id']], 50, 0)
        ]);
    }

    public function create() {
        $this->requireTenant(self::TENANT_ROLES);
        $this->view('contacts/create', ['title' => 'Add Contact', 'statuses' => self::STATUSES]);
    }

    public function store() {
        $tenantId = $this->requireTenant(self::TENANT_ROLES);
        $this->requireCsrf();

        $quota = QuotaService::check($tenantId, 'contacts');
        if (!$quota['allowed']) {
            $this->session->flash('error', $quota['message']);
            $this->redirect('/contacts');
        }

        list($data, $tags, $errors) = $this->collect($tenantId, 0);
        if (!empty($errors)) {
            $this->failValidation($errors);
        }

        $id = (new Contact())->create($data + ['tenant_id' => $tenantId]);
        (new Tag())->syncContactTags($id, $tenantId, $tags);
        $contact = (new Contact())->find($id, $tenantId);
        WebhookDispatcher::dispatch($tenantId, 'contact.created', ['contact' => MessageProcessor::publicContact($contact)]);

        $this->session->flash('success', 'Contact added');
        $this->redirect('/contacts/' . $id);
    }

    public function edit($id) {
        $tenantId = $this->requireTenant(self::TENANT_ROLES);
        $contact = $this->findOwned(new Contact(), $id, $tenantId);
        $this->view('contacts/edit', [
            'title' => 'Edit Contact',
            'contact' => $contact,
            'tagList' => implode(', ', array_column((new Tag())->getByContact($contact['id'], $tenantId), 'name')),
            'customFieldsText' => self::fieldsToText(json_array($contact['custom_fields'])),
            'statuses' => self::STATUSES
        ]);
    }

    public function update($id) {
        $tenantId = $this->requireTenant(self::TENANT_ROLES);
        $this->requireCsrf();
        $model = new Contact();
        $contact = $this->findOwned($model, $id, $tenantId);

        list($data, $tags, $errors) = $this->collect($tenantId, $contact['id']);
        if (!empty($errors)) {
            $this->failValidation($errors);
        }

        $model->update($contact['id'], $data, $tenantId);
        (new Tag())->syncContactTags($contact['id'], $tenantId, $tags);
        $this->session->flash('success', 'Contact updated');
        $this->redirect('/contacts/' . $contact['id']);
    }

    public function delete($id) {
        $tenantId = $this->requireTenant(['tenant_admin']);
        $this->requireCsrf();
        $model = new Contact();
        $contact = $this->findOwned($model, $id, $tenantId);
        $model->delete($contact['id'], $tenantId);
        $this->session->flash('success', 'Contact deleted');
        $this->redirect('/contacts');
    }

    /**
     * CSV import. Columns: phone_number, name, email, tags (tags separated by "|").
     * A header row is detected automatically. Existing phone numbers are updated.
     */
    public function import() {
        $tenantId = $this->requireTenant(['tenant_admin', 'developer']);
        $this->requireCsrf();

        $file = $this->request->file('csv_file');
        if (!$file || $file['error'] !== UPLOAD_ERR_OK || $file['size'] > 5 * 1048576) {
            $this->session->flash('error', 'Upload a CSV file (max 5 MB)');
            $this->redirect('/contacts');
        }
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['csv', 'txt'], true) || !is_uploaded_file($file['tmp_name'])) {
            $this->session->flash('error', 'Only .csv files can be imported');
            $this->redirect('/contacts');
        }

        $handle = fopen($file['tmp_name'], 'r');
        $model = new Contact();
        $tagModel = new Tag();
        $created = $updated = $skipped = 0;
        $problems = [];
        $row = 0;
        $limitHit = false;

        while (($cols = fgetcsv($handle, 2000, ',', '"', '\\')) !== false && $row < self::IMPORT_MAX_ROWS) {
            $row++;
            if ($row === 1 && isset($cols[0]) && stripos($cols[0], 'phone') !== false) {
                continue; // header
            }
            $phone = Validator::normalizePhone(isset($cols[0]) ? $cols[0] : '');
            $name = isset($cols[1]) ? trim($cols[1]) : '';
            $email = isset($cols[2]) ? trim($cols[2]) : '';
            $tags = isset($cols[3]) ? array_filter(array_map('trim', explode('|', $cols[3])), 'strlen') : [];

            if (!Validator::isValidPhone($phone)) {
                $skipped++;
                if (count($problems) < 5) {
                    $problems[] = "Row {$row}: invalid phone number";
                }
                continue;
            }
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $email = '';
            }

            $existing = $model->findByPhoneNumber($phone, $tenantId);
            if ($existing) {
                $model->update($existing['id'], array_filter(['name' => mb_substr($name, 0, 255, 'UTF-8'), 'email' => $email], 'strlen'), $tenantId);
                $contactId = $existing['id'];
                $updated++;
            } else {
                if (!QuotaService::check($tenantId, 'contacts')['allowed']) {
                    $limitHit = true;
                    break;
                }
                $contactId = $model->create([
                    'tenant_id' => $tenantId,
                    'phone_number' => $phone,
                    'name' => $name !== '' ? mb_substr($name, 0, 255, 'UTF-8') : null,
                    'email' => $email !== '' ? $email : null,
                    'status' => 'active'
                ]);
                $created++;
            }
            if (!empty($tags)) {
                $current = array_column($tagModel->getByContact($contactId, $tenantId), 'name');
                $tagModel->syncContactTags($contactId, $tenantId, array_unique(array_merge($current, $tags)));
            }
        }
        fclose($handle);

        $message = "Import finished: {$created} created, {$updated} updated, {$skipped} skipped.";
        if ($limitHit) {
            $message .= ' Stopped early: your plan\'s contact limit was reached.';
        }
        if (!empty($problems)) {
            $message .= ' ' . implode('; ', $problems);
        }
        $this->session->flash($limitHit ? 'error' : 'success', $message);
        $this->redirect('/contacts');
    }

    /**
     * @return array [data, tag names, errors]
     */
    private function collect($tenantId, $exceptId) {
        $data = [
            'phone_number' => Validator::normalizePhone($this->request->post('phone_number')),
            'name' => $this->request->post('name') ?: null,
            'email' => strtolower((string) $this->request->post('email')) ?: null,
            'notes' => $this->request->post('notes') ?: null,
            'status' => $this->request->post('status', 'active')
        ];
        $errors = $this->validate($data, [
            'phone_number' => 'required|phone',
            'name' => 'max:255',
            'email' => 'email|max:255',
            'notes' => 'max:5000',
            'status' => 'required|in:' . implode(',', self::STATUSES)
        ]);

        $existing = (new Contact())->findByPhoneNumber($data['phone_number'], $tenantId);
        if ($existing && (int) $existing['id'] !== (int) $exceptId) {
            $errors['phone_number'][] = 'A contact with this phone number already exists';
        }

        list($fields, $fieldErrors) = self::textToFields((string) $this->request->post('custom_fields'));
        if (!empty($fieldErrors)) {
            $errors['custom_fields'] = $fieldErrors;
        }
        $data['custom_fields'] = empty($fields) ? null : json_encode($fields);

        $tags = Tag::parseList($this->request->post('tags'));
        return [$data, $tags, $errors];
    }

    /** "key=value" per line -> array */
    public static function textToFields($text) {
        $fields = [];
        $errors = [];
        foreach (preg_split('/\r?\n/', trim($text)) as $i => $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = explode('=', $line, 2);
            $key = trim($parts[0]);
            if (count($parts) !== 2 || !preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,49}$/', $key)) {
                $errors[] = 'Line ' . ($i + 1) . ': use key=value with a simple key (letters, numbers, _)';
                continue;
            }
            $fields[$key] = mb_substr(trim($parts[1]), 0, 500, 'UTF-8');
        }
        if (count($fields) > 30) {
            $errors[] = 'At most 30 custom fields';
        }
        return [$fields, $errors];
    }

    public static function fieldsToText($fields) {
        $lines = [];
        foreach ($fields as $key => $value) {
            $lines[] = $key . '=' . (is_scalar($value) ? $value : json_encode($value));
        }
        return implode("\n", $lines);
    }
}
