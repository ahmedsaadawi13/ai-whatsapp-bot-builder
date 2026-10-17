<?php
// FILE: /app/controllers/InboxController.php

/**
 * Inbox Controller - conversation list for agents.
 * Agents see "My conversations" + unassigned ones by default.
 */
class InboxController extends Controller {

    public function index() {
        $tenantId = $this->requireTenant(self::TENANT_ROLES);
        $model = new Conversation();

        $status = $this->request->get('status', 'open');
        $filters = [
            'status' => in_array($status, Conversation::STATUSES, true) ? $status : null,
            'assigned_to' => $this->request->get('assigned_to', ''),
            'channel_id' => (int) $this->request->get('channel_id') ?: null,
            'search' => $this->request->get('search')
        ];
        if ($filters['assigned_to'] === 'me') {
            $filters['assigned_to'] = (string) $this->auth->id();
        }

        $pagination = $this->getPagination($model->countForInbox($tenantId, $filters));
        $this->view('inbox/index', [
            'title' => 'Inbox',
            'conversations' => $model->getForInbox($tenantId, $filters, $pagination['per_page'], $pagination['offset']),
            'pagination' => $pagination,
            'filters' => ['status' => $status] + $filters,
            'counts' => $model->countByStatus($tenantId),
            'agents' => (new User())->getAssignable($tenantId),
            'channels' => (new Channel())->getForSelect($tenantId)
        ]);
    }
}
