<?php
// FILE: /app/controllers/InboxController.php

/**
 * Inbox Controller
 *
 * Handles agent inbox for managing conversations.
 */
class InboxController extends Controller {

    /**
     * Show inbox
     */
    public function index() {
        $this->requireAuth();

        $tenantId = $this->getTenantId();
        $page = max(1, (int) $this->request->get('page', 1));
        $perPage = 20;

        $filters = [
            'status' => $this->request->get('status')
        ];

        // Remove null filters
        $filters = array_filter($filters, function($v) { return $v !== null; });

        require_once __DIR__ . '/../models/Conversation.php';
        $conversationModel = new Conversation();

        $total = $conversationModel->count($filters, $tenantId);
        $pagination = $this->getPagination($total, $perPage);
        $conversations = $conversationModel->getForInbox($tenantId, $filters, $perPage, $pagination['offset']);

        $this->view('inbox/index', [
            'title' => 'Inbox',
            'conversations' => $conversations,
            'pagination' => $pagination,
            'filters' => $filters
        ]);
    }
}
