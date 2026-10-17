<?php
// FILE: /app/controllers/DashboardController.php

/**
 * Dashboard Controller - tenant overview or platform overview.
 */
class DashboardController extends Controller {

    public function index() {
        $this->requireAuth();
        $user = $this->getUser();

        if ($user['role'] === 'platform_admin') {
            $this->view('dashboard/platform', [
                'title' => 'Platform Dashboard',
                'totals' => (new Analytics())->platformTotals(),
                'recentTenants' => (new Tenant())->listWithStats([], null, 10, 0)
            ]);
            return;
        }

        $tenantId = $this->getTenantId();
        $conversationModel = new Conversation();
        $analytics = new Analytics();
        $byStatus = $conversationModel->countByStatus($tenantId);

        $start = date('Y-m-d', strtotime('-13 days'));
        $end = date('Y-m-d');

        (new UsageTracking())->recalculate($tenantId);

        $this->view('dashboard/index', [
            'title' => 'Dashboard',
            'stats' => [
                'total_bots' => (new Bot())->count([], $tenantId),
                'active_bots' => (new Bot())->count(['status' => 'active'], $tenantId),
                'total_channels' => (new Channel())->count([], $tenantId),
                'total_contacts' => (new Contact())->count([], $tenantId),
                'open_conversations' => $byStatus['open'],
                'pending_conversations' => $byStatus['pending']
            ],
            'messageStats' => (new Message())->getStatistics($tenantId, date('Y-m-01 00:00:00'), date('Y-m-d 23:59:59')),
            'subscription' => (new Subscription())->getActiveSubscription($tenantId),
            'quota' => QuotaService::summary($tenantId),
            'chart' => $analytics->messagesPerDay($tenantId, $start, $end),
            'responseTimes' => $analytics->averageResponseTimes($tenantId, $start, $end),
            'topFlows' => (new Flow())->topFlows($tenantId, $start . ' 00:00:00', $end . ' 23:59:59', 5),
            'topKeywords' => $analytics->topKeywords($tenantId, $start, $end, 5),
            'recentConversations' => $conversationModel->getForInbox($tenantId, [], 5, 0)
        ]);
    }
}
