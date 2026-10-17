<?php
// FILE: /app/controllers/BroadcastController.php

/**
 * Broadcast Controller - campaigns to contact segments, now or scheduled.
 * Scheduled campaigns are sent by `php cron/run.php broadcasts`.
 */
class BroadcastController extends Controller {

    const STATUSES = ['draft', 'scheduled', 'running', 'completed', 'cancelled'];

    public function index() {
        $tenantId = $this->requireTenant(self::BUILDER_ROLES);
        $model = new Broadcast();
        $filters = ['status' => in_array($this->request->get('status'), self::STATUSES, true) ? $this->request->get('status') : null];
        $search = $this->request->get('search');
        $pagination = $this->getPagination($model->count($filters, $tenantId, $search));

        $this->view('broadcasts/index', [
            'title' => 'Broadcasts',
            'broadcasts' => $model->listWithDetails($tenantId, $filters, $search, $pagination['per_page'], $pagination['offset']),
            'pagination' => $pagination,
            'filters' => $filters + ['search' => $search],
            'statuses' => self::STATUSES
        ]);
    }

    public function show($id) {
        $tenantId = $this->requireTenant(self::BUILDER_ROLES);
        $broadcast = (new Broadcast())->getWithStats((int) $id, $tenantId);
        if (!$broadcast) {
            $this->abort(404, 'Broadcast not found');
        }

        $recipientModel = new BroadcastMessage();
        $status = $this->request->get('status');
        $status = in_array($status, ['pending', 'sent', 'failed'], true) ? $status : null;
        $pagination = $this->getPagination($recipientModel->countByBroadcast($broadcast['id'], $tenantId, $status), 50);

        $filter = json_array($broadcast['target_filter']);
        $segment = (new Contact())->getSegment($tenantId, $filter);

        $this->view('broadcasts/show', [
            'title' => $broadcast['name'],
            'broadcast' => $broadcast,
            'recipients' => $recipientModel->getByBroadcast($broadcast['id'], $tenantId, $status, $pagination['per_page'], $pagination['offset']),
            'pagination' => $pagination,
            'statusFilter' => $status,
            'segmentLabel' => $this->segmentLabel($filter, $tenantId),
            'segmentSize' => count($segment),
            'preview' => !empty($segment) ? $this->preview($broadcast, $segment[0], $tenantId) : null,
            'timezone' => $this->tenantTimezone($tenantId)
        ]);
    }

    public function create() {
        $tenantId = $this->requireTenant(self::BUILDER_ROLES);
        $this->view('broadcasts/create', [
            'title' => 'New Broadcast',
            'channels' => (new Channel())->getForSelect($tenantId),
            'bots' => (new Bot())->getForSelect($tenantId),
            'templates' => (new Template())->getActiveForSelect($tenantId),
            'tags' => (new Tag())->getForTenant($tenantId),
            'timezone' => $this->tenantTimezone($tenantId)
        ]);
    }

