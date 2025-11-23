<?php
// FILE: /app/controllers/DashboardController.php

/**
 * Dashboard Controller
 *
 * Handles main dashboard with analytics overview.
 */
class DashboardController extends Controller {

    /**
     * Show dashboard
     */
    public function index() {
        $this->requireAuth();

        $user = $this->getUser();
        $tenantId = $this->getTenantId();

        // Platform admin sees different dashboard
        if ($user['role'] === 'platform_admin') {
            $this->showPlatformDashboard();
            return;
        }

        // Load models
        require_once __DIR__ . '/../models/Bot.php';
        require_once __DIR__ . '/../models/Channel.php';
        require_once __DIR__ . '/../models/Contact.php';
        require_once __DIR__ . '/../models/Conversation.php';
        require_once __DIR__ . '/../models/Message.php';
        require_once __DIR__ . '/../models/Subscription.php';
        require_once __DIR__ . '/../models/UsageTracking.php';

        $botModel = new Bot();
        $channelModel = new Channel();
        $contactModel = new Contact();
        $conversationModel = new Conversation();
        $messageModel = new Message();
        $subscriptionModel = new Subscription();
        $usageModel = new UsageTracking();

        // Get statistics
        $stats = [
            'total_bots' => $botModel->count([], $tenantId),
            'total_channels' => $channelModel->count([], $tenantId),
            'total_contacts' => $contactModel->count([], $tenantId),
            'open_conversations' => $conversationModel->count(['status' => 'open'], $tenantId)
        ];

        // Get message statistics for current month
        $startDate = date('Y-m-01 00:00:00');
        $endDate = date('Y-m-t 23:59:59');
        $messageStats = $messageModel->getStatistics($tenantId, $startDate, $endDate);

        // Get subscription and usage
        $subscription = $subscriptionModel->getActiveSubscription($tenantId);
        $usage = $usageModel->getCurrentMonthUsage($tenantId);

        // Recalculate usage if not found
        if (!$usage) {
            $usageModel->recalculate($tenantId);
            $usage = $usageModel->getCurrentMonthUsage($tenantId);
        }

        // Get recent conversations
        $recentConversations = $conversationModel->getForInbox($tenantId, [], 5, 0);

        $this->view('dashboard/index', [
            'title' => 'Dashboard',
            'user' => $user,
            'stats' => $stats,
            'messageStats' => $messageStats,
            'subscription' => $subscription,
            'usage' => $usage,
            'recentConversations' => $recentConversations
        ]);
    }

    /**
     * Show platform admin dashboard
     */
    private function showPlatformDashboard() {
        require_once __DIR__ . '/../models/Tenant.php';
        require_once __DIR__ . '/../models/User.php';

        $tenantModel = new Tenant();
        $userModel = new User();

        $stats = [
            'total_tenants' => $tenantModel->count([]),
            'active_tenants' => $tenantModel->count(['status' => 'active']),
            'total_users' => $userModel->count([])
        ];

        $recentTenants = $tenantModel->findAll([], null, 10, 0);

        $this->view('dashboard/platform', [
            'title' => 'Platform Dashboard',
            'stats' => $stats,
            'recentTenants' => $recentTenants
        ]);
    }
}
