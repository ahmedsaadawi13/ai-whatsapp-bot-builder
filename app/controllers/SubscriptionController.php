<?php
// FILE: /app/controllers/SubscriptionController.php

class SubscriptionController extends Controller {
    public function index() {
        $this->requireAuth();
        $tenantId = $this->getTenantId();
        require_once __DIR__ . '/../models/Subscription.php';
        require_once __DIR__ . '/../models/UsageTracking.php';
        $subscriptionModel = new Subscription();
        $usageModel = new UsageTracking();
        $subscription = $subscriptionModel->getActiveSubscription($tenantId);
        $usage = $usageModel->getCurrentMonthUsage($tenantId);
        if (!$usage) {
            $usageModel->recalculate($tenantId);
            $usage = $usageModel->getCurrentMonthUsage($tenantId);
        }
        $this->view('subscription/index', ['title' => 'Subscription', 'subscription' => $subscription, 'usage' => $usage]);
    }

    public function plans() {
        $this->requireAuth();
        require_once __DIR__ . '/../models/Plan.php';
        $planModel = new Plan();
        $plans = $planModel->getActivePlans();
        $this->view('subscription/plans', ['title' => 'Plans', 'plans' => $plans]);
    }

    public function changePlan() {
        $this->requireAuth();
        $this->requireCsrf();
        $tenantId = $this->getTenantId();
        $planId = $this->request->post('plan_id');
        require_once __DIR__ . '/../models/Subscription.php';
        $subscriptionModel = new Subscription();
        $current = $subscriptionModel->getActiveSubscription($tenantId);
        if ($current) {
            $subscriptionModel->update($current['id'], ['status' => 'cancelled'], $tenantId);
        }
        $subscriptionModel->create([
            'tenant_id' => $tenantId,
            'plan_id' => $planId,
            'status' => 'active',
            'started_at' => date('Y-m-d H:i:s'),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+1 month'))
        ]);
        $this->session->flash('success', 'Plan changed successfully');
        $this->redirect('/subscription');
    }
}