    public function store() {
        $tenantId = $this->requireTenant(self::BUILDER_ROLES);
        $this->requireCsrf();

        $data = [
            'name' => $this->request->post('name'),
            'channel_id' => (int) $this->request->post('channel_id'),
            'bot_id' => (int) $this->request->post('bot_id') ?: null,
            'template_id' => (int) $this->request->post('template_id') ?: null,
            'message_content' => $this->request->post('message_content') ?: null
        ];
        $mode = $this->request->post('schedule_mode', 'now');
        $errors = $this->validate($data + ['schedule_mode' => $mode], [
            'name' => 'required|min:2|max:255',
            'channel_id' => 'required|integer',
            'message_content' => 'max:4096',
            'schedule_mode' => 'required|in:now,later,draft'
        ]);

        if (!(new Channel())->find($data['channel_id'], $tenantId)) {
            $errors['channel_id'][] = 'Choose one of your channels';
        }
        if ($data['bot_id'] && !(new Bot())->find($data['bot_id'], $tenantId)) {
            $errors['bot_id'][] = 'Choose one of your bots';
        }
        if ($data['template_id'] && !(new Template())->find($data['template_id'], $tenantId)) {
            $errors['template_id'][] = 'Choose one of your templates';
        }
        if (!$data['template_id'] && !$data['message_content']) {
            $errors['message_content'][] = 'Choose a template or write a message';
        }

        // Target segment
        $targetType = $this->request->post('target_type', 'all');
        $filter = ['type' => 'all'];
        if ($targetType === 'tag') {
            $tagId = (int) $this->request->post('tag_id');
            if (!(new Tag())->find($tagId, $tenantId)) {
                $errors['tag_id'][] = 'Choose a tag';
            }
            $filter = ['type' => 'tag', 'tag_id' => $tagId];
        } elseif ($targetType === 'custom') {
            $field = trim((string) $this->request->post('custom_field'));
            if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,49}$/', $field)) {
                $errors['custom_field'][] = 'Enter a field name like "industry" or "city"';
            }
            $filter = ['type' => 'custom', 'field' => $field, 'value' => (string) $this->request->post('custom_value')];
        }

        list($mapping, $mappingErrors) = ContactController::textToFields((string) $this->request->post('template_variables'));
        if (!empty($mappingErrors)) {
            $errors['template_variables'] = $mappingErrors;
        }

        $scheduledAt = null;
        if ($mode === 'later') {
            $scheduledAt = $this->toUtc((string) $this->request->post('scheduled_at'), $tenantId);
            if ($scheduledAt === null || strtotime($scheduledAt) <= time()) {
                $errors['scheduled_at'][] = 'Choose a date and time in the future';
            }
        }

        if (!empty($errors)) {
            $this->failValidation($errors);
        }

        $model = new Broadcast();
        $id = $model->create($data + [
            'tenant_id' => $tenantId,
            'target_filter' => json_encode($filter),
            'template_variables' => json_encode((object) $mapping),
            'status' => $mode === 'later' ? 'scheduled' : 'draft',
            'scheduled_at' => $scheduledAt
        ]);

        if ($mode === 'now') {
            $stats = BroadcastRunner::run($id, $tenantId);
            $this->session->flash('success', "Broadcast sent: {$stats['sent']} delivered, {$stats['failed']} failed.");
        } else {
            $this->session->flash('success', $mode === 'later' ? 'Broadcast scheduled' : 'Broadcast saved as draft');
        }
        $this->redirect('/broadcasts/' . $id);
    }

    public function send($id) {
        $tenantId = $this->requireTenant(self::BUILDER_ROLES);
        $this->requireCsrf();
        $broadcast = $this->findOwned(new Broadcast(), $id, $tenantId);
        $stats = BroadcastRunner::run($broadcast['id'], $tenantId);
        if ($stats === false) {
            $this->session->flash('error', 'Only draft or scheduled broadcasts can be sent');
        } else {
            $this->session->flash('success', "Broadcast sent: {$stats['sent']} delivered, {$stats['failed']} failed.");
        }
        $this->redirect('/broadcasts/' . $broadcast['id']);
    }

    public function cancel($id) {
        $tenantId = $this->requireTenant(self::BUILDER_ROLES);
        $this->requireCsrf();
        $model = new Broadcast();
        $broadcast = $this->findOwned($model, $id, $tenantId);
        if (!in_array($broadcast['status'], ['draft', 'scheduled'], true)) {
            $this->session->flash('error', 'Only draft or scheduled broadcasts can be cancelled');
        } else {
            $model->update($broadcast['id'], ['status' => 'cancelled'], $tenantId);
            $this->session->flash('success', 'Broadcast cancelled');
        }
        $this->redirect('/broadcasts/' . $broadcast['id']);
    }

    public function delete($id) {
        $tenantId = $this->requireTenant(self::BUILDER_ROLES);
        $this->requireCsrf();
        $model = new Broadcast();
        $broadcast = $this->findOwned($model, $id, $tenantId);
        if ($broadcast['status'] === 'running' && !$model->isStale($broadcast)) {
            $this->session->flash('error', 'A running broadcast cannot be deleted');
            $this->redirect('/broadcasts/' . $broadcast['id']);
        }
        $model->delete($broadcast['id'], $tenantId);
        $this->session->flash('success', 'Broadcast deleted');
        $this->redirect('/broadcasts');
    }

    private function preview($broadcast, $contact, $tenantId) {
        $variables = BroadcastRunner::variablesFor($contact, json_array($broadcast['template_variables']));
        if ($broadcast['template_id']) {
            $template = (new Template())->find($broadcast['template_id'], $tenantId);
            return $template ? Template::renderFull($template, $variables) : null;
        }
        return Template::render((string) $broadcast['message_content'], $variables);
    }

    private function segmentLabel($filter, $tenantId) {
        $type = isset($filter['type']) ? $filter['type'] : 'all';
        if ($type === 'tag') {
            $tag = (new Tag())->find(isset($filter['tag_id']) ? (int) $filter['tag_id'] : 0, $tenantId);
            return 'Contacts tagged "' . ($tag ? $tag['name'] : 'deleted tag') . '"';
        }
        if ($type === 'custom') {
            return 'Contacts where ' . $filter['field'] . ' = "' . $filter['value'] . '"';
        }
        return 'All active contacts';
    }

    private function tenantTimezone($tenantId) {
        $tenant = (new Tenant())->find($tenantId);
        return $tenant && in_array($tenant['timezone'], DateTimeZone::listIdentifiers(), true) ? $tenant['timezone'] : 'UTC';
    }

    /** Convert a datetime-local value entered in the tenant's timezone to UTC. */
    private function toUtc($value, $tenantId) {
        if ($value === '') {
            return null;
        }
        try {
            $date = new DateTime($value, new DateTimeZone($this->tenantTimezone($tenantId)));
        } catch (Exception $e) {
            return null;
        }
        $date->setTimezone(new DateTimeZone('UTC'));
        return $date->format('Y-m-d H:i:s');
    }
}
