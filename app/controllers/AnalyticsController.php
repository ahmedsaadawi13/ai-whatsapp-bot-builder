<?php
// FILE: /app/controllers/AnalyticsController.php

class AnalyticsController extends Controller {
    public function index() {
        $this->requireAuth();
        $tenantId = $this->getTenantId();
        $startDate = $this->request->get('start_date', date('Y-m-01'));
        $endDate = $this->request->get('end_date', date('Y-m-t'));
        require_once __DIR__ . '/../models/Message.php';
        $messageModel = new Message();
        $stats = $messageModel->getStatistics($tenantId, $startDate . ' 00:00:00', $endDate . ' 23:59:59');
        $this->view('analytics/index', ['title' => 'Analytics', 'stats' => $stats, 'startDate' => $startDate, 'endDate' => $endDate]);
    }

    public function messages() {
        $this->requireAuth();
        $tenantId = $this->getTenantId();
        $startDate = $this->request->get('start_date', date('Y-m-01'));
        $endDate = $this->request->get('end_date', date('Y-m-t'));
        require_once __DIR__ . '/../models/Message.php';
        $messageModel = new Message();
        $stats = $messageModel->getStatistics($tenantId, $startDate . ' 00:00:00', $endDate . ' 23:59:59');
        $this->view('analytics/messages', ['title' => 'Message Analytics', 'stats' => $stats, 'startDate' => $startDate, 'endDate' => $endDate]);
    }

    public function conversations() {
        $this->requireAuth();
        $tenantId = $this->getTenantId();
        require_once __DIR__ . '/../models/Conversation.php';
        $conversationModel = new Conversation();
        $stats = [
            'total' => $conversationModel->count([], $tenantId),
            'open' => $conversationModel->count(['status' => 'open'], $tenantId),
            'closed' => $conversationModel->count(['status' => 'closed'], $tenantId),
            'pending' => $conversationModel->count(['status' => 'pending'], $tenantId)
        ];
        $this->view('analytics/conversations', ['title' => 'Conversation Analytics', 'stats' => $stats]);
    }
}
